<?php

namespace App\Support;

/**
 * A telephone number as WhatsApp wants it, and as people read it.
 *
 * WhatsApp addresses a number by its international digits with no "+" or
 * leading zero, so the Nigerian 0803 123 4567 is 2348031234567.
 */
final class WhatsAppNumber
{
    /**
     * International digits, or null when there is not a whole number there.
     */
    public static function digits(?string $number): ?string
    {
        if (blank($number)) {
            return null;
        }

        $digits = (string) preg_replace('/\D+/', '', $number);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            // A local Nigerian number: 0803... is 234803...
            $digits = '234'.substr($digits, 1);
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        // A Nigerian number is always 234 followed by ten digits.
        if (str_starts_with($digits, '234') && strlen($digits) !== 13) {
            return null;
        }

        return $digits;
    }

    /**
     * "+234 803 123 4567" for a Nigerian number, "+447700900123" otherwise.
     */
    public static function display(string $digits): string
    {
        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            return '+234 '.substr($digits, 3, 3).' '.substr($digits, 6, 3).' '.substr($digits, 9);
        }

        return '+'.$digits;
    }

    public static function link(string $digits): string
    {
        return 'https://wa.me/'.$digits;
    }
}
