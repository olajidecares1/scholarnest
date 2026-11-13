<?php

namespace App\Services\StaffImport;

use App\Services\Import\ReadsImportValues;
use App\Services\StudentImport\StudentImportException;
use App\Services\StudentImport\StudentImportReader;

/**
 * Reads rows of cells (from {@see StudentImportReader},
 * which reads any list, not only students) as staff members.
 *
 * Works out which column is which from the header row, whatever the school
 * called it ("Surname", "Designation", "Class Assigned", "WhatsApp No."), and
 * returns each row as the plain text of the fields it understood. Whether the
 * text is any good (a known role, a real class, an unused email) is decided
 * by {@see StaffImportValidator}, so a row the school corrects on the review
 * page is checked by exactly the same rules as a row read from the file.
 */
class StaffImportParser
{
    use ReadsImportValues;

    public const MAX_ROWS = 500;

    public const SURNAME_FIRST = 'surname_first';

    public const FIRST_NAME_FIRST = 'first_name_first';

    /**
     * Header spellings, compared with everything but letters removed.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        'staff_number' => ['staffid', 'staffno', 'staffnumber', 'staffidnumber', 'employeeid', 'employeeno', 'employeenumber', 'idnumber', 'idno'],
        'first_name' => ['firstname', 'givenname', 'forename', 'fname', 'first', 'christianname'],
        'middle_name' => ['middlename', 'othername', 'othernames', 'middle', 'mname'],
        'last_name' => ['lastname', 'surname', 'familyname', 'lname', 'last'],
        'full_name' => ['name', 'names', 'fullname', 'staffname', 'staffsname', 'nameofstaff', 'namesofstaff', 'teachername', 'teachersname', 'employeename', 'staffpersonnelname'],
        'role' => ['role', 'roles', 'assignedrole', 'staffrole', 'position', 'designation', 'jobtitle', 'job', 'jobrole', 'post', 'responsibility', 'category', 'stafftype', 'staffcategory', 'rank', 'office', 'duty'],
        'class' => ['class', 'classes', 'assignedclass', 'assignedclasses', 'classassigned', 'classesassigned', 'classname', 'classtaught', 'classestaught', 'classarm', 'arm', 'form', 'grade', 'classteacherof'],
        'subjects' => ['subject', 'subjects', 'subjectstaught', 'subjecttaught', 'assignedsubject', 'assignedsubjects', 'subjectassigned', 'subjectsassigned', 'teachingsubject', 'teachingsubjects', 'courses'],
        'gender' => ['gender', 'sex'],
        'phone' => ['phone', 'phonenumber', 'phoneno', 'mobile', 'mobilenumber', 'mobileno', 'tel', 'telephone', 'telephonenumber', 'gsm', 'gsmnumber', 'gsmno', 'whatsapp', 'whatsappnumber', 'whatsappno', 'whatsappphone', 'contact', 'contactnumber', 'contactno', 'phonewhatsapp'],
        'email' => ['email', 'emailaddress', 'mail', 'staffemail'],
        'date_of_birth' => ['dateofbirth', 'dob', 'birthdate', 'birthday', 'datebirth'],
        'password' => ['password', 'temporarypassword', 'temppassword', 'initialpassword', 'loginpassword', 'portalpassword', 'pass', 'pwd'],
        'department' => ['department', 'dept', 'unit', 'section'],
        'qualification' => ['qualification', 'qualifications', 'highestqualification', 'certificate', 'degree'],
        'employment_date' => ['employmentdate', 'dateofemployment', 'dateemployed', 'startdate', 'resumptiondate', 'dateofresumption', 'dateofassumption', 'datejoined', 'joindate', 'hiredate'],
        'address' => ['address', 'homeaddress', 'residentialaddress', 'contactaddress'],
        'emergency_contact_name' => ['emergencycontact', 'emergencycontactname', 'nextofkin', 'nextofkinname', 'nok'],
        'emergency_contact_phone' => ['emergencycontactphone', 'emergencyphone', 'emergencycontactnumber', 'nextofkinphone', 'nextofkinnumber', 'nokphone'],
        'notes' => ['notes', 'note', 'remark', 'remarks', 'comment', 'comments'],

        // Recognised so it counts towards finding the header row, then left out.
        'serial' => ['sn', 'sno', 'serialnumber', 'serialno', 'serial', 'no', 'number', 's'],
    ];

    /** The fields a row can carry, with the label the review shows. */
    public const FIELDS = [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'role' => 'Role',
        'class' => 'Class',
        'subjects' => 'Subjects',
        'gender' => 'Gender',
        'phone' => 'Phone (WhatsApp)',
        'email' => 'Email',
        'date_of_birth' => 'Date of Birth',
        'password' => 'Password',
        'department' => 'Department',
        'qualification' => 'Qualification',
        'employment_date' => 'Employment Date',
        'address' => 'Address',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_phone' => 'Emergency Contact Phone',
        'notes' => 'Notes',
        'staff_number' => 'Staff ID',
    ];

