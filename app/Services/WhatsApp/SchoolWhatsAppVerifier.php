<?php

namespace App\Services\WhatsApp;

use App\Models\School;
use App\Support\WhatsAppNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Proves a school's WhatsApp number is on WhatsApp, ordinary or Business,
 * and is theirs: a 6-digit code goes to it on WhatsApp, from the platform's
 * number, and the School Admin types it back.
 *
 * WhatsApp has no lookup for "is this number on WhatsApp?", so a message
 * that arrives is the only real proof. One that cannot be delivered comes
 * back as a failed receipt (receipt()), and the page says so.
 */
class SchoolWhatsAppVerifier
{
    /** How long a code lasts, in seconds. */
    public const LIFETIME = 600;

    /** Wrong codes allowed before a new one is needed. */
    public const MAX_ATTEMPTS = 5;

    /** Seconds before another code can be sent. */
    public const RESEND_AFTER = 60;

    public function __construct(private readonly WhatsAppGateway $whatsApp) {}

    public function canVerify(): bool
    {
        return $this->whatsApp->canSendCodes();
    }

    /**
     * The code waiting to be typed back, if there is one.
     *
     * @return array{number: string, sent_at: int, attempts: int, failed: ?string, hash: string}|null
     */
    public function pending(School $school): ?array
    {
        $pending = Cache::get($this->key($school));

        return is_array($pending) ? $pending : null;
    }

    public function secondsUntilResend(School $school): int
    {
        $pending = $this->pending($school);

        return $pending === null ? 0 : max(0, $pending['sent_at'] + self::RESEND_AFTER - now()->getTimestamp());
    }

    /**
     * Send a code to this number. Returns why it could not be sent, or null.
     */
    public function sendCode(School $school, string $number): ?string
    {
        $digits = WhatsAppNumber::digits($number);

        if ($digits === null) {
            return 'Enter the whole WhatsApp number, e.g. 08031234567, or with the country code, e.g. +44 7700 900123.';
        }

        if (! $this->canVerify()) {
            return 'WhatsApp verification is not switched on for AkademicNest yet. Please contact AkademicNest support.';
        }

        $pending = $this->pending($school);

        if ($pending !== null && $pending['number'] === $digits && ($wait = $this->secondsUntilResend($school)) > 0) {
            return "A code was just sent. You can ask for another in {$wait} seconds.";
        }

        $code = (string) random_int(100000, 999999);
        $delivery = $this->whatsApp->sendCode($digits, $code);

        if (! $delivery->sent) {
            return 'The code could not be sent to '.WhatsAppNumber::display($digits).': '.$delivery->error;
        }

        Cache::put($this->key($school), [
            'number' => $digits,
            'hash' => Hash::make($code),
            'sent_at' => now()->getTimestamp(),
            'attempts' => 0,
            'failed' => null,
        ], self::LIFETIME);

        if (filled($delivery->messageId)) {
            Cache::put($this->messageKey((string) $delivery->messageId), $school->id, self::LIFETIME);
        }

        return null;
    }

    /**
     * Check the code typed back; on a match, the number is verified and
     * becomes the school's WhatsApp number. Returns why not, or null.
     */
    public function verify(School $school, string $code): ?string
    {
        $pending = $this->pending($school);

        if ($pending === null) {
            return 'That code has expired. Send a new one.';
        }

        if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
            return 'Too many wrong codes. Send a new one.';
        }

        if (! Hash::check(trim($code), $pending['hash'])) {
            $pending['attempts']++;
            Cache::put($this->key($school), $pending, max(1, $pending['sent_at'] + self::LIFETIME - now()->getTimestamp()));

            $left = self::MAX_ATTEMPTS - $pending['attempts'];

            return $left > 0
                ? "That code is not right. {$left} ".($left === 1 ? 'try' : 'tries').' left.'
                : 'Too many wrong codes. Send a new one.';
        }

        $school->forceFill([
            'whatsapp_number' => WhatsAppNumber::display($pending['number']),
            'whatsapp_verified_number' => $pending['number'],
            'whatsapp_verified_at' => now(),
        ])->save();

        $this->cancel($school);

        return null;
    }

    public function cancel(School $school): void
    {
        Cache::forget($this->key($school));
    }

    /**
     * A delivery receipt from Meta about a code. "failed" usually means the
     * number is not on WhatsApp; the page shows WhatsApp's reason.
     */
    public function receipt(string $messageId, string $status, ?string $error = null): void
    {
        if ($status !== 'failed') {
            return;
        }

        $schoolId = Cache::get($this->messageKey($messageId));
        $school = $schoolId ? School::query()->find($schoolId) : null;
        $pending = $school ? $this->pending($school) : null;

        if ($pending === null) {
            return;
        }

        $pending['failed'] = $error ?: 'WhatsApp could not deliver the code.';
        Cache::put($this->key($school), $pending, max(1, $pending['sent_at'] + self::LIFETIME - now()->getTimestamp()));
    }

    private function key(School $school): string
    {
        return "school-whatsapp-code:{$school->id}";
    }

    private function messageKey(string $messageId): string
    {
        return 'school-whatsapp-code-message:'.sha1($messageId);
    }
}
