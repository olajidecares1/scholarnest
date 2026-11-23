<?php

namespace App\Services\StudentImport;

use App\Models\AuditLog;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Registering a student/pupil who is ALREADY on the school's records, once
 * the School Admin has confirmed it is the same child: the existing record
 * is updated with the details just submitted, instead of a second record
 * being created.
 *
 * Its main use is correcting a registration mistake, most often a wrong
 * class ("registered in Basic 4, should be Basic 5"): registering the child
 * again in the right class moves them there.
 *
 * WHAT NEVER CHANGES:
 *
 *  - the admission number. Whatever was typed or generated for the new
 *    registration is ignored; the student keeps the number they have;
 *  - the record itself (its id). Results, scores, attendance, fees and
 *    payments, report cards, guardians, hostel and transport places, portal
 *    login details: everything is attached to the student's id, so all of it
 *    stays with them, exactly as it was;
 *  - how the student was first registered (registration_source), their
 *    portal password, and whether they are active.
 *
 * A detail left blank in the new registration does not wipe what the record
 * already holds: only details actually given are written.
 */
class StudentReRegistration
{
    /** The details a registration may update. */
    public const FIELDS = [
        'first_name', 'last_name', 'gender', 'date_of_birth', 'class_name', 'house',
        'guardian_name', 'guardian_phone', 'guardian_email', 'phone', 'email',
        'address', 'admission_date', 'notes', 'blood_group',
    ];

    /**
     * Applies the new details. Returns what changed, field => [from, to],
     * with dates as Y-m-d.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function apply(Student $student, array $data, ?string $photoPath = null, string $how = 'single registration'): array
    {
        return DB::transaction(function () use ($student, $data, $photoPath, $how) {
            $updates = [];

            foreach (self::FIELDS as $field) {
                $value = $data[$field] ?? null;

                if ($value instanceof \BackedEnum) {
                    $value = $value->value;
                }

                if ($value === null || (is_string($value) && trim($value) === '')) {
                    continue;
                }

                $updates[$field] = is_string($value) ? trim($value) : $value;
            }

            if ($photoPath) {
                $updates['photo_path'] = $photoPath;
            }

            $changes = [];

            foreach ($updates as $field => $value) {
                $before = self::plain($student->getAttribute($field));
                $after = self::plain($value);

                if ($before !== $after) {
                    $changes[$field] = [$before, $after];
                }
            }

            // admission_number, registration_source, password and is_active
            // are not in FIELDS, so they cannot be written here.
            $student->fill($updates)->save();

            if ($changes !== []) {
                $class = isset($changes['class_name'])
                    ? " Class changed from {$changes['class_name'][0]} to {$changes['class_name'][1]}."
                    : '';

                AuditLog::record(
                    'students.re-registered',
                    "{$student->fullName()} ({$student->admission_number}) was registered again by {$how}; the existing record was updated instead of creating a new one. Admission number kept.{$class} Changed: ".implode(', ', array_keys($changes)).'.',
                    $student,
                );
            }

            return $changes;
        });
    }

    private static function plain(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof CarbonInterface => $value->toDateString(),
            $value instanceof \BackedEnum => (string) $value->value,
            is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1 => substr($value, 0, 10),
            default => (string) $value,
        };
    }
}