    /**
     * @param  list<list<string>>  $rows
     * @return array{columns: list<string>, rows: list<array{line: int, raw: array<string, string>}>}
     *
     * @throws StudentImportException
     */
    public function parse(array $rows, string $nameOrder = self::SURNAME_FIRST): array
    {
        [$headerIndex, $map] = $this->findHeader($rows);

        $mapped = array_unique(array_values($map));
        $hasSplitNames = in_array('last_name', $mapped, true)
            && (in_array('first_name', $mapped, true) || in_array('middle_name', $mapped, true));

        if (! $hasSplitNames && ! in_array('full_name', $mapped, true)) {
            throw new StudentImportException('We could not find the name columns. Add a "Full Name" column, or "First Name" and "Last Name" (or "Surname") columns.');
        }

        if (! in_array('role', $mapped, true)) {
            throw new StudentImportException('We could not find a "Role" column. Each staff member needs a role, for example Class Teacher, Subject Teacher, Administrative Staff, Management or Support Staff.');
        }

        if (! in_array('gender', $mapped, true)) {
            throw new StudentImportException('We could not find a "Gender" (or "Sex") column. Gender is required for every staff member.');
        }

        $dataRows = array_slice($rows, $headerIndex + 1, null, true);
        $headerKey = mb_strtolower(implode('|', $rows[$headerIndex]));
        $parsed = [];

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

            unset($raw['serial']);

            // No name and no role: a sub-heading, a total or a page footer.
            if (array_intersect_key($raw, array_flip(['first_name', 'last_name', 'full_name', 'middle_name', 'role'])) === []) {
                continue;
            }

            $parsed[] = ['line' => $index + 1, 'raw' => $this->names($raw, $nameOrder)];
        }

        if ($parsed === []) {
            throw new StudentImportException('The file has column headings but no staff listed under them.');
        }

        if (count($parsed) > self::MAX_ROWS) {
            throw new StudentImportException('A single upload can hold up to '.number_format(self::MAX_ROWS).' staff. Please split the list into smaller files.');
        }

        $labels = [];

        foreach ($mapped as $field) {
            $labels[] = match ($field) {
                'full_name' => 'Full Name',
                'middle_name' => 'Middle Name',
                'serial' => null,
                default => self::FIELDS[$field] ?? null,
            };
        }

        return ['columns' => array_values(array_filter($labels)), 'rows' => $parsed];
    }

    /**
     * One "Full Name" column, or First / Middle / Last, becomes First Name and
     * Last Name, which is what the review page lets the school correct.
     *
     * @param  array<string, string>  $raw
     * @return array<string, string>
     */
    private function names(array $raw, string $nameOrder): array
    {
        $first = $raw['first_name'] ?? null;
        $last = $raw['last_name'] ?? null;
        $middle = $raw['middle_name'] ?? null;

        if (($first === null || $last === null) && isset($raw['full_name'])) {
            // "Mrs. Ngozi Okafor": a title is not a name.
            $full = trim((string) preg_replace('/^(mr|mrs|miss|ms|mx|dr|prof|rev|pastor|engr|barr|alhaji|alhaja|chief|sir|madam)\.?\s+/i', '', $raw['full_name']));
            [$splitFirst, $splitLast] = $this->splitFullName($full, $nameOrder);
            $first ??= $splitFirst;
            $last ??= $splitLast;
        }

        if ($first === null && $middle !== null && $last !== null) {
            [$first, $middle] = [$middle, null];
        }

        if ($middle !== null && $first !== null) {
            $first .= ' '.$middle;
        }

        unset($raw['full_name'], $raw['middle_name'], $raw['first_name'], $raw['last_name']);

        return array_filter([
            'first_name' => $first !== null ? $this->nameCase($first) : null,
            'last_name' => $last !== null ? $this->nameCase($last) : null,
            ...$raw,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * The first row, among the first fifteen, that reads like column headings.
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

            $real = array_diff($map, ['serial']);
            $hasName = array_intersect($real, ['first_name', 'last_name', 'full_name']) !== [];

            if ($hasName && count($real) >= 2) {
                return [$index, $map];
            }
        }

        throw new StudentImportException('We could not find the column headings (for example "Full Name", "Role", "Class", "Phone"). Make sure the first row of your list has headings, or download the template and copy your list into it.');
    }

    private function fieldFor(string $heading): ?string
    {
        $key = $this->headingKey($heading);

        if ($key === '') {
            return null;
        }

        foreach (self::ALIASES as $field => $aliases) {
            if (in_array($key, $aliases, true)) {
                return $field;
            }
        }

        return match (true) {
            str_starts_with($key, 'dateofbirth') => 'date_of_birth',
            str_starts_with($key, 'surname') => 'last_name',
            str_starts_with($key, 'firstname') => 'first_name',
            str_starts_with($key, 'fullname') => 'full_name',
            str_starts_with($key, 'emergency') && str_contains($key, 'phone') => 'emergency_contact_phone',
            str_starts_with($key, 'emergency') => 'emergency_contact_name',
            str_starts_with($key, 'role'), str_starts_with($key, 'assignedrole') => 'role',
            str_starts_with($key, 'assignedclass'), str_starts_with($key, 'classassigned') => 'class',
            str_starts_with($key, 'subject') => 'subjects',
            str_contains($key, 'whatsapp'), str_starts_with($key, 'phone') => 'phone',
            str_starts_with($key, 'email') => 'email',
            str_starts_with($key, 'password') => 'password',
            str_starts_with($key, 'gender') => 'gender',
            default => null,
        };
    }
}
