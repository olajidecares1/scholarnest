<?php

namespace App\Services\StaffImport;

use App\Enums\StaffRole;

/**
 * Reads the Role a school typed for a staff member.
 *
 * Schools describe their staff in their own words ("Class Teacher", "Form
 * Master", "Bursar", "Vice Principal", "Driver"). Each is understood as one of
 * the roles the system has, plus, for teachers, whether they take a class:
 *
 *   Class Teacher    a Teacher, made Class Teacher of the class in the file
 *   Subject Teacher  a Teacher, given the subjects in the file (if any)
 *   Teacher          a Teacher with no assignment yet
 *   anything else    Administrator, Management, Support Staff or Other
 *
 * What a staff member can do in the Staff Portal follows from exactly these
 * two things, their role (teaching tools are for Teachers only) and their
 * teacher assignments (a Class Teacher keeps that class's register and
 * report cards), so resolving the role is what configures their permissions.
 */
class StaffRoleResolver
{
    public const CLASS_TEACHER = 'class_teacher';

    public const SUBJECT_TEACHER = 'subject_teacher';

    public const TEACHER = 'teacher';

    /**
     * Labels offered in the template's drop-down and on the upload page, in
     * the order a school thinks of them.
     *
     * @var array<string, string> kind => label
     */
    public const KINDS = [
        self::CLASS_TEACHER => 'Class Teacher',
        self::SUBJECT_TEACHER => 'Subject Teacher',
        self::TEACHER => 'Teacher',
        'administrator' => 'Administrative Staff',
        'management' => 'Management',
        'support_staff' => 'Support Staff',
        'other' => 'Other',
    ];

    /**
     * Wording, letters only, lower case => kind.
     *
     * @var array<string, string>
     */
    private const WORDS = [
        // Class teachers.
        'classteacher' => self::CLASS_TEACHER,
        'classteachers' => self::CLASS_TEACHER,
        'formteacher' => self::CLASS_TEACHER,
        'formmaster' => self::CLASS_TEACHER,
        'formmistress' => self::CLASS_TEACHER,
        'classmaster' => self::CLASS_TEACHER,
        'classmistress' => self::CLASS_TEACHER,
        'classadviser' => self::CLASS_TEACHER,
        'classadvisor' => self::CLASS_TEACHER,
        'ct' => self::CLASS_TEACHER,

        // Subject teachers.
        'subjectteacher' => self::SUBJECT_TEACHER,
        'subjectteachers' => self::SUBJECT_TEACHER,
        'subjectmaster' => self::SUBJECT_TEACHER,
        'subjectmistress' => self::SUBJECT_TEACHER,
        'st' => self::SUBJECT_TEACHER,

        // Teachers, no assignment.
        'teacher' => self::TEACHER,
        'teachers' => self::TEACHER,
        'teaching' => self::TEACHER,
        'teachingstaff' => self::TEACHER,
        'tutor' => self::TEACHER,
        'instructor' => self::TEACHER,
        'lecturer' => self::TEACHER,
        'educator' => self::TEACHER,
        'caregiver' => self::TEACHER,
        'nurseryteacher' => self::TEACHER,
        'assistantteacher' => self::TEACHER,
        'teachingassistant' => self::TEACHER,

        // Administrative staff.
        'administrator' => 'administrator',
        'administrative' => 'administrator',
        'administrativestaff' => 'administrator',
        'administration' => 'administrator',
        'admin' => 'administrator',
        'adminstaff' => 'administrator',
        'adminofficer' => 'administrator',
        'administrativeofficer' => 'administrator',
        'bursar' => 'administrator',
        'accountant' => 'administrator',
        'accounts' => 'administrator',
        'accountofficer' => 'administrator',
        'accountsofficer' => 'administrator',
        'cashier' => 'administrator',
        'secretary' => 'administrator',
        'clerk' => 'administrator',
        'receptionist' => 'administrator',
        'frontdesk' => 'administrator',
        'frontdeskofficer' => 'administrator',
        'registrar' => 'administrator',
        'examofficer' => 'administrator',
        'examsofficer' => 'administrator',
        'ictofficer' => 'administrator',
        'itofficer' => 'administrator',
        'storekeeper' => 'administrator',
        'librarian' => 'administrator',
        'officer' => 'administrator',

        // Management.
        'management' => 'management',
        'manager' => 'management',
        'principal' => 'management',
        'viceprincipal' => 'management',
        'viceprincipaladmin' => 'management',
        'viceprincipalacademics' => 'management',
        'headteacher' => 'management',
        'headmaster' => 'management',
        'headmistress' => 'management',
        'assistantheadteacher' => 'management',
        'deputyheadteacher' => 'management',
        'proprietor' => 'management',
        'proprietress' => 'management',
        'director' => 'management',
        'schooladministrator' => 'management',
        'rector' => 'management',
        'provost' => 'management',
        'coordinator' => 'management',
        'headofschool' => 'management',

        // Support staff.
        'supportstaff' => 'support_staff',
        'support' => 'support_staff',
        'nonteaching' => 'support_staff',
        'nonteachingstaff' => 'support_staff',
        'driver' => 'support_staff',
        'cleaner' => 'support_staff',
        'security' => 'support_staff',
        'securityguard' => 'support_staff',
        'securityman' => 'support_staff',
        'gateman' => 'support_staff',
        'porter' => 'support_staff',
        'cook' => 'support_staff',
        'chef' => 'support_staff',
        'kitchenstaff' => 'support_staff',
        'nanny' => 'support_staff',
        'matron' => 'support_staff',
        'nurse' => 'support_staff',
        'schoolnurse' => 'support_staff',
        'gardener' => 'support_staff',
        'messenger' => 'support_staff',
        'attendant' => 'support_staff',
        'labattendant' => 'support_staff',
        'laboratoryattendant' => 'support_staff',
        'technician' => 'support_staff',
        'electrician' => 'support_staff',
        'maintenance' => 'support_staff',
        'janitor' => 'support_staff',
        'hostelstaff' => 'support_staff',

        'other' => 'other',
        'others' => 'other',
    ];

