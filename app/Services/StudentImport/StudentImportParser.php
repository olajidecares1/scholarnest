<?php

namespace App\Services\StudentImport;

use App\Services\Import\ReadsImportValues;

/**
 * Reads rows of cells as students/pupils.
 *
 * Works out which column is which from the header row, whatever the school
 * called it ("Surname", "Last Name", "SEX", "D.O.B", ...), then turns every
 * row after it into the same fields the Add Student form collects, with a
 * plain-language reason for anything that cannot be imported.
 *
 * The class written on each row is read too, as it was written. Which of the
 * school's classes it means, and whether the class chosen on the upload form
 * replaces it, is decided by StudentImportController: a class name in a
 * spreadsheet can never invent a class the school does not have.
 */
class StudentImportParser
{
    use ReadsImportValues;

    public const MAX_ROWS = 1000;

    /** Single "Name" column order. */
    public const SURNAME_FIRST = 'surname_first';

    public const FIRST_NAME_FIRST = 'first_name_first';

    /**
     * Header spellings, compared with everything but letters removed.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        'admission_number' => ['admissionnumber', 'admissionno', 'admno', 'admnumber', 'admissionnum', 'admission', 'regno', 'regnumber', 'registrationnumber', 'registrationno', 'studentid', 'studentno', 'studentnumber', 'pupilid', 'pupilno', 'pupilnumber', 'idnumber', 'idno', 'matricno', 'matricnumber'],
        'first_name' => ['firstname', 'givenname', 'forename', 'fname', 'first', 'christianname'],
        'middle_name' => ['middlename', 'othername', 'othernames', 'middle', 'mname'],
        'last_name' => ['lastname', 'surname', 'familyname', 'lname', 'last'],
        'full_name' => ['name', 'names', 'fullname', 'studentname', 'studentsname', 'pupilname', 'pupilsname', 'nameofstudent', 'nameofpupil', 'namesofstudents', 'namesofpupils', 'studentfullname', 'pupilfullname'],
        'gender' => ['gender', 'sex'],
        'date_of_birth' => ['dateofbirth', 'dob', 'birthdate', 'birthday', 'datebirth'],
        'house' => ['house', 'housename', 'sporthouse', 'sportshouse'],
        'guardian_name' => ['guardianname', 'parentname', 'guardian', 'parent', 'parentguardian', 'parentguardianname', 'parentsname', 'guardiansname', 'nameofparent', 'nameofguardian', 'fathername', 'fathersname', 'mothername', 'mothersname'],
        'guardian_phone' => ['guardianphone', 'guardianphonenumber', 'guardianphoneno', 'parentphone', 'parentphonenumber', 'parentphoneno', 'parentguardianphone', 'parentguardianphonenumber', 'guardiantel', 'parenttel', 'guardianmobile', 'parentmobile', 'guardiancontact', 'parentcontact', 'parentsphone', 'guardiansphone', 'parentgsm', 'guardiangsm'],
        'guardian_email' => ['guardianemail', 'guardianemailaddress', 'parentemail', 'parentemailaddress', 'parentguardianemail', 'parentsemail', 'guardiansemail'],
        'phone' => ['phone', 'phonenumber', 'phoneno', 'studentphone', 'pupilphone', 'telephone', 'mobile', 'tel', 'gsm'],
        'email' => ['email', 'emailaddress', 'studentemail', 'pupilemail'],
        'address' => ['address', 'homeaddress', 'residentialaddress', 'contactaddress'],
        'admission_date' => ['admissiondate', 'dateofadmission', 'dateadmitted', 'entrydate', 'yearofadmission'],
        'notes' => ['notes', 'note', 'remark', 'remarks', 'comment', 'comments'],

        // The class each student/pupil is in, matched to the school's own
        // classes by the controller.
        'class' => ['class', 'classes', 'classname', 'classarm', 'arm', 'grade', 'form', 'level', 'classlevel', 'currentclass', 'presentclass', 'studentclass', 'pupilclass', 'classofstudent', 'classofpupil', 'classadmitted', 'classadmittedto', 'admittedclass'],

        // Recognised so it counts towards finding the header row, then left
        // out: a serial number is not a student detail.
        'serial' => ['sn', 'sno', 'serialnumber', 'serialno', 'serial', 'no', 'number', 's'],
    ];

    /** Fields shown in the preview, in the order the form shows them. */
    public const FIELDS = [
        'admission_number' => 'Admission No.',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of Birth',
        'house' => 'House',
        'guardian_name' => 'Guardian Name',
        'guardian_phone' => 'Guardian Phone',
        'guardian_email' => 'Guardian Email',
        'phone' => 'Student Phone',
        'email' => 'Student Email',
        'address' => 'Address',
        'admission_date' => 'Admission Date',
        'notes' => 'Notes',
    ];

    private const MAX_LENGTHS = [
        'admission_number' => 50,
        'first_name' => 100,
        'last_name' => 100,
        'house' => 100,
        'guardian_name' => 150,
        'guardian_phone' => 30,
        'guardian_email' => 255,
        'phone' => 30,
        'email' => 255,
        'address' => 2000,
        'notes' => 2000,
    ];

