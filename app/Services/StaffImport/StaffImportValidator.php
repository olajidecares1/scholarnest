<?php

namespace App\Services\StaffImport;

use App\Enums\TeacherAssignmentType;
use App\Models\School;
use App\Models\TeacherAssignment;
use App\Rules\NotDerivedFromIdentity;
use App\Services\Import\ReadsImportValues;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Checks every row of a staff upload against the school, and against the
 * other rows, before anything is saved.
 *
 * Run on the whole list every time: when the file is first read, and again
 * whenever the School Admin corrects or removes a row on the review page,
 * because fixing one row can clear (or cause) a duplicate in another.
 *
 * Each row comes back as one of:
 *
 *   ready               errors === [] and existing === null
 *   already registered  existing !== null: the person is on the staff list
 *                       already (same email, phone, Staff ID or name). Skipped
 *                       and never modified, exactly like the student upload.
 *   needs correcting    errors !== []: missing or invalid details, an unknown
 *                       role or class, a duplicate within the file, a class
 *                       that already has a class teacher, a weak password.
 */
class StaffImportValidator
{
    use ReadsImportValues;

    private const MAX_LENGTHS = [
        'first_name' => 100,
        'last_name' => 100,
        'department' => 100,
        'qualification' => 150,
        'emergency_contact_name' => 150,
        'emergency_contact_phone' => 30,
        'phone' => 30,
        'email' => 255,
        'address' => 2000,
        'notes' => 2000,
    ];

    /** @var array<string, string> normalised class => the school's spelling */
    private array $classes = [];

    /** @var array<string, string> class => "Name (Staff ID)" of its current class teacher */
    private array $classTeachers = [];

    /** @var array<string, array{label: string, staff_id: int}> */
    private array $existingEmails = [];

    /** @var array<string, array{label: string, staff_id: int}> */
    private array $existingPhones = [];

    /** @var array<string, array{label: string, staff_id: int}> */
    private array $existingNames = [];

    /** @var array<string, array{label: string, staff_id: int}> */
    private array $existingStaffIds = [];

    public function __construct(
        private readonly StaffRoleResolver $roles,
    ) {}

    /**
     * @param  list<array{line: int, raw: array<string, string>}>  $rows
     * @param  list<string>  $classNames  the school's classes
     * @return list<array<string, mixed>>
     */
    public function validate(School $school, array $rows, array $classNames): array
    {
        $this->load($school, $classNames);

        $seenEmails = [];
        $seenPhones = [];
        $seenNames = [];
        $claimedClasses = [];
        $checked = [];

        foreach ($rows as $row) {
            $result = $this->row($school, $row);

            // Already on the staff list: nothing else about the row matters.
            if ($result['existing'] === null) {
                $data = $result['data'];

                if ($data['email'] !== null) {
                    $key = mb_strtolower($data['email']);

                    if (isset($seenEmails[$key])) {
                        $result['errors'][] = "Email {$data['email']} is also used on row {$seenEmails[$key]}. Each staff member needs their own email address.";
                    } else {
                        $seenEmails[$key] = $row['line'];
                    }
                }

                if (($phoneKey = self::phoneKey($data['phone'])) !== null) {
                    if (isset($seenPhones[$phoneKey])) {
                        $result['errors'][] = "Phone number {$data['phone']} is also used on row {$seenPhones[$phoneKey]}. Each staff member needs their own number for WhatsApp.";
                    } else {
                        $seenPhones[$phoneKey] = $row['line'];
                    }
                }

                if (($nameKey = self::nameKey($data['first_name'], $data['last_name'])) !== null) {
                    if (isset($seenNames[$nameKey])) {
                        $result['errors'][] = "{$data['first_name']} {$data['last_name']} is listed twice (also on row {$seenNames[$nameKey]}). Remove one of the rows.";
                    } else {
                        $seenNames[$nameKey] = $row['line'];
                    }
                }

                if ($result['kind'] === StaffRoleResolver::CLASS_TEACHER && ($class = $result['classes'][0] ?? null) !== null) {
                    if (isset($claimedClasses[$class])) {
                        $result['errors'][] = "{$class} can only have one class teacher, and row {$claimedClasses[$class]} is already its class teacher.";
                    } else {
                        $claimedClasses[$class] = $row['line'];
                    }
                }
            }

            $checked[] = $result;
        }

        return $checked;
    }