    /**
     * @return array{kind: string, role: StaffRole, label: string}|null null when the wording is not recognised
     */
    public function resolve(?string $value): ?array
    {
        $key = (string) preg_replace('/[^a-z]/', '', mb_strtolower((string) $value));

        if ($key === '') {
            return null;
        }

        $kind = self::WORDS[$key] ?? $this->byLabel($key) ?? $this->byPattern($key);

        if ($kind === null) {
            return null;
        }

        return ['kind' => $kind, 'role' => self::roleFor($kind), 'label' => self::KINDS[$kind]];
    }

    public static function roleFor(string $kind): StaffRole
    {
        return match ($kind) {
            self::CLASS_TEACHER, self::SUBJECT_TEACHER, self::TEACHER => StaffRole::Teacher,
            'administrator' => StaffRole::Administrator,
            'management' => StaffRole::Management,
            'support_staff' => StaffRole::SupportStaff,
            default => StaffRole::Other,
        };
    }

    /**
     * Whether a teacher described this way can be made class teacher of the
     * one class listed for them without the file saying so in as many words.
     * A plain "Teacher" (or no role at all) with one class is that class's
     * teacher; an assistant or caregiver with one class is not.
     */
    public function canTakeClass(?string $value): bool
    {
        $key = (string) preg_replace('/[^a-z]/', '', mb_strtolower((string) $value));

        return ! str_contains($key, 'assistant')
            && ! str_contains($key, 'caregiver')
            && ! str_contains($key, 'aide');
    }

    public static function isTeacher(string $kind): bool
    {
        return in_array($kind, [self::CLASS_TEACHER, self::SUBJECT_TEACHER, self::TEACHER], true);
    }

    private function byLabel(string $key): ?string
    {
        foreach (self::KINDS as $kind => $label) {
            if ((string) preg_replace('/[^a-z]/', '', mb_strtolower($label)) === $key) {
                return $kind;
            }
        }

        foreach (StaffRole::cases() as $role) {
            if ((string) preg_replace('/[^a-z]/', '', mb_strtolower($role->label())) === $key) {
                return $role->value;
            }
        }

        return null;
    }

    /**
     * Longer descriptions that still say plainly what they are: "Class
     * Teacher (Primary 2)", "Mathematics Teacher", "Head of Accounts".
     */
    private function byPattern(string $key): ?string
    {
        return match (true) {
            // "Class Teacher (Primary 2)", "Primary 2 Class Teacher", "Form Master, JSS 1".
            (bool) preg_match('/(class|form)(teacher|master|mistress|adviser|advisor)/', $key) => self::CLASS_TEACHER,
            str_starts_with($key, 'subjectteacher') => self::SUBJECT_TEACHER,
            str_contains($key, 'principal'), str_contains($key, 'headteacher'), str_contains($key, 'proprieto') => 'management',
            str_contains($key, 'nonteaching') => 'support_staff',
            str_ends_with($key, 'teacher'), str_ends_with($key, 'teachers') => self::SUBJECT_TEACHER,
            str_contains($key, 'admin'), str_contains($key, 'account'), str_contains($key, 'bursar'), str_contains($key, 'secretar') => 'administrator',
            default => null,
        };
    }
}
