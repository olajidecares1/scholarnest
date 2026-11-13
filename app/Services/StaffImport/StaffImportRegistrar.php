<?php

namespace App\Services\StaffImport;

use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Staff;
use App\Models\TeacherAssignment;
use App\Services\CredentialShareMessage;
use App\Services\IdentifierGenerator;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Support\TemporaryPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Step 2 of a bulk staff upload: everything that happens once the School Admin
 * confirms.
 *
 * For every row that is ready, in one go and with nothing left to do by hand:
 *
 *   1. the account is created, with a Staff ID from the school's sequence;
 *   2. it gets its password, the one in the file or a generated one;
 *   3. its role is set, and a Class Teacher is made class teacher of their
 *      class, a Subject Teacher given their subjects, which together are what
 *      the Staff Portal's permissions follow;
 *   4. the person is sent their login details on WhatsApp.
 *
 * The result is the registration summary: what was created, what was not and
 * why, and which WhatsApp messages went.
 */
class StaffImportRegistrar
{
    public function __construct(
        private readonly IdentifierGenerator $identifiers,
        private readonly CredentialShareMessage $messages,
        private readonly WhatsAppGateway $whatsApp,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows  as returned by {@see StaffImportValidator}, freshly checked
     * @return array{summary: array<string, mixed>, shares: array<string, array{password: string, url: string}>}
     */
    public function register(School $school, array $rows, string $fileName): array
    {
        $created = [];
        $failed = [];

        $teacherLimit = $school->teacherAccountLimit();
        $teacherRoom = $teacherLimit === null
            ? null
            : max(0, $teacherLimit - $school->staff()->where('role', StaffRole::Teacher)->where('is_active', true)->count());

        foreach ($rows as $row) {
            $name = trim(($row['data']['first_name'] ?? '').' '.($row['data']['last_name'] ?? '')) ?: 'Unnamed';

            if ($row['existing'] !== null) {
                $failed[] = ['line' => $row['line'], 'name' => $name, 'reasons' => ['Already registered. '.$row['existing'].' The existing record was left unchanged.']];

                continue;
            }

            if ($row['errors'] !== []) {
                $failed[] = ['line' => $row['line'], 'name' => $name, 'reasons' => $row['errors']];

                continue;
            }

            $isTeacher = StaffRoleResolver::isTeacher((string) $row['kind']);

            if ($isTeacher && $teacherRoom !== null && $teacherRoom <= 0) {
                $failed[] = ['line' => $row['line'], 'name' => $name, 'reasons' => ["Your plan allows {$teacherLimit} teacher accounts and they are all in use. Upgrade your subscription to add more teachers."]];

                continue;
            }

            try {
                $created[] = $this->create($school, $row);
            } catch (Throwable $e) {
                report($e);
                $failed[] = ['line' => $row['line'], 'name' => $name, 'reasons' => ['The account could not be created. Please add this staff member with Add Staff.']];

                continue;
            }

            if ($isTeacher && $teacherRoom !== null) {
                $teacherRoom--;
            }
        }

        // Accounts first, every one of them, then the messages: a slow or
        // failing WhatsApp never leaves a list half registered.
        $whatsAppReady = $this->whatsApp->isConfigured();
        $shares = [];

        foreach ($created as $i => $person) {
            $password = $person['password'];
            unset($created[$i]['password']);

            $share = $this->messages->build(
                $school,
                $person['name'],
                'Staff ID',
                $person['staff_number'],
                $password,
                $person['login_url'],
                $person['phone'],
                $this->intro($school, $person),
            );

            $shares[$person['uuid']] = ['password' => $password, 'url' => $share['url']];

            if ($share['phone'] === null) {
                $created[$i]['whatsapp'] = 'no_phone';
                $created[$i]['whatsapp_error'] = 'No phone number.';

                continue;
            }

            if (! $whatsAppReady) {
                $created[$i]['whatsapp'] = 'not_configured';
                $created[$i]['whatsapp_error'] = 'Automatic WhatsApp sending is not set up yet.';

                continue;
            }

            $delivery = $this->whatsApp->send($share['phone'], $share['message'], [
                $person['name'],
                $school->name,
                $person['staff_number'],
                $password,
                $person['login_url'],
            ]);

            $created[$i]['whatsapp'] = $delivery->sent ? 'sent' : 'failed';
            $created[$i]['whatsapp_error'] = $delivery->error;
        }

        $created = array_values($created);
        $classTeachers = count(array_filter($created, fn ($p) => $p['kind'] === StaffRoleResolver::CLASS_TEACHER));
        $byRole = [];

        foreach ($created as $person) {
            if ($person['kind'] !== StaffRoleResolver::CLASS_TEACHER) {
                $byRole[$person['role_label']] = ($byRole[$person['role_label']] ?? 0) + 1;
            }
        }

        $whatsApp = [
            'configured' => $whatsAppReady,
            'sent' => count(array_filter($created, fn ($p) => $p['whatsapp'] === 'sent')),
            'failed' => count(array_filter($created, fn ($p) => $p['whatsapp'] === 'failed')),
            'not_sent' => count(array_filter($created, fn ($p) => in_array($p['whatsapp'], ['no_phone', 'not_configured'], true))),
        ];

        AuditLog::record(
            'staff.imported',
            'Registered '.count($created)." staff member(s) from {$fileName}"
                .($classTeachers > 0 ? ", {$classTeachers} as class teacher(s)" : '')
                .'.'.($failed !== [] ? ' '.count($failed).' not registered.' : '')
                .($whatsAppReady ? " WhatsApp: {$whatsApp['sent']} sent, {$whatsApp['failed']} failed." : ''),
            $school,
        );

        return [
            'summary' => [
                'file_name' => $fileName,
                'total' => count($rows),
                'registered' => count($created),
                'class_teachers' => $classTeachers,
                'other_roles' => $byRole,
                'created' => $created,
                'failed' => $failed,
                'whatsapp' => $whatsApp,
                'finished_at' => now()->toIso8601String(),
            ],
            'shares' => $shares,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function create(School $school, array $row): array
    {
        $password = $row['password_plain'] ?? null;
        $generated = blank($password);
        $password = $generated ? TemporaryPassword::generate() : $password;
        $notes = [];

        $member = DB::transaction(function () use ($school, $row, $password, &$notes) {
            // Locked and reserved in one transaction, as when adding one by
            // hand: a freed Staff ID is reused, never handed out twice.
            $member = $school->staff()->create([
                ...array_filter($row['data'], fn ($value) => $value !== null),
                'staff_number' => $this->identifiers->nextStaffId($school),
                'password' => Hash::make($password),
                // As for every login the School Admin issues: staff do not set
                // their own passwords, so nothing forces a change on sign-in.
                'must_change_password' => false,
                'is_active' => true,
            ]);

            foreach ($row['classes'] as $class) {
                if ($row['kind'] === StaffRoleResolver::CLASS_TEACHER) {
                    // Never takes a class from a teacher who already has it;
                    // checked on the review page, and again here in case it
                    // changed while the page was open.
                    $taken = TeacherAssignment::query()
                        ->where('school_id', $school->id)
                        ->where('type', TeacherAssignmentType::ClassTeacher)
                        ->where('class_name', $class)
                        ->lockForUpdate()
                        ->exists();

                    if ($taken) {
                        $notes[] = "{$class} was given a class teacher while this list was open, so this staff member was not made its class teacher.";
                    } else {
                        TeacherAssignment::create([
                            'school_id' => $school->id,
                            'staff_id' => $member->id,
                            'type' => TeacherAssignmentType::ClassTeacher,
                            'class_name' => $class,
                        ]);
                    }
                }

                foreach ($row['subjects'] as $subject) {
                    TeacherAssignment::firstOrCreate([
                        'school_id' => $school->id,
                        'staff_id' => $member->id,
                        'type' => TeacherAssignmentType::SubjectTeacher,
                        'class_name' => $class,
                        'subject' => mb_substr($subject, 0, 100),
                    ]);
                }
            }

            return $member;
        });

        AuditLog::record('portal.credentials.issued', "Login details issued by bulk upload (Staff ID {$member->staff_number}).", $member);

        return [
            'uuid' => $member->uuid,
            'line' => $row['line'],
            'name' => $member->fullName(),
            'staff_number' => $member->staff_number,
            'kind' => $row['kind'],
            'role_label' => $row['role_label'],
            'classes' => $row['classes'],
            'subjects' => $row['subjects'],
            'phone' => $member->phone,
            'email' => $member->email,
            'password_source' => $generated ? 'generated' : 'file',
            'password' => $password,
            'login_url' => $school->portalLoginUrl('staff'),
            'notes' => $notes,
            'whatsapp' => null,
            'whatsapp_error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private function intro(School $school, array $person): string
    {
        $role = $person['role_label'];

        if ($person['kind'] === StaffRoleResolver::CLASS_TEACHER && $person['classes'] !== []) {
            $role .= ' of '.$person['classes'][0];
        }

        return "Hello {$person['name']}, your staff account at {$school->name} has been created. Your role: {$role}.\n\n"
            .'To sign in, open the link below and enter your Staff ID and password. You will see your classes and tools as soon as you sign in.';
    }
}