    /**
     * @param  array{line: int, raw: array<string, string>}  $row
     * @return array<string, mixed>
     */
    private function row(School $school, array $row): array
    {
        $raw = $row['raw'];
        $errors = [];
        $warnings = [];

        $data = [
            'first_name' => isset($raw['first_name']) ? $this->nameCase($raw['first_name']) : null,
            'last_name' => isset($raw['last_name']) ? $this->nameCase($raw['last_name']) : null,
            'gender' => null,
            'role' => null,
            'date_of_birth' => null,
            'employment_date' => null,
            'phone' => isset($raw['phone']) ? $this->phone($raw['phone']) : null,
            'email' => null,
            'department' => $raw['department'] ?? null,
            'qualification' => $raw['qualification'] ?? null,
            'address' => $raw['address'] ?? null,
            'emergency_contact_name' => $raw['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => isset($raw['emergency_contact_phone']) ? $this->phone($raw['emergency_contact_phone']) : null,
            'notes' => $raw['notes'] ?? null,
        ];

        // Names.
        if (blank($data['first_name']) && blank($data['last_name'])) {
            $errors[] = 'Name is missing.';
        } elseif (blank($data['first_name'])) {
            $errors[] = 'First name is missing.';
        } elseif (blank($data['last_name'])) {
            $errors[] = 'Last name (surname) is missing.';
        }

        // Already registered? Checked before anything else is reported, so a
        // re-uploaded staff list reads as "already registered", not as errors.
        $existing = $this->existing($raw, $data);

        // Gender.
        $gender = $this->gender($raw['gender'] ?? null);
        $data['gender'] = $gender?->value;

        if ($gender === null) {
            $errors[] = isset($raw['gender'])
                ? "Gender \"{$raw['gender']}\" is not recognised. Use Male or Female (M/F)."
                : 'Gender is missing.';
        }

        // Role, then the class and subjects that go with it.
        $resolved = $this->roles->resolve($raw['role'] ?? null);
        $kind = $resolved['kind'] ?? null;
        $classes = [];
        $subjects = $this->split($raw['subjects'] ?? '');

        if (! isset($raw['role'])) {
            $errors[] = 'Role is missing. Use Class Teacher, Subject Teacher, Teacher, Administrative Staff, Management, Support Staff or Other.';
        } elseif ($resolved === null) {
            $errors[] = "Role \"{$raw['role']}\" is not recognised. Use Class Teacher, Subject Teacher, Teacher, Administrative Staff, Management, Support Staff or Other.";
        } else {
            $data['role'] = $resolved['role']->value;

            // "Mathematics Teacher" says the subject as well as the role.
            if ($kind === StaffRoleResolver::SUBJECT_TEACHER && $subjects === [] && ($subject = $this->subjectFromRole($raw['role'])) !== null) {
                $subjects = [$subject];
            }

            // "Teacher" with subjects is a subject teacher.
            if ($kind === StaffRoleResolver::TEACHER && $subjects !== []) {
                $kind = StaffRoleResolver::SUBJECT_TEACHER;
            }
        }

        $listed = $this->split($raw['class'] ?? '');
        $unknown = [];

        foreach ($listed as $name) {
            $match = $this->classes[self::classKey($name)] ?? null;

            if ($match === null) {
                $unknown[] = $name;
            } elseif (! in_array($match, $classes, true)) {
                $classes[] = $match;
            }
        }

        if ($kind !== null && ! StaffRoleResolver::isTeacher($kind)) {
            if ($listed !== [] || $subjects !== []) {
                $warnings[] = 'Class and subjects are only used for teachers, so they are ignored for '.StaffRoleResolver::KINDS[$kind].'.';
            }

            $classes = [];
            $subjects = [];
        } else {
            foreach ($unknown as $name) {
                $errors[] = $this->unknownClassMessage($name);
            }
        }

        if ($kind === StaffRoleResolver::CLASS_TEACHER) {
            if ($listed === []) {
                $errors[] = 'Class is missing. A class teacher needs the class they take, for example Primary 1 or JSS 1.';
            } elseif (count($listed) > 1) {
                $errors[] = 'A class teacher takes one class, but '.count($listed).' are listed ('.implode(', ', $listed).'). Keep one, or use Subject Teacher.';
                $classes = [];
            } elseif (($class = $classes[0] ?? null) !== null && isset($this->classTeachers[$class])) {
                $errors[] = "{$class} already has a class teacher: {$this->classTeachers[$class]}. Choose another class, or change the class teacher afterwards in Teacher Assignments.";
            }
        }

        if ($kind === StaffRoleResolver::SUBJECT_TEACHER && $subjects !== [] && $classes === [] && $unknown === []) {
            $warnings[] = 'No class is given for '.implode(', ', $subjects).', so subjects can be assigned later in Teacher Assignments.';
        }

        if ($kind === StaffRoleResolver::TEACHER && $listed !== []) {
            $warnings[] = 'The class is ignored because the role is Teacher. Use Class Teacher to make them the class teacher, or add Subjects.';
            $classes = [];
        }

        // Phone: the WhatsApp message goes to it.
        if ($data['phone'] !== null && self::phoneKey($data['phone']) === null) {
            $errors[] = "Phone number \"{$raw['phone']}\" is not a valid phone number.";
        } elseif ($data['phone'] === null) {
            $warnings[] = 'No phone number, so the WhatsApp message with the login details cannot be sent.';
        }

        // Email.
        if (isset($raw['email'])) {
            if (filter_var($raw['email'], FILTER_VALIDATE_EMAIL)) {
                $data['email'] = mb_strtolower($raw['email']);
            } else {
                $errors[] = "Email \"{$raw['email']}\" is not a valid email address.";
            }
        }

        // Dates.
        foreach (['date_of_birth' => 'Date of Birth', 'employment_date' => 'Employment Date'] as $field => $label) {
            if (! isset($raw[$field])) {
                continue;
            }

            $date = $this->date($raw[$field]);

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
                $errors[] = (StaffImportParser::FIELDS[$field] ?? $field)." is too long (maximum {$max} characters).";
            }
        }

        // Password: the school's own, if the file has one, held to the same
        // rules as one typed on a staff member's page. Otherwise one is
        // generated when the account is created.
        if (isset($raw['password'])) {
            $check = Validator::make(['password' => $raw['password']], [
                'password' => ['string', Password::defaults(), new NotDerivedFromIdentity([
                    $school->name,
                    $data['first_name'],
                    $data['last_name'],
                    $data['email'],
                ])],
            ]);

            foreach ($check->errors()->all() as $message) {
                $errors[] = 'Password: '.$message.' Leave the password blank to have one generated.';
            }
        }

        if (isset($raw['staff_number']) && $existing === null) {
            $warnings[] = 'The Staff ID in the file is ignored; a new one is issued automatically.';
        }

        return [
            'line' => $row['line'],
            'raw' => $raw,
            'data' => $data,
            'kind' => $kind,
            'role_label' => $kind !== null ? StaffRoleResolver::KINDS[$kind] : null,
            'classes' => array_values($classes),
            'subjects' => array_values($subjects),
            'password_source' => isset($raw['password']) ? 'file' : 'generated',
            'errors' => $existing === null ? $errors : [],
            'warnings' => $existing === null ? $warnings : [],
            'existing' => $existing['reason'] ?? null,
            'existing_staff_id' => $existing['staff_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, string>  $raw
     * @param  array<string, string|null>  $data
     * @return array{reason: string, staff_id: int}|null
     */
    private function existing(array $raw, array $data): ?array
    {
        $checks = [
            [$this->existingStaffIds, isset($raw['staff_number']) ? mb_strtolower(trim($raw['staff_number'])) : null, 'Staff ID '.($raw['staff_number'] ?? '').' belongs to'],
            [$this->existingEmails, isset($raw['email']) ? mb_strtolower(trim($raw['email'])) : null, 'Email '.($raw['email'] ?? '').' is already used by'],
            [$this->existingPhones, self::phoneKey($data['phone']), 'Phone number '.($data['phone'] ?? '').' is already used by'],
            [$this->existingNames, self::nameKey($data['first_name'], $data['last_name']), 'Already on your staff list:'],
        ];

        foreach ($checks as [$index, $key, $reason]) {
            if ($key !== null && isset($index[$key])) {
                return ['reason' => "{$reason} {$index[$key]['label']}.", 'staff_id' => $index[$key]['staff_id']];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $classNames
     */
    private function load(School $school, array $classNames): void
    {
        $this->classes = [];

        foreach ($classNames as $name) {
            $this->classes[self::classKey($name)] ??= $name;
        }

        $this->classTeachers = TeacherAssignment::query()
            ->where('school_id', $school->id)
            ->where('type', TeacherAssignmentType::ClassTeacher)
            ->with('staff:id,first_name,last_name,staff_number')
            ->get()
            ->filter(fn (TeacherAssignment $assignment) => $assignment->staff !== null)
            ->mapWithKeys(fn (TeacherAssignment $assignment) => [
                $assignment->class_name => "{$assignment->staff->fullName()} ({$assignment->staff->staff_number})",
            ])
            ->all();

        $this->existingEmails = $this->existingPhones = $this->existingNames = $this->existingStaffIds = [];

        $school->staff()
            ->select(['id', 'staff_number', 'first_name', 'last_name', 'email', 'phone'])
            ->orderBy('id')
            ->chunk(1000, function ($staff) {
                foreach ($staff as $member) {
                    $who = ['label' => "{$member->fullName()} ({$member->staff_number})", 'staff_id' => $member->id];

                    $this->existingStaffIds[mb_strtolower((string) $member->staff_number)] ??= $who;

                    if (filled($member->email)) {
                        $this->existingEmails[mb_strtolower(trim($member->email))] ??= $who;
                    }

                    if (($phone = self::phoneKey($member->phone)) !== null) {
                        $this->existingPhones[$phone] ??= $who;
                    }

                    if (($name = self::nameKey($member->first_name, $member->last_name)) !== null) {
                        $this->existingNames[$name] ??= $who;
                    }
                }
            });
    }

    /**
     * The same number however it was written: "0803 123 4567",
     * "+2348031234567" and "8031234567" are one phone.
     */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $phone);

        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return null;
        }

        return strlen($digits) > 10 ? substr($digits, -10) : ltrim($digits, '0');
    }

    private static function nameKey(?string $first, ?string $last): ?string
    {
        if (blank($first) || blank($last)) {
            return null;
        }

        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($first)).'|'.preg_replace('/\s+/u', ' ', trim($last)));
    }

    private static function classKey(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($name));
    }

