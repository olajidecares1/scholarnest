<?php

namespace App\Support;

use App\Enums\RegistrationSource;
use App\Models\Student;

/**
 * What the School Admin is shown when a registration is refused because the
 * student/pupil is already registered: who the existing record is, its
 * admission number, every detail on it, and how it was registered, so the
 * existing record can be recognised and checked at a glance.
 *
 * Plain arrays, because it travels through the session (the flash after a
 * refused Add Student) and the cache (a bulk upload's preview).
 */
class DuplicateStudentNotice
{
    /**
     * @return array{
     *     reason: string,
     *     student_id: int,
     *     full_name: string,
     *     admission_number: string,
     *     registration_source: string,
     *     is_bulk: bool|null,
     *     show_url: string,
     *     details: array<string, string>,
     * }
     */
    public static function for(Student $student, string $reason): array
    {
        $source = $student->registration_source;

        $date = fn ($value) => $value ? $value->format('j M Y') : null;

        $details = array_filter([
            'Full name' => $student->fullName(),
            'Admission number' => (string) $student->admission_number,
            'Class' => $student->class_name,
            'Gender' => $student->gender?->label(),
            'Date of birth' => $date($student->date_of_birth),
            'House' => $student->house,
            'Blood group' => $student->blood_group,
            'Parent/guardian' => $student->guardian_name,
            'Guardian phone' => $student->guardian_phone,
            'Guardian email' => $student->guardian_email,
            'Student phone' => $student->phone,
            'Student email' => $student->email,
            'Address' => $student->address,
            'Admission date' => $date($student->admission_date),
            'Status' => $student->is_active ? 'Active' : 'Inactive',
            'Registered through' => RegistrationSource::labelFor($source),
            'Registered on' => $student->created_at?->format('j M Y, g:i a'),
        ], fn ($value) => filled($value));

        return [
            'reason' => $reason,
            'student_id' => $student->id,
            'full_name' => $student->fullName(),
            'admission_number' => (string) $student->admission_number,
            'registration_source' => RegistrationSource::labelFor($source),
            'is_bulk' => $source === null ? null : $source === RegistrationSource::Bulk,
            'show_url' => route('students.show', $student),
            'details' => $details,
        ];
    }
}
