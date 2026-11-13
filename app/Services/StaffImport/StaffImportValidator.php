<?php

namespace App\Services\StaffImport;

use App\Enums\StaffRole;
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
 *
 * Class teachers are worked out from the list, so nobody picks them by hand:
 *
 *   said outright   a "Class Teacher" / "Form Master" role (the class may be
 *                   in the role itself: "Class Teacher, Primary 2"), a
 *                   "Class Teacher Of" column, or Yes in an "Is Class
 *                   Teacher" column. A clash with another class teacher is
 *                   an error to correct.
 *   worked out      a teacher (or a row with no role) with exactly one class.
 *                   If that class has a class teacher already, in the school
 *                   or earlier in the file, they are registered as a teacher
 *                   of it instead, with a note, and nothing is held up.
 *
 * Class names are matched loosely ("Pry 1", "P1", "Primary One" for Primary 1;
 * "JS1A" for JSS 1A; "SS 2" for SSS 2). Someone already on the staff list is
 * never changed, except that a free class the file gives them is assigned.
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

    /** @var array<string, string> loosely written class ("Pry 1", "JS1A") => the school's spelling, when only one class reads that way */
    private array $looseClasses = [];

    /** @var array<string, string> class => "Name (Staff ID)" of its current class teacher */
    private array $classTeachers = [];

    /** @var array<string, int> class => staff id of its current class teacher */
    private array $classTeacherStaff = [];

    /** @var array<int, StaffRole|null> staff id => role, for people already on the staff list */
    private array $staffRoles = [];

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
            }

            $checked[] = $result;
        }

        // One class teacher per class. A class named outright for a teacher
        // is settled first, then the ones worked out from the file, each in
        // the order of the list.
        $claimed = [];

        foreach (['explicit', 'inferred'] as $source) {
            foreach ($checked as $i => $result) {
                $class = $result['class_teacher_of'];

                if ($class === null || $result['class_teacher_source'] !== $source) {
                    continue;
                }

                if ($result['existing'] !== null && ! $this->canAssignExisting($checked[$i], $class)) {
                    continue;
                }

                if (isset($claimed[$class])) {
                    if ($source === 'explicit' && $result['existing'] === null) {
                        $checked[$i]['errors'][] = "{$class} can only have one class teacher, and row {$claimed[$class]} is already its class teacher.";
                    } else {
                        $checked[$i] = $this->notClassTeacher($result, "{$class} is already given to row {$claimed[$class]} as its class teacher.");
                    }

                    continue;
                }

                $claimed[$class] = $result['line'];

                if ($result['existing'] !== null) {
                    $checked[$i]['assign_class'] = $class;
                }
            }
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
        $roleText = $raw['role'] ?? null;
        $resolved = $this->roles->resolve($roleText);
        $kind = $resolved['kind'] ?? null;
        $subjects = $this->split($raw['subjects'] ?? '');
        $listed = $this->split($raw['class'] ?? '');
        [$flag, $classTeacherListed] = $this->classTeacherCell($raw['class_teacher'] ?? null);

        if ($roleText === null) {
            // A teachers' list often has no Role column at all: a class or
            // subjects beside a name are enough to say this is a teacher (the
            // review shows the role it was understood as).
            if ($listed !== [] || $subjects !== [] || $classTeacherListed !== [] || $flag === true) {
                $kind = StaffRoleResolver::TEACHER;
            } else {
                $errors[] = 'Role is missing. Use Class Teacher, Subject Teacher, Teacher, Administrative Staff, Management, Support Staff or Other.';
            }
        } elseif ($resolved === null) {
            $errors[] = "Role \"{$roleText}\" is not recognised. Use Class Teacher, Subject Teacher, Teacher, Administrative Staff, Management, Support Staff or Other.";
        }

        if ($kind !== null) {
            $data['role'] = StaffRoleResolver::roleFor($kind)->value;

            // "Mathematics Teacher" says the subject as well as the role.
            if ($kind === StaffRoleResolver::SUBJECT_TEACHER && $subjects === [] && $roleText !== null && ($subject = $this->subjectFromRole($roleText)) !== null) {
                $subjects = [$subject];
            }
        }

        $unknown = [];
        $classes = $this->matchClasses($listed, $unknown);
        $classTeacherClasses = $this->matchClasses($classTeacherListed, $unknown);
        $classTeacherOf = null;
        $source = null;

        if ($kind !== null && ! StaffRoleResolver::isTeacher($kind)) {
            if ($listed !== [] || $subjects !== [] || $classTeacherListed !== [] || $flag === true) {
                $warnings[] = 'Class, class teacher and subjects are only used for teachers, so they are ignored for '.StaffRoleResolver::KINDS[$kind].'.';
            }

            $classes = [];
            $subjects = [];
        } elseif ($kind !== null) {
            foreach ($unknown as $name) {
                $errors[] = $this->unknownClassMessage($name);
            }

            // The class teacher, said in so many words: the role ("Class
            // Teacher", "Form Master, JSS 1"), a "Class Teacher Of" column
            // naming the class, or a Yes in an "Is Class Teacher" column.
            $explicit = $flag !== false && ($kind === StaffRoleResolver::CLASS_TEACHER || $classTeacherListed !== [] || $flag === true);
            $fromRole = $explicit && $classTeacherListed === [] && $listed === [] ? $this->classInText((string) $roleText) : null;

            if ($flag === false && $kind === StaffRoleResolver::CLASS_TEACHER) {
                $kind = StaffRoleResolver::TEACHER;
                $warnings[] = 'The Class Teacher column says No, so they are not made a class teacher.';
            }

            if ($explicit) {
                $kind = StaffRoleResolver::CLASS_TEACHER;
                [$named, $matched] = match (true) {
                    $classTeacherListed !== [] => [$classTeacherListed, $classTeacherClasses],
                    $fromRole !== null => [[$fromRole], [$fromRole]],
                    default => [$listed, $classes],
                };

                if ($named === []) {
                    $errors[] = 'Class is missing. A class teacher needs the class they take, for example Primary 1 or JSS 1.';
                } elseif (count($named) > 1) {
                    $errors[] = 'A class teacher takes one class, but '.count($named).' are listed ('.implode(', ', $named).'). Keep one, or use Subject Teacher.';

                    if ($classTeacherListed === []) {
                        $classes = [];
                    }
                } elseif ($matched !== []) {
                    $classTeacherOf = $matched[0];
                    $source = 'explicit';

                    if (isset($this->classTeachers[$classTeacherOf]) && $this->classTeacherStaff[$classTeacherOf] !== ($existing['staff_id'] ?? null)) {
                        $errors[] = "{$classTeacherOf} already has a class teacher: {$this->classTeachers[$classTeacherOf]}. Choose another class, or change the class teacher afterwards in Teacher Assignments.";
                    }
                }
            } elseif ($kind === StaffRoleResolver::TEACHER && $flag !== false && count($listed) === 1 && $classes !== [] && $this->roles->canTakeClass($roleText)) {
                // Worked out, not said: a teacher with one class is that
                // class's teacher, unless the class has one already.
                $kind = StaffRoleResolver::CLASS_TEACHER;
                $classTeacherOf = $classes[0];
                $source = 'inferred';
            }

            // Subjects are taught in the classes listed, or else in the
            // class they are class teacher of.
            if ($classTeacherOf !== null && $classes === []) {
                $classes = [$classTeacherOf];
            }

            if ($kind === StaffRoleResolver::TEACHER && $subjects !== []) {
                $kind = StaffRoleResolver::SUBJECT_TEACHER;
            }

            if ($kind === StaffRoleResolver::SUBJECT_TEACHER && $subjects !== [] && $classes === [] && $unknown === []) {
                $warnings[] = 'No class is given for '.implode(', ', $subjects).', so subjects can be assigned later in Teacher Assignments.';
            }

            if ($kind === StaffRoleResolver::TEACHER && $classes !== []) {
                $warnings[] = count($listed) > 1
                    ? 'Several classes are listed but no subjects, so the classes are ignored. Add Subjects, or keep one class to make them its class teacher.'
                    : 'The class is ignored. Use Class Teacher to make them its class teacher, or add Subjects.';
                $classes = [];
            }
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

        $result = [
            'line' => $row['line'],
            'raw' => $raw,
            'data' => $data,
            'kind' => $kind,
            'role_label' => $kind !== null ? StaffRoleResolver::KINDS[$kind] : null,
            'classes' => array_values($classes),
            'subjects' => array_values($subjects),
            'class_teacher_of' => $classTeacherOf,
            'class_teacher_source' => $source,
            'password_source' => isset($raw['password']) ? 'file' : 'generated',
            'errors' => $existing === null ? $errors : [],
            'warnings' => $existing === null ? $warnings : [],
            'existing' => $existing['reason'] ?? null,
            'existing_staff_id' => $existing['staff_id'] ?? null,
            'assign_class' => null,
            'existing_notes' => [],
        ];

        // Worked out from the file, and the class has a class teacher
        // already: they are still registered, just not as its class teacher.
        if ($source === 'inferred' && isset($this->classTeachers[$classTeacherOf]) && $this->classTeacherStaff[$classTeacherOf] !== ($existing['staff_id'] ?? null)) {
            $result = $this->notClassTeacher($result, "{$classTeacherOf} already has a class teacher ({$this->classTeachers[$classTeacherOf]}).");
        }

        return $result;
    }

    /**
     * Someone already on the staff list who the file makes a class teacher:
     * their record is left as it is, but a free class is still assigned to
     * them, so a school that added its teachers one at a time can hand out
     * every class from one list. Notes on the row say why when it is not.
     *
     * @param  array<string, mixed>  $result
     */
    private function canAssignExisting(array &$result, string $class): bool
    {
        $staffId = $result['existing_staff_id'];
        $current = $this->classTeacherStaff[$class] ?? null;
        $theirs = array_keys($this->classTeacherStaff, $staffId, true);

        $note = match (true) {
            $current === $staffId => "Already the class teacher of {$class}.",
            ($this->staffRoles[$staffId] ?? null) !== StaffRole::Teacher => "Not made class teacher of {$class}, because their role on the staff list is not Teacher.",
            $current !== null => "Not made class teacher of {$class}, because it already has one: {$this->classTeachers[$class]}.",
            $theirs !== [] => 'Not made class teacher of '.$class.', because they are already class teacher of '.implode(', ', $theirs).'. Change it in Teacher Assignments if they have moved.',
            default => null,
        };

        if ($note !== null) {
            $result['existing_notes'][] = $note;
            $result['class_teacher_of'] = null;
            $result['class_teacher_source'] = null;

            return false;
        }

        return true;
    }

    /**
     * A teacher who looked like a class teacher, but whose class is already
     * taken: registered as a subject teacher of that class (with the subjects
     * in the file) or as a teacher, with a note saying why.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function notClassTeacher(array $result, string $reason): array
    {
        $class = $result['class_teacher_of'];
        $subjects = $result['subjects'];

        $result['kind'] = $subjects !== [] ? StaffRoleResolver::SUBJECT_TEACHER : StaffRoleResolver::TEACHER;
        $result['role_label'] = StaffRoleResolver::KINDS[$result['kind']];
        $result['class_teacher_of'] = null;
        $result['class_teacher_source'] = null;

        if ($subjects === []) {
            $result['classes'] = [];
        }

        $outcome = $subjects !== []
            ? 'They are registered as a Subject Teacher of '.implode(', ', $subjects)." in {$class}."
            : 'They are registered as a Teacher; give them a class later in Teacher Assignments if needed.';

        if ($result['existing'] === null) {
            $result['warnings'][] = "{$reason} {$outcome}";
        } else {
            $result['existing_notes'][] = "{$reason} Not made its class teacher.";
        }

        return $result;
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
        $this->looseClasses = [];
        $loose = [];

        foreach ($classNames as $name) {
            $this->classes[self::classKey($name)] ??= $name;
            $loose[self::looseClassKey($name)][$name] = true;
        }

        foreach ($loose as $key => $names) {
            if (count($names) === 1) {
                $this->looseClasses[$key] = array_key_first($names);
            }
        }

        $assignments = TeacherAssignment::query()
            ->where('school_id', $school->id)
            ->where('type', TeacherAssignmentType::ClassTeacher)
            ->with('staff:id,first_name,last_name,staff_number')
            ->get()
            ->filter(fn (TeacherAssignment $assignment) => $assignment->staff !== null);

        $this->classTeachers = $assignments
            ->mapWithKeys(fn (TeacherAssignment $assignment) => [
                $assignment->class_name => "{$assignment->staff->fullName()} ({$assignment->staff->staff_number})",
            ])
            ->all();

        $this->classTeacherStaff = $assignments
            ->mapWithKeys(fn (TeacherAssignment $assignment) => [$assignment->class_name => $assignment->staff_id])
            ->all();

        $this->staffRoles = [];

        $this->existingEmails = $this->existingPhones = $this->existingNames = $this->existingStaffIds = [];

        $school->staff()
            ->select(['id', 'staff_number', 'first_name', 'last_name', 'email', 'phone', 'role'])
            ->orderBy('id')
            ->chunk(1000, function ($staff) {
                foreach ($staff as $member) {
                    $this->staffRoles[$member->id] = $member->role;

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

    /**
     * Each class name written in the file, as the school's own spelling.
     * Names that are not the school's classes are added to $unknown.
     *
     * @param  list<string>  $names
     * @param  list<string>  $unknown
     * @return list<string>
     */
    private function matchClasses(array $names, array &$unknown): array
    {
        $matched = [];

        foreach ($names as $name) {
            $match = $this->classes[self::classKey($name)] ?? $this->looseClasses[self::looseClassKey($name)] ?? null;

            if ($match === null) {
                $unknown[] = $name;
            } elseif (! in_array($match, $matched, true)) {
                $matched[] = $match;
            }
        }

        return $matched;
    }

    /**
     * The one class named inside a role such as "Class Teacher (Primary 2)"
     * or "Form Master, JSS 1A". The longest name wins, so "JSS 1A" is found
     * rather than "JSS 1".
     */
    private function classInText(string $text): ?string
    {
        $haystack = self::looseClassKey($text);
        $found = null;

        foreach (array_unique([...array_values($this->classes), ...array_values($this->looseClasses)]) as $class) {
            $needle = self::looseClassKey($class);
            $at = strlen($needle) < 3 ? false : strpos($haystack, $needle);

            // Not "Primary 1" inside "Primary 12".
            if ($at === false || ctype_digit(substr($haystack, $at + strlen($needle), 1))) {
                continue;
            }

            if ($found === null || strlen($needle) > strlen(self::looseClassKey($found))) {
                $found = $class;
            }
        }

        return $found;
    }

    /**
     * A "Class Teacher" column holds either the class ("Primary 2") or a
     * plain Yes / No.
     *
     * @return array{0: bool|null, 1: list<string>} [yes/no, classes named]
     */
    private function classTeacherCell(?string $value): array
    {
        $value = trim((string) $value);
        $key = (string) preg_replace('/[^a-z0-9✓✔]/u', '', mb_strtolower($value));

        return match (true) {
            $key === '' => [null, []],
            in_array($key, ['yes', 'y', 'true', '1', 'x', '✓', '✔', 'classteacher', 'ct'], true) => [true, []],
            in_array($key, ['no', 'n', 'false', '0', 'none', 'nil', 'na'], true) => [false, []],
            default => [null, $this->split($value)],
        };
    }

    /**
     * How a class is often written, reduced to one form: "Pry 1", "P1",
     * "Primary One" and "Primary I" are all "primary1"; "JS1", "J.S.S 1" and
     * "Junior Secondary 1" are "jss1"; "SS 2" and "Senior Secondary 2" are
     * "sss2".
     */
    private static function looseClassKey(string $name): string
    {
        $text = mb_strtolower($name);
        $text = (string) preg_replace('/(?<=[a-z])\.(?=[a-z])/', '', $text);
        $text = (string) preg_replace('/junior\s+secondary(\s+school)?/', 'jss', $text);
        $text = (string) preg_replace('/senior\s+secondary(\s+school)?/', 'sss', $text);
        $text = (string) preg_replace('/([a-z])(\d)/', '$1 $2', $text);
        $text = (string) preg_replace('/(\d)([a-z])/', '$1 $2', $text);

        $tokens = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $words = [
            'pry' => 'primary', 'pri' => 'primary', 'prim' => 'primary', 'p' => 'primary',
            'js' => 'jss', 'jhs' => 'jss', 'jsss' => 'jss',
            'ss' => 'sss', 'shs' => 'sss', 's' => 'sss',
            'kindergarten' => 'kg', 'kindergarden' => 'kg', 'k' => 'kg',
            'nur' => 'nursery', 'n' => 'nursery',
            'yr' => 'year', 'gr' => 'grade', 'bas' => 'basic',
        ];
        $numbers = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12];
        $roman = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6];
        $levels = ['primary', 'jss', 'sss', 'kg', 'nursery', 'year', 'grade', 'basic', 'class', 'form', 'reception'];

        $out = [];

        foreach ($tokens as $i => $token) {
            $previous = $out[$i - 1] ?? null;

            // A lone letter ("P", "S") only stands for a level before a number.
            if (isset($words[$token]) && (strlen($token) > 1 || ctype_digit($tokens[$i + 1] ?? ''))) {
                $token = $words[$token];
            } elseif (isset($numbers[$token])) {
                $token = (string) $numbers[$token];
            } elseif (isset($roman[$token]) && in_array($previous, $levels, true)) {
                $token = (string) $roman[$token];
            }

            $out[$i] = $token;
        }

        return implode('', $out);
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
