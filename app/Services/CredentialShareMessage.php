<?php

namespace App\Services;

use App\Models\School;

/**
 * The message a School Admin sends when handing over someone's login details.
 *
 * Built at the one moment the password exists in readable form - immediately
 * after it is set - and never stored. It is flashed to the session for the
 * length of one page view and is gone after that, which is why the "Share via
 * WhatsApp" button appears only on the response to saving credentials.
 *
 * On the logo: a wa.me link carries text, not attachments, so the school's
 * logo cannot be embedded in the message itself. What goes in instead is the
 * login link, which WhatsApp renders as a preview card showing the school's
 * own sign-in page - branding included. Its address is in the message too, for
 * clients that do not preview.
 */
class CredentialShareMessage
{
    /**
     * @return array{message: string, url: string, phone: ?string}
     */
    public function build(
        School $school,
        string $personName,
        string $idLabel,
        string $identifier,
        ?string $password,
        string $loginUrl,
        ?string $phone = null,
    ): array {
        $lines = [
            "*{$school->name}*",
            '',
            "Hello {$personName}, here are your sign-in details.",
            '',
            "*{$idLabel}:* {$identifier}",
        ];

        // Only when there is one to send. A password exists in readable form
        // for the single page view after it is set and never again, so a share
        // sent later carries the ID and the link, and says where the password
        // comes from - rather than a "Password:" line with nothing after it.
        $lines[] = $password === null
            ? '*Password:* issued separately by the school office.'
            : "*Password:* {$password}";

        $lines[] = '';
        $lines[] = "Sign in here: {$loginUrl}";

        if ($logo = $school->logoUrl()) {
            $lines[] = "School logo: {$logo}";
        }

        $lines[] = '';
        $lines[] = 'Please keep these details private. If you lose your password, ask the school office for a new one — it cannot be looked up.';

        $message = implode("\n", $lines);

        return [
            'message' => $message,
            'password' => $password,
            'phone' => $this->normalisePhone($phone),
            'url' => $this->shareUrl($message, $this->normalisePhone($phone)),
        ];
    }

    /**
     * wa.me with a number opens the chat with that person; without one it opens
     * WhatsApp's contact picker. The second is the sensible fallback rather
     * than hiding the button - a school that has not recorded a phone number
     * can still choose the right chat.
     */
    private function shareUrl(string $message, ?string $phone): string
    {
        return 'https://wa.me/'.($phone ?? '').'?text='.rawurlencode($message);
    }

    /**
     * Digits only, as wa.me requires. A leading 0 is dropped in favour of the
     * country code, because "08012345678" is a Nigerian local number and
     * wa.me needs it in international form to open the right chat.
     */
    private function normalisePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = config('app.default_country_code', '234').ltrim($digits, '0');
        }

        return $digits;
    }
}
