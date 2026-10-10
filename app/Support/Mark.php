<?php

namespace App\Support;

/**
 * A mark as it is printed: "200", "62.5", "1,298", never "200.00".
 *
 * Replaces rtrim(rtrim($value, '0'), '.'), which trimmed zeros whether or not
 * the number had a decimal point, so a total of 200 printed as "2" and 1,300
 * as "1,3". Zeros are only ever trimmed after the decimal point here.
 */
class Mark
{
    public static function format(mixed $value, int $decimals = 2, bool $thousands = false): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return (string) $value;
        }

        $formatted = number_format((float) $value, $decimals, '.', $thousands ? ',' : '');

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
