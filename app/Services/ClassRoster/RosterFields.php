<?php

namespace App\Services\ClassRoster;

use App\Enums\Gender;
use App\Models\Student;
use App\Services\Import\ClassNameMatcher;
use App\Services\Import\ReadsImportValues;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The student details a whole class can be edited by, and one way of reading
 * every value, whether it was typed into the class editor or arrived in an
 * uploaded file.
 *
 * Every value is compared in a canonical form (dates as Y-m-d, gender as the
 * enum value, status as "1"/"0", blank as null), so "14/03/2014" in a file and
 * the 2014-03-14 on record are the same date, and "ADEBAYO" typed in capitals
 * is the "Adebayo" already saved. Only a real difference is ever a change.
 *
 * Columns the school added itself ("Locker No.") are keyed "x:<heading>" and
 * stored in students.extra_fields.
 */
class RosterFields
{
    use ReadsImportValues;

    public const EXTRA_PREFIX = 'x:';

    /** Field => label, in the order the editor and the preview show them. */
    public const FIELDS = [
        'admission_number' => 'Admission No.',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of Birth',
        'class_name' => 'Class',
        'house' => 'House',
        'blood_group' => 'Blood Group',
        'guardian_name' => 'Guardian Name',
        'guardian_phone' => 'Guardian Phone',
        'guardian_email' => 'Guardian Email',
        'phone' => 'Student Phone',
        'email' => 'Student Email',
        'address' => 'Address',
        'admission_date' => 'Admission Date',
        'notes' => 'Notes',
        'is_active' => 'Status',
    ];

    public const DATE_FIELDS = ['date_of_birth', 'admission_date'];

    public const EMAIL_FIELDS = ['guardian_email', 'email'];

    public const PHONE_FIELDS = ['guardian_phone', 'phone'];

    public const NAME_FIELDS = ['first_name', 'last_name'];

    public const MAX_LENGTHS = [
        'admission_number' => 50,
        'first_name' => 100,
        'last_name' => 100,
        'house' => 100,
        'blood_group' => 20,
        'guardian_name' => 150,
        'guardian_phone' => 30,
        'guardian_email' => 255,
        'phone' => 30,
        'email' => 255,
        'address' => 2000,
        'notes' => 2000,
    ];

    /** Longest value a school-added column may hold, and the most columns. */
    public const EXTRA_MAX_LENGTH = 255;

    public const EXTRA_MAX_COLUMNS = 20;

    public const EXTRA_HEADING_MAX_LENGTH = 50;

    public function __construct(private readonly ClassNameMatcher $classes) {}

    /**
     * @param  list<string>  $classNames  the school's classes
     */
    public static function forClasses(array $classNames): self
    {
        return new self(new ClassNameMatcher($classNames));
    }

    public static function isExtra(string $key): bool
    {
        return str_starts_with($key, self::EXTRA_PREFIX);
    }

    public static function extraKey(string $heading): string
    {
        return self::EXTRA_PREFIX.$heading;
    }

    public static function extraHeading(string $key): string
    {
        return substr($key, strlen(self::EXTRA_PREFIX));
    }

    public static function label(string $key): string
    {
        return self::isExtra($key) ? self::extraHeading($key) : (self::FIELDS[$key] ?? $key);
    }

