<?php

namespace App\Http\Controllers\Staff;

use App\Enums\RegistrationSource;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Services\IdentifierGenerator;
use App\Services\StudentImport\ExistingStudentMatcher;
use App\Services\StudentImport\StudentReRegistration;
use App\Services\StudentLicenceAllocation;
use App\Services\Uploads\UploadStorage;
use App\Support\DuplicateStudentNotice;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The confirmation step of a Class Teacher's My Class form, when the pupil
 * being added may already be on the school's records. The same question the
 * School Admin is asked (StudentExistingMatchController), with a Class
 * Teacher's limits:
 *
 *  - a matching pupil ALREADY IN ONE OF THIS TEACHER'S CLASSES can be
 *    updated (details corrected, moved between the teacher's own classes),
 *    admission number and everything attached to the pupil kept;
 *  - a matching pupil in ANOTHER class is shown, so the teacher knows they
 *    exist, but cannot be chosen: moving a child out of someone else's class
 *    is the School Admin's decision (StudentPolicy::update);
 *  - "a different child" registers a new pupil, as adding one always could.
 *
 * Every choice is checked again on the server, whatever the form sends.
 */
class ClassStudentExistingMatchController extends Controller
{
    public const TTL_MINUTES = 60;

    public function __construct(
        private readonly IdentifierGenerator $identifiers,
        private readonly StudentLicenceAllocation $licences,
        private readonly UploadStorage $uploads,
    ) {}

    public static function cacheKey(Staff $staff, string $token): string
    {
        return 'staff-student-existing-match:'.$staff->getKey().':'.hash('sha256', $token);
    }

    public function show(Request $request, School $school, string $token): View|RedirectResponse
    {
        $staff = $request->user('staff');
        $school = $staff->school;
        $pending = $this->pending($staff, $token, $school);

        if ($pending === null) {
            return $this->expired($school);
        }

        return view('staff.students.confirm-existing', [
            'school' => $school,
            'token' => $token,
            'data' => $pending['data'],
            'hasPhoto' => filled($pending['photo_path'] ?? null),
            'candidates' => $this->candidates($staff, $school, $pending),
            // A Class Teacher's new pupils are always numbered automatically.
            'autoGenerate' => true,
        ]);
    }

    public function confirm(Request $request, School $school, string $token): RedirectResponse
    {
        $staff = $request->user('staff');
        $school = $staff->school;
        $pending = $this->pending($staff, $token, $school);

        if ($pending === null) {
            return $this->expired($school);
        }

        // Only the records this teacher may update are choices at all.
        $updatable = collect($this->candidates($staff, $school, $pending))
            ->filter(fn ($candidate) => $candidate['locked'] === null)
            ->map(fn ($candidate) => 'update:'.$candidate['student_id'])
            ->all();

        $choice = $request->validate([
            'choice' => ['required', 'string', 'in:new,'.implode(',', $updatable)],
        ], [
            'choice.required' => 'Please choose whether this is one of the existing pupils or a different child.',
            'choice.in' => 'Please choose one of the options you are allowed to choose.',
        ])['choice'];

        // The class this pupil is going into must still be one of the
        // teacher's own.
        Gate::forUser($staff)->authorize('create', [Student::class, $pending['data']['class_name'] ?? null]);

        if ($choice === 'new') {
            return $this->createNew($staff, $token, $school, $pending);
        }

        $student = $school->students()->whereKey((int) Str::after($choice, 'update:'))->firstOrFail();

        Gate::forUser($staff)->authorize('update', $student);

        $previousPhoto = $student->photo_path;
        $before = $student->class_name;

        $changes = StudentReRegistration::apply(
            $student,
            Arr::only($pending['data'], ClassStudentController::EDITABLE),
            $pending['photo_path'] ?? null,
            "Class Teacher {$staff->fullName()}",
        );

        if (($pending['photo_path'] ?? null) && $previousPhoto && $previousPhoto !== $pending['photo_path']) {
            $this->uploads->delete('local', $previousPhoto);
        }

        Cache::forget(self::cacheKey($staff, $token));

        $moved = isset($changes['class_name']) ? " Moved from {$before} to {$student->class_name}." : '';

        return redirect()
            ->route('staff.students.index', ['school' => $school, 'class' => $student->class_name])
            ->with('status', $changes === []
                ? "{$student->fullName()} is already registered with these details, so nothing needed changing."
                : "{$student->fullName()}'s existing record was updated; no new record was created. Admission number {$student->admission_number} is unchanged.{$moved}");
    }

    public function cancel(Request $request, School $school, string $token): RedirectResponse
    {
        $staff = $request->user('staff');
        $school = $staff->school;
        $pending = $this->pending($staff, $token, $school);

        if ($pending && ($pending['photo_path'] ?? null)) {
            $this->uploads->delete('local', $pending['photo_path']);
        }

        Cache::forget(self::cacheKey($staff, $token));

        return redirect()->route('staff.students.index', ['school' => $school])
            ->with('status', 'Nothing was saved and no existing record was changed.');
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function createNew(Staff $staff, string $token, School $school, array $pending): RedirectResponse
    {
        // Never a typed admission number: a Class Teacher's new pupil is
        // numbered by the School Admin's own generator, as in
        // ClassStudentController::store.
        $data = Arr::only($pending['data'], ClassStudentController::EDITABLE);

        if (blank($school->school_code)) {
            return back()->withErrors(['choice' => 'Admission numbers cannot be generated yet because your school has no school code. Ask your School Admin to set one under Settings.']);
        }

        $student = $this->licences->withCapacity($school, function () use ($school, $data, $pending) {
            School::whereKey($school->id)->lockForUpdate()->first();

            $matcher = ExistingStudentMatcher::forSchool($school);
            $attempts = 0;

            do {
                $data['admission_number'] = $this->identifiers->nextAdmissionNumber($school, $data['class_name'] ?? null);
            } while ($matcher->admissionNumberTaken($data['admission_number']) && ++$attempts < 1000);

            return $school->students()->create([
                ...$data,
                'photo_path' => $pending['photo_path'] ?? null,
                'registration_source' => RegistrationSource::Single,
            ]);
        });

        if ($student === null) {
            return back()->withErrors(['choice' => $this->licences->limitReachedMessage($school)]);
        }

        Cache::forget(self::cacheKey($staff, $token));

        AuditLog::record(
            'students.added_by_class_teacher',
            "{$staff->fullName()} (Class Teacher) added {$student->fullName()} ({$student->admission_number}) to {$student->class_name}, confirming they are a different child from the pupil(s) already registered with the same name.",
            $student,
            $staff->fullName(),
        );

        return redirect()
            ->route('staff.students.index', ['school' => $school, 'class' => $student->class_name])
            ->with('status', "{$student->fullName()} was added to {$student->class_name} with admission number {$student->admission_number}.");
    }

    /**
     * The offered records as they are now. Each says why it cannot be chosen
     * ('locked') when it is not in one of this teacher's classes.
     *
     * @param  array<string, mixed>  $pending
     * @return list<array<string, mixed>>
     */
    private function candidates(Staff $staff, School $school, array $pending): array
    {
        $students = $school->students()
            ->whereKey(collect($pending['candidates'])->pluck('student_id'))
            ->get()
            ->keyBy('id');

        $labels = [
            'first_name' => 'First name', 'last_name' => 'Surname', 'gender' => 'Gender',
            'date_of_birth' => 'Date of birth', 'class_name' => 'Class', 'house' => 'House',
            'guardian_name' => 'Parent/guardian', 'guardian_phone' => 'Guardian phone',
            'guardian_email' => 'Guardian email', 'phone' => 'Student phone', 'email' => 'Student email',
            'address' => 'Address', 'admission_date' => 'Admission date', 'notes' => 'Notes',
        ];

        return collect($pending['candidates'])
            ->filter(fn ($candidate) => $students->has($candidate['student_id']))
            ->map(function ($candidate) use ($students, $pending, $labels, $staff) {
                $student = $students->get($candidate['student_id']);
                $changes = [];

                foreach ($labels as $field => $label) {
                    $new = $pending['data'][$field] ?? null;

                    if (blank($new)) {
                        continue;
                    }

                    $current = $student->getAttribute($field);
                    $current = match (true) {
                        $current instanceof CarbonInterface => $current->toDateString(),
                        $current instanceof \BackedEnum => (string) $current->value,
                        default => $current === null ? null : (string) $current,
                    };

                    if (mb_strtolower(trim((string) $current)) !== mb_strtolower(trim((string) $new))) {
                        $changes[$label] = [$current, (string) $new];
                    }
                }

                $locked = Gate::forUser($staff)->allows('update', $student)
                    ? null
                    : 'This pupil is in '.($student->class_name ?: 'no class yet').', which is not one of your classes. Only the School Admin can move them. If this is the same child, please ask the School Admin to correct their class; nothing has been saved.';

                return [
                    ...$candidate,
                    'notice' => DuplicateStudentNotice::for($student, 'Possible match: '.implode(', ', $candidate['signals']).'.'),
                    'changes' => $changes,
                    'locked' => $locked,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pending(Staff $staff, string $token, School $school): ?array
    {
        $pending = Cache::get(self::cacheKey($staff, $token));

        return is_array($pending) && ($pending['school_id'] ?? null) === $school->id ? $pending : null;
    }

    private function expired(School $school): RedirectResponse
    {
        return redirect()->route('staff.students.index', ['school' => $school])->withErrors([
            'first_name' => 'That registration has expired. Please add the pupil again.',
        ]);
    }
}
