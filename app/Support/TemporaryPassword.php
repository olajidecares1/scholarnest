<?php

namespace App\Support;

/**
 * A password the system makes up for a new account, when the School Admin
 * has not chosen one.
 *
 * Meets Password::defaults() (upper and lower case, a number and a symbol)
 * and is easy to read off a phone and type: no 0/O, 1/l/I, and only symbols
 * that sit on the first page of a phone keyboard. Ten characters is far
 * beyond guessing at the sign-in
 * page's rate limit (most characters come from 56 letters and digits).
 */
final class TemporaryPassword
{
    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWER = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGITS = '23456789';

    private const SYMBOLS = '@#$%&*!?';

    public static function generate(int $length = 10): string
    {
        $length = max(8, $length);

        // One of each kind, then the rest from all of them, then shuffled so
        // the kinds are not always in the same places.
        $characters = [
            self::pick(self::UPPER),
            self::pick(self::LOWER),
            self::pick(self::DIGITS),
            self::pick(self::SYMBOLS),
        ];

        $all = self::UPPER.self::LOWER.self::DIGITS;

        while (count($characters) < $length) {
            $characters[] = self::pick($all);
        }

        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
