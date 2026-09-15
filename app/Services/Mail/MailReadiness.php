<?php

namespace App\Services\Mail;

/**
 * Whether this server is set up to actually deliver email.
 *
 * Laravel's default mailer is "log". Left on it, every email is written to a
 * log file and the code that sent it carries on as if it had gone out, which
 * is exactly how a production server can say "email sent" for months while
 * nobody receives anything. This is the check that refuses to call that sent.
 *
 * Only production is held to it. In development and tests, "log" and "array"
 * are the right mailers and are not a fault.
 */
class MailReadiness
{
    /**
     * Mailers that accept a message and deliver it to nobody.
     */
    private const NON_DELIVERING = ['log', 'array'];

    /**
     * What stops email being delivered, in words a Super Admin can act on, or
     * null when nothing does.
     */
    public function problem(): ?string
    {
        $mailer = (string) config('mail.default');

        if (app()->environment('production') && in_array($mailer, self::NON_DELIVERING, true)) {
            return "Email is not set up on this server: MAIL_MAILER is \"{$mailer}\", so messages are written to a log instead of being sent. "
                .'Set MAIL_MAILER=smtp and the MAIL_HOST, MAIL_PORT, MAIL_SCHEME, MAIL_USERNAME and MAIL_PASSWORD settings.';
        }

        if ($mailer === 'smtp' && blank(config('mail.mailers.smtp.host'))) {
            return 'Email is not set up on this server: MAIL_HOST is empty.';
        }

        if (blank(config('mail.from.address'))) {
            return 'Email is not set up on this server: MAIL_FROM_ADDRESS is empty.';
        }

        return null;
    }

    /**
     * The settings in use, safe to show: never the password.
     *
     * @return array<string, string>
     */
    public function summary(): array
    {
        $mailer = (string) config('mail.default');

        return array_filter([
            'Mailer' => $mailer,
            'Host' => $mailer === 'smtp' ? (string) config('mail.mailers.smtp.host') : '',
            'Port' => $mailer === 'smtp' ? (string) config('mail.mailers.smtp.port') : '',
            'Scheme' => $mailer === 'smtp' ? (string) (config('mail.mailers.smtp.scheme') ?: 'auto') : '',
            'Username' => $mailer === 'smtp' ? (string) config('mail.mailers.smtp.username') : '',
            'Password' => $mailer === 'smtp' ? (filled(config('mail.mailers.smtp.password')) ? 'set' : 'NOT SET') : '',
            'From' => trim(config('mail.from.name').' <'.config('mail.from.address').'>'),
        ], fn (string $value) => $value !== '');
    }
}