    /**
     * A heading the school typed for a new column, tidied: no stray spaces,
     * and never one that would be confused with a standard detail.
     */
    public static function cleanHeading(string $heading): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $heading)), 0, self::EXTRA_HEADING_MAX_LENGTH);
    }

    /**
     * A student's current value for every standard field, plus every
     * school-added column, in canonical form.
     *
     * @param  list<string>  $extraHeadings
     * @return array<string, string|null>
     */
    public static function current(Student $student, array $extraHeadings = []): array
    {
        $values = [];

        foreach (array_keys(self::FIELDS) as $field) {
            $values[$field] = self::canonicalFromModel($student, $field);
        }

        $extra = is_array($student->extra_fields) ? $student->extra_fields : [];

        foreach ($extraHeadings as $heading) {
            $value = $extra[$heading] ?? null;
            $values[self::extraKey($heading)] = filled($value) ? (string) $value : null;
        }

        return $values;
    }

    public static function canonicalFromModel(Student $student, string $field): ?string
    {
        if (self::isExtra($field)) {
            $value = (is_array($student->extra_fields) ? $student->extra_fields : [])[self::extraHeading($field)] ?? null;

            return filled($value) ? (string) $value : null;
        }

        $value = $student->getAttribute($field);

        return match (true) {
            $value === null => null,
            $value instanceof Gender => $value->value,
            $value instanceof CarbonInterface => $value->toDateString(),
            $field === 'is_active' => $value ? '1' : '0',
            default => trim((string) $value) === '' ? null : (string) $value,
        };
    }

    /**
     * A canonical value as the school reads it.
     */
    public static function display(string $field, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            return $date ? $date->format('d/m/Y') : $value;
        }

        return match ($field) {
            'gender' => Gender::tryFrom($value)?->label() ?? $value,
            'is_active' => $value === '1' ? 'Active' : 'Inactive',
            default => $value,
        };
    }

    /**
     * The canonical value a model attribute is saved as.
     */
    public static function toAttribute(string $field, ?string $value): mixed
    {
        return match (true) {
            $field === 'is_active' => $value === '1',
            default => $value,
        };
    }

    /**
     * Read one typed or uploaded value.
     *
     * @return array{value: string|null, error: string|null}
     */
    public function read(string $field, mixed $raw, bool $nameFromSingleColumn = false): array
    {
        $raw = is_string($raw) || is_numeric($raw) ? trim((string) $raw) : ($raw === true ? '1' : ($raw === false ? '0' : ''));
        $label = self::label($field);

        if ($raw === '') {
            return ['value' => null, 'error' => null];
        }

        if (self::isExtra($field)) {
            return mb_strlen($raw) > self::EXTRA_MAX_LENGTH
                ? ['value' => null, 'error' => "{$label} is too long (maximum ".self::EXTRA_MAX_LENGTH.' characters).']
                : ['value' => $raw, 'error' => null];
        }

        $result = match (true) {
            in_array($field, self::NAME_FIELDS, true) => ['value' => $this->nameCase($raw), 'error' => null],
            $field === 'gender' => ($gender = $this->gender($raw)) !== null
                ? ['value' => $gender->value, 'error' => null]
                : ['value' => null, 'error' => "Gender \"{$raw}\" is not recognised. Use Male or Female (M/F)."],
            in_array($field, self::DATE_FIELDS, true) => $this->readDate($field, $raw),
            in_array($field, self::EMAIL_FIELDS, true) => filter_var($raw, FILTER_VALIDATE_EMAIL)
                ? ['value' => mb_strtolower($raw), 'error' => null]
                : ['value' => null, 'error' => "{$label} \"{$raw}\" is not a valid email address."],
            in_array($field, self::PHONE_FIELDS, true) => ['value' => $this->phone($raw), 'error' => null],
            $field === 'class_name' => ($class = $this->classes->match($raw)) !== null
                ? ['value' => $class, 'error' => null]
                : ['value' => null, 'error' => $this->classes->unknownMessage($raw)],
            $field === 'is_active' => $this->readStatus($raw),
            default => ['value' => $raw, 'error' => null],
        };

        if ($result['value'] !== null && isset(self::MAX_LENGTHS[$field]) && mb_strlen($result['value']) > self::MAX_LENGTHS[$field]) {
            return ['value' => null, 'error' => "{$label} is too long (maximum ".self::MAX_LENGTHS[$field].' characters).'];
        }

        return $result;
    }

    /**
     * @return array{value: string|null, error: string|null}
     */
    private function readDate(string $field, string $raw): array
    {
        $label = self::FIELDS[$field];

        // The editor's date picker sends Y-m-d; files are day first.
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) && checkdate((int) substr($raw, 5, 2), (int) substr($raw, 8, 2), (int) substr($raw, 0, 4))
            ? CarbonImmutable::createFromFormat('!Y-m-d', $raw)
            : $this->date($raw);

        if ($date === null) {
            return ['value' => null, 'error' => "{$label} \"{$raw}\" was not understood. Use DD/MM/YYYY."];
        }

        if ($field === 'date_of_birth' && ! $date->isPast()) {
            return ['value' => null, 'error' => "{$label} {$date->format('d/m/Y')} must be in the past."];
        }

        return ['value' => $date->toDateString(), 'error' => null];
    }

    /**
     * @return array{value: string|null, error: string|null}
     */
    private function readStatus(string $raw): array
    {
        $key = (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($raw));

        return match ($key) {
            'active', 'yes', 'y', '1', 'true', 'enabled', 'current' => ['value' => '1', 'error' => null],
            'inactive', 'no', 'n', '0', 'false', 'disabled', 'deactivated', 'left', 'withdrawn' => ['value' => '0', 'error' => null],
            default => ['value' => null, 'error' => "Status \"{$raw}\" is not recognised. Use Active or Inactive."],
        };
    }

    /**
     * Two spellings of the same person's name: the same words, in any order,
     * ignoring case and spacing. "Okafor Chinedu Emeka" is "Chinedu Emeka
     * Okafor".
     */
    public static function nameTokens(?string ...$parts): string
    {
        $words = preg_split('/[\s,]+/u', mb_strtolower(trim(implode(' ', array_map('strval', $parts))))) ?: [];
        $words = array_values(array_filter($words, fn ($w) => $w !== ''));
        sort($words);

        return implode(' ', $words);
    }
}
