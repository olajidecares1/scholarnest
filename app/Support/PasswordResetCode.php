<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * The six-digit code that accompanies a password-reset link.
 *
 * WHY A CODE AT ALL. The link alone is a single secret in a single place. It
 * travels through mail servers, sits in an inbox that may be shared or open on
 * a staffroom screen, and leaks through referrer headers and browser history.
 * Requiring a code as well means possession of the link is not enough: whoever
 * resets the password has to have read the email body, not merely acquired the
 * URL from it.
 *
 * WHY IT IS DERIVED RATHER THAN STORED. The obvious design gives the code its
 * own table row, and then has to answer three questions the token has already
 * answered: when does it expire, what invalidates it, and who deletes it. This
 * derives the code from the reset token itself, so all three answers are
 * Laravel's:
 *
 *   - It expires when the token does.
 *   - Requesting a new link replaces the token, and therefore the code.
 *   - Using the link deletes the token, and the code goes with it.
 *
 * There is no row to leak, no cleanup to forget, and no way for a code and a
 * token to disagree about whether they are still valid.
 *
 * WHY IT IS STILL A SECOND FACTOR. The code is an HMAC of the token under the
 * application key. Somebody holding the link holds the token and nothing else -
 * without the key they cannot compute the code, so the link on its own remains
 * insufficient exactly as intended.
 */
final class PasswordResetCode
{
    /**
     * Six digits: long enough that guessing is hopeless against the attempt
     * limit on the reset form, short enough to read off a phone and type.
     */
    private const DIGITS = 6;

    public static function for(string $token): string
    {
        $key = (string) Config::get('app.key');

        // Twelve hex characters is 48 bits, comfortably more than the 20 bits
        // the modulo reduces it to - so the digits stay evenly distributed
        // rather than favouring the low end.
        $digest = hash_hmac('sha256', $token, $key);
        $number = hexdec(substr($digest, 0, 12)) % (10 ** self::DIGITS);

        return str_pad((string) $number, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Constant-time, so a wrong code takes the same time to reject whichever
     * digit is wrong.
     */
    public static function matches(string $token, string $supplied): bool
    {
        return hash_equals(self::for($token), trim($supplied));
    }
}
