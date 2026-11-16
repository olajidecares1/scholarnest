<?php

namespace App\Services\ClassRoster;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Import\ImportTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A class's current list, to edit in Excel or Word and upload back.
 *
 * Every heading is one ClassRosterFileParser reads back as the same field,
 * and the Admission Number column is always included (even where numbers are
 * generated) because it is how each row is matched to its student. The
 * class's own added columns follow the standard ones.
 */
class ClassRosterExport extends ImportTemplate
{
    /** Field => heading written in the file. */
    private const HEADINGS = [
        'admission_number' => 'Admission Number',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of Birth (DD/MM/YYYY)',
        'class_name' => 'Class',
        'house' => 'House',
        'blood_group' => 'Blood Group',
        'guardian_name' => 'Guardian Name',
        'guardian_phone' => 'Guardian Phone',
        'guardian_email' => 'Guardian Email',
        'phone' => 'Student Phone',
        'email' => 'Student Email',
        'address' => 'Address',
        'admission_date' => 'Admission Date (DD/MM/YYYY)',
        'notes' => 'Notes',
        'is_active' => 'Status',
    ];

    private string $className = 'class';

    /**
     * @param  Collection<int, Student>  $students
     * @param  list<string>  $classNames  offered as a drop-down in Excel
     */
    public function build(string $format, SchoolClass $class, Collection $students, array $classNames = []): string
    {
        $this->className = $class->name;
        $extra = array_values($class->extra_columns ?? []);
        $fields = [...array_keys(self::HEADINGS), ...array_map(fn ($h) => RosterFields::extraKey($h), $extra)];

        $headings = [...array_values(self::HEADINGS), ...$extra];
        $rows = $students->map(function (Student $student) use ($fields, $extra) {
            $values = RosterFields::current($student, $extra);

            return array_map(fn ($field) => RosterFields::display($field, $values[$field] ?? null), $fields);
        })->values()->all();

        return $this->renderRows($format, $headings, $rows, [
            'Gender' => ['Male', 'Female'],
            'Status' => ['Active', 'Inactive'],
            'Class' => $classNames,
        ], 5);
    }

    /**
     * Headings for a list of field keys, as written in the file.
     */
    public static function heading(string $field): string
    {
        return self::HEADINGS[$field] ?? RosterFields::label($field);
    }

    protected function title(): string
    {
        return $this->className.' Class List';
    }

    protected function sheetName(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $this->className) ?: 'Class', 0, 31);
    }

    protected function filenameStem(): string
    {
        return Str::slug($this->className ?: 'class').'-class-list-'.now()->format('Y-m-d');
    }

    protected function instructions(): string
    {
        return 'Edit any detail, add rows for new students/pupils or new columns, then upload this file on the Update Class page. Keep the Admission Number column: it is how each row is matched to its student/pupil. You will see every change before anything is saved.';
    }

    protected function pdfHint(): string
    {
        return 'Dates as DD/MM/YYYY. Keep the Admission Number column so each row can be matched.';
    }
}