    /**
     * "JSS 1, JSS 2", "Maths; English", "Basic 1 & Basic 2".
     *
     * @return list<string>
     */
    private function split(string $value): array
    {
        $parts = preg_split('/\s*(?:[,;&|\n]|\band\b)\s*/iu', trim($value)) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn ($part) => $part !== '')));
    }

    private function subjectFromRole(string $role): ?string
    {
        if (! preg_match('/^\s*(.+?)\s+teachers?\s*$/iu', $role, $m)) {
            return null;
        }

        $subject = trim($m[1]);

        return in_array(mb_strtolower($subject), ['subject', 'class', 'form', 'head', 'assistant', 'nursery', 'primary', 'secondary', 'senior', 'junior', 'lead', 'part time', 'part-time', 'full time', 'full-time'], true)
            ? null
            : $subject;
    }

    private function unknownClassMessage(string $name): string
    {
        $key = self::classKey($name);
        $closest = null;
        $distance = PHP_INT_MAX;

        foreach ($this->classes as $candidate => $spelling) {
            $d = levenshtein($key, $candidate);

            if ($d < $distance) {
                [$closest, $distance] = [$spelling, $d];
            }
        }

        $hint = $closest !== null && $distance <= 2 ? " Did you mean {$closest}?" : '';

        return $this->classes === []
            ? "Class \"{$name}\" cannot be used because your school has no classes yet. Create them in Academics first."
            : "Class \"{$name}\" is not one of your school's classes.{$hint}";
    }
}
