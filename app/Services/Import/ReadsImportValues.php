<?php

namespace App\Services\Import;

use App\Enums\Gender;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turning the text in one cell of an uploaded list into a value.
 *
 * Shared by every bulk upload (students/pupils, staff), so a date, a phone
 * number, a gender or a name typed in capitals is understood the same way
 * whichever list it arrives in.
 */
trait ReadsImportValues
{
    /**
     * A heading reduced to its letters, so "Date of Birth (DD/MM/YYYY)",
     * "DATE OF BIRTH" and "Date-of-birth" all compare equal.
     */
    private function headingKey(string $heading): string
    {
        $heading = (string) preg_replace('/\(.*?\)|\[.*?\]/u', '', $heading);

        return (string) preg_replace('/[^a-z]/', '', mb_strtolower($heading));
    }

    /**
     * @return array{0: string|null, 1: string|null} [first name(s), surname]
     */
    private function splitFullName(string $name, string $order): array
    {
        // "Adebayo, Tolu Grace" is unambiguous whichever order was chosen.
        if (str_contains($name, ',')) {
            [$last, $rest] = array_map('trim', explode(',', $name, 2));

            return [$rest !== '' ? $rest : null, $last !== '' ? $last : null];
        }

        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        if (count($parts) < 2) {
            return [null, null];
        }

        if ($order === 'first_name_first') {
            $last = array_pop($parts);

            return [implode(' ', $parts), $last];
        }

        $last = array_shift($parts);

        return [implode(' ', $parts), $last];
    }

    /**
     * Registers are often typed in capitals. "ADEBAYO" becomes "Adebayo";
     * anything already in mixed case ("McDonald", "da Silva") is left alone.
     */
    private function nameCase(string $name): string
    {
        $name = trim($name);

        if ($name !== '' && mb_strtoupper($name) === $name && mb_strtolower($name) !== $name) {
            return mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
        }

        return $name;
    }

    private function gender(?string $value): ?Gender
    {
        $key = (string) preg_replace('/[^a-z]/', '', mb_strtolower((string) $value));

        return match ($key) {
            'm', 'male', 'boy', 'b', 'man' => Gender::Male,
            'f', 'female', 'girl', 'g', 'woman' => Gender::Female,
            default => null,
        };
    }

    /**
     * Excel drops the leading zero from a Nigerian mobile number stored as a
     * number (08012345678 becomes 8012345678); put it back.
     */
    private function phone(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^[789]\d{9}$/', $value)) {
            return '0'.$value;
        }

        return $value;
    }

    /**
     * Day-first, as schools here write dates, plus ISO dates and the serial
     * numbers Excel stores dates as.
     */
    private function date(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        // Excel serial date (days since 1899-12-30). Five digits only, so a
        // bare year such as 2015 is never mistaken for one.
        if (preg_match('/^\d{5}(\.\d+)?$/', $value) && (float) $value < 80000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value);
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'j F Y', 'j M Y', 'F j, Y', 'M j, Y', 'jS F Y', 'Y/m/d'];

        foreach ($formats as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date !== null && $date->format($format) === $value) {
                return $date;
            }

            // Allow 1/2/2015 as well as 01/02/2015.
            if ($date !== null && ltrim($date->format($format), '0') === ltrim($value, '0')) {
                return $date;
            }
        }

        if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $value, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }
}
