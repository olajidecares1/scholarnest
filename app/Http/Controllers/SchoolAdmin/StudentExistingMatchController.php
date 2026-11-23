<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\RegistrationSource;
use App\Http\Controllers\Controller;
use App\Models\School;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The confirmation step of the Add Student form, when the child being
 * registered may already be on the school's records.
 *
 * A name is not proof: two children at one school can share one. So nothing
 * is saved until the School Admin has looked at the existing record(s) and
 * said either
 *
 *   - "Yes, this is the same child": the existing record is updated with the
 *     new details (most often, moved to the correct class). The admission
 *     number, results, attendance, payments and everything else attached to
 *     the student stay exactly as they are; or
 *   - "No, this is a different child": a new record is created as usual.
 *
 * The details waiting for that answer are kept on the server for an hour,
 * under a random token tied to the admin who submitted them, so the choice
 * cannot be pointed at a record that was not offered.
 */
class StudentExistingMatchController extends Controller
{
    public const TTL_MINUTES = 60;

    public function __construct(
        private readonly IdentifierGenerator $identifiers,
        private readonly StudentLicenceAllocation $licences,
        private readonly UploadStorage $uploads,
    ) {}

    public static function cacheKey(Request $request, string $token): string
    {
        return 'student-existing-match:'.$request->user()->getAuthIdentifier().':'.hash('sha256', $token);
    }

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $school = $request->user()->school;
        $pending = $this->pending($request, $token, $school);

        if ($pending === null) {
            return $this->expired();
        }

        return view('school-admin.students.confirm-existing', [
            'token' => $token,
            'data' => $pending['data'],
            'hasPhoto' => filled($pending['photo_path'] ?? null),
            'candidates' => $this->candidates($school, $pending),
            'autoGenerate' => (bool) $school->auto_generate_admission_numbers,
        ]);
    }

    public function confirm(Request $request, string $token): RedirectResponse
    {
        $school = $request->user()->school;
        $pending = $this->pending($request, $token, $school);

        if ($pending === null) {
            return $this->expired();
        }

        $offered = collect($pending['candidates'])->pluck('student_id')->map(fn ($id) => 'update:'.$id)->all();

        $choice = $request->validate([
            'choice' => ['required', 'string', 'in:new,'.implode(',', $offered)],
        ], [
            'choice.required' => 'Please choose whether this is one of the existing students/pupils or a different child.',
            'choice.in' => 'Please choose one of the options shown.',
        ])['choice'];

        if ($choice === 'new') {
            return $this->createNew($request, $token, $school, $pending);
        }

        // Only a record that was offered, at this school, can be updated.
        $student = $school->students()->whereKey((int) Str::after($choice, 'update:'))->first();

        if ($student === null) {
            return back()->withErrors(['choice' => 'That student/pupil is no longer on your records. Please choose again.']);
        }

        $previousPhoto = $student->photo_path;
        $changes = StudentReRegistration::apply($student, $pending['data'], $pending['photo_path'] ?? null, 'single registration');

        if (($pending['photo_path'] ?? null) && $previousPhoto && $previousPhoto !== $pending['photo_path']) {
            $this->uploads->delete('local', $previousPhoto);
        }

        Cache::forget(self::cacheKey($request, $token));

        $class = isset($changes['class_name']) ? " Moved from {$changes['class_name'][0]} to {$changes['class_name'][1]}." : '';

        return redirect()->route('students.show', $student)->with('status', $changes === []
            ? "{$student->fullName()} is already registered with these details, so nothing needed changing. Admission number {$student->admission_number} is unchanged."
            : "{$student->fullName()}'s existing record was updated; no new record was created. Admission number {$student->admission_number} is unchanged.{$class}");
    }

    public function cancel(Request $request, string $token): RedirectResponse
    {
        $school = $request->user()->school;
        $pending = $this->pending($request, $token, $school);

        if ($pending && ($pending['photo_path'] ?? null)) {
            $this->uploads->delete('local', $pending['photo_path']);
        }

        Cache::forget(self::cacheKey($request, $token));

        return redirect()->route('students.index')->with('status', 'Registration cancelled. Nothing was saved and no existing record was changed.');
    }

    /**
     * "A different child": registered as a new student, exactly as the Add
     * Student form does when there is no match, apart from the name check
     * the admin has just answered.
     *
     * @param  array<string, mixed>  $pending
     */
    private function createNew(Request $request, string $token, School $school, array $pending): RedirectResponse
    {
        $data = $pending['data'];
        $auto = (bool) $school->auto_generate_admission_numbers;

        $student = $this->licences->withCapacity($school, function () use ($school, $data, $auto, $pending) {
            School::whereKey($school->id)->lockForUpdate()->first();

            $matcher = ExistingStudentMatcher::forSchool($school);

            // The name was answered for; an admission number belonging to
            // somebody else never is.
            if (! $auto && filled($data['admission_number'] ?? null) && $matcher->admissionNumberTaken((string) $data['admission_number'])) {
                return 'taken';
            }

            if ($auto) {
                $attempts = 0;

                do {
                    $data['admission_number'] = $this->identifiers->nextAdmissionNumber($school, $data['class_name'] ?? null);
                } while ($matcher->admissionNumberTaken($data['admission_number']) && ++$attempts < 1000);
            }

            return $school->students()->create([
                ...$data,
                'photo_path' => $pending['photo_path'] ?? null,
                'registration_source' => RegistrationSource::Single,
            ]);
        });

        if ($student === null) {
            return back()->withErrors(['choice' => $this->licences->limitReachedMessage($school)]);
        }

        if ($student === 'taken') {
            return back()->withErrors(['choice' => "Admission number {$data['admission_number']} already belongs to another student/pupil, so a new record cannot use it. Cancel, and register this child again with a different admission number."]);
        }

        Cache::forget(self::cacheKey($request, $token));

        return redirect()->route('students.show', $student)->with('status', "{$student->fullName()} was added as a new student/pupil with admission number {$student->admission_number}. You can set their login details here.");
    }

    /**
     * The offered records as they are now, each with what matched, what does
     * not, and what updating it would change.
     *
     * @param  array<string, mixed>  $pending
     * @return list<array<string, mixed>>
     */
    private function candidates(School $school, array $pending): array
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
            ->map(function ($candidate) use ($students, $pending, $labels) {
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

                return [
                    ...$candidate,
                    'notice' => DuplicateStudentNotice::for($student, 'Possible match: '.implode(', ', $candidate['signals']).'.'),
                    'changes' => $changes,
                    'uuid' => $student->uuid,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pending(Request $request, string $token, School $school): ?array
    {
        $pending = Cache::get(self::cacheKey($request, $token));

        return is_array($pending) && ($pending['school_id'] ?? null) === $school->id ? $pending : null;
    }

    private function expired(): RedirectResponse
    {
        return redirect()->route('students.index')->withErrors([
            'admission_number' => 'That registration has expired. Please fill in the Add Student form again.',
        ]);
    }
}