    /**
     * @param  list<list<string>>  $rows  as returned by {@see StudentImportReader}
     * @param  list<string>  $existingAdmissionNumbers  already used at this school
     * @return array{columns: list<string>, ignored: list<string>, rows: list<array{line: int, data: array<string, string|null>, errors: list<string>, warnings: list<string>, file_admission_number: string|null, existing: string|null}>}
     *
     * @throws StudentImportException
     */
    public function parse(array $rows, bool $autoGenerateAdmissionNumbers, array $existingAdmissionNumbers = [], string $nameOrder = self::SURNAME_FIRST): array
    {
        [$headerIndex, $map] = $this->findHeader($rows);

        $mapped = array_unique(array_values($map));
        // "Surname" + "Other Names" is as common on a register as
        // "Last Name" + "First Name"; the other names are the given names.
        $hasSplitNames = in_array('last_name', $mapped, true)
            && (in_array('first_name', $mapped, true) || in_array('middle_name', $mapped, true));

        if (! $hasSplitNames && ! in_array('full_name', $mapped, true)) {
            throw new StudentImportException('We could not find the name columns. Add a "First Name" and "Last Name" (or "Surname") heading, or a single "Name" column.');
        }

        if (! in_array('gender', $mapped, true)) {
            throw new StudentImportException('We could not find a "Gender" (or "Sex") column. Gender is required for every student/pupil.');
        }

        if (! $autoGenerateAdmissionNumbers && ! in_array('admission_number', $mapped, true)) {
            throw new StudentImportException('We could not find an "Admission Number" column. Your school enters admission numbers manually, so each student/pupil needs one. (Turn on automatic admission numbers in Settings to have them generated instead.)');
        }

        $dataRows = array_slice($rows, $headerIndex + 1, null, true);

        if ($dataRows === []) {
            throw new StudentImportException('The file has column headings but no students/pupils listed under them.');
        }

        if (count($dataRows) > self::MAX_ROWS) {
            throw new StudentImportException('A single upload can hold up to '.number_format(self::MAX_ROWS).' students/pupils. Please split the list into smaller files.');
        }

        $existing = array_flip(array_map('mb_strtolower', $existingAdmissionNumbers));
        $seen = [];
        $parsed = [];

        $headerKey = mb_strtolower(implode('|', $rows[$headerIndex]));

        foreach ($dataRows as $index => $cells) {
            // The headings again (a PDF or Word table repeated on each page).
            if (mb_strtolower(implode('|', $cells)) === $headerKey) {
                continue;
            }

            $raw = [];

            foreach ($map as $column => $field) {
                $value = trim((string) ($cells[$column] ?? ''));

                if ($value !== '' && ! isset($raw[$field])) {
                    $raw[$field] = $value;
                }
            }

            // No name and no gender: a sub-heading, a total or a page footer
            // ("Page 1 of 3"), not a student/pupil.
            if (array_intersect_key($raw, array_flip(['first_name', 'last_name', 'full_name', 'middle_name', 'gender'])) === []) {
                continue;
            }

            $row = $this->parseRow($raw, $autoGenerateAdmissionNumbers, $nameOrder);

            $number = $row['data']['admission_number'];

            if ($number !== null) {
                $key = mb_strtolower($number);

                if (isset($existing[$key])) {
                    $row['errors'][] = "Admission number {$number} is already used by another student/pupil at your school.";
                } elseif (isset($seen[$key])) {
                    $row['errors'][] = "Admission number {$number} appears more than once in this file (also on row {$seen[$key]}).";
                } else {
                    $seen[$key] = $index + 1;
                }
            }

            // Kept even when the school generates its own numbers: a register
            // exported from this system carries them, and it is how a student
            // who is already registered is recognised (ExistingStudentMatcher).
            $parsed[] = ['line' => $index + 1, ...$row, 'file_admission_number' => $raw['admission_number'] ?? null, 'file_class' => $raw['class'] ?? null, 'existing' => null];
        }

        if ($parsed === []) {
            throw new StudentImportException('The file has column headings but no students/pupils listed under them.');
        }

        $labels = [];

        foreach ($mapped as $field) {
            if (isset(self::FIELDS[$field])) {
                $labels[] = self::FIELDS[$field];
            } elseif ($field === 'full_name') {
                $labels[] = 'Name';
            } elseif ($field === 'middle_name') {
                $labels[] = 'Middle Name';
            }
        }

        $ignored = [];

        foreach ($map as $column => $field) {
            if ($field === 'class') {
                $ignored[] = ($rows[$headerIndex][$column] ?? 'Class').' (the class you select is used instead)';
            }
        }

        return ['columns' => $labels, 'ignored' => $ignored, 'rows' => $parsed];
    }

    /**
     * The first row, among the first fifteen, that reads like column
     * headings. Registers often start with the school name and a title.
     *
     * @param  list<list<string>>  $rows
     * @return array{0: int, 1: array<int, string>}
     */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 15, true) as $index => $cells) {
            $map = [];

            foreach ($cells as $column => $heading) {
                $field = $this->fieldFor($heading);

                if ($field !== null && ! in_array($field, $map, true)) {
                    $map[$column] = $field;
                }
            }

            $real = array_diff($map, ['serial', 'class']);
            $hasName = array_intersect($real, ['first_name', 'last_name', 'full_name']) !== [];

            if ($hasName && count($real) >= 2) {
                return [$index, $map];
            }
        }

        throw new StudentImportException('We could not find the column headings (for example "First Name", "Last Name", "Gender"). Make sure the first row of your list has headings, or download the template and copy your list into it.');
    }

    private function fieldFor(string $heading): ?string
    {
        // "Date of Birth (DD/MM/YYYY)" is still "Date of Birth".
        $key = $this->headingKey($heading);

        if ($key === '') {
            return null;
        }

        foreach (self::ALIASES as $field => $aliases) {
            if (in_array($key, $aliases, true)) {
                return $field;
            }
        }

        // Longer headings that still clearly say what they are.
        return match (true) {
            str_starts_with($key, 'dateofbirth') => 'date_of_birth',
            str_starts_with($key, 'surname') => 'last_name',
            str_starts_with($key, 'firstname') => 'first_name',
            str_starts_with($key, 'admissionn') => 'admission_number',
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $raw
     * @return array{data: array<string, string|null>, errors: list<string>, warnings: list<string>}
     */
    private function parseRow(array $raw, bool $autoGenerate, string $nameOrder): array
    {
        $errors = [];
        $warnings = [];
        $data = array_fill_keys(array_keys(self::FIELDS), null);

        // Names.
        $first = $raw['first_name'] ?? null;
        $last = $raw['last_name'] ?? null;
        $middle = $raw['middle_name'] ?? null;

        if (($first === null || $last === null) && isset($raw['full_name'])) {
            [$splitFirst, $splitLast] = $this->splitFullName($raw['full_name'], $nameOrder);
            $first ??= $splitFirst;
            $last ??= $splitLast;
        }

        if ($first === null && $middle !== null && $last !== null) {
            [$first, $middle] = [$middle, null];
        }

        if ($middle !== null && $first !== null) {
            $first .= ' '.$middle;
        }

        $data['first_name'] = $first !== null ? $this->nameCase($first) : null;
        $data['last_name'] = $last !== null ? $this->nameCase($last) : null;

        if (blank($data['first_name']) && blank($data['last_name'])) {
            $errors[] = 'Name is missing.';
        } elseif (blank($data['first_name'])) {
            $errors[] = 'First name is missing.';
        } elseif (blank($data['last_name'])) {
            $errors[] = 'Last name (surname) is missing.';
        }

        // Gender.
        $gender = $this->gender($raw['gender'] ?? null);

        if ($gender === null) {
            $errors[] = isset($raw['gender'])
                ? "Gender \"{$raw['gender']}\" is not recognised. Use Male or Female (M/F)."
                : 'Gender is missing.';
        }

        $data['gender'] = $gender?->value;

        // Admission number.
        if ($autoGenerate) {
            if (isset($raw['admission_number'])) {
                $warnings[] = 'Admission number in the file is ignored; one will be generated automatically.';
            }
        } else {
            $data['admission_number'] = $raw['admission_number'] ?? null;

            if ($data['admission_number'] === null) {
                $errors[] = 'Admission number is missing.';
            }
        }

        // Plain text fields.
        foreach (['house', 'guardian_name', 'address', 'notes'] as $field) {
            $data[$field] = $raw[$field] ?? null;
        }

        foreach (['guardian_phone', 'phone'] as $field) {
            $data[$field] = isset($raw[$field]) ? $this->phone($raw[$field]) : null;
        }

        foreach (['guardian_email', 'email'] as $field) {
            if (! isset($raw[$field])) {
                continue;
            }

            if (filter_var($raw[$field], FILTER_VALIDATE_EMAIL)) {
                $data[$field] = mb_strtolower($raw[$field]);
            } else {
                $label = self::FIELDS[$field];
                $warnings[] = "{$label} \"{$raw[$field]}\" is not a valid email address and was left blank.";
            }
        }

        // Dates.
        foreach (['date_of_birth', 'admission_date'] as $field) {
            if (! isset($raw[$field])) {
                continue;
            }

            $date = $this->date($raw[$field]);
            $label = self::FIELDS[$field];

            if ($date === null) {
                $warnings[] = "{$label} \"{$raw[$field]}\" was not understood and was left blank. Use DD/MM/YYYY.";
            } elseif ($field === 'date_of_birth' && ! $date->isPast()) {
                $warnings[] = "{$label} {$date->format('d/m/Y')} is not in the past and was left blank.";
            } else {
                $data[$field] = $date->toDateString();
            }
        }

        foreach (self::MAX_LENGTHS as $field => $max) {
            if ($data[$field] !== null && mb_strlen($data[$field]) > $max) {
                $errors[] = self::FIELDS[$field]." is too long (maximum {$max} characters).";
            }
        }

        return ['data' => $data, 'errors' => $errors, 'warnings' => $warnings];
    }
}
