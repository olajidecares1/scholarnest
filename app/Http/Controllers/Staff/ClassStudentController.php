<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Gender;
use App\Enums\RegistrationSource;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Rules\UploadedImage;
use App\Services\IdentifierGenerator;
use App\Services\StudentImport\ExistingStudentMatcher;
use App\Services\StudentImport\StudentMatchCandidates;
use App\Services\StudentLicenceAllocation;
use App\Services\Uploads\UploadStorage;
use App\Support\DuplicateStudentNotice;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A Class Teacher's own class list: add pupils and keep their details up to
 * date.
 *
 * A Class Teacher fills in and edits the same details the School Admin does,
 * names, gender, date of birth, class, house, guardian and contact details,
 * admission date, notes and photograph, for pupils in their own class(es).
 *
 * WHAT STAYS WITH THE SCHOOL ADMIN:
 *   - DELETING a student. There is no delete here, no route and no button:
 *     removing a record removes the pupil's results with it.
 *   - The ADMISSION NUMBER once it is set. It is how results, tokens and
 *     cumulative results find the pupil; a teacher enters it only when adding
 *     a pupil in a school that does not generate them.
 *   - LOGIN DETAILS and ACTIVE STATUS. Who can sign in is the School Admin's
 *     call, as everywhere else in this application.
 *   - Other classes. A teacher can move a pupil only between classes they are
 *     Class Teacher of.
 *
 * Every action is checked on the server through StudentPolicy, whatever the
 * form sends; fields outside the list below are simply never read.
 */
class ClassStudentController extends Controller
{
    /**
     * The details a Class Teacher may set, on adding or editing.
     */
    public const EDITABLE = [
        'first_name', 'last_name', 'gender', 'date_of_birth', 'class_name', 'house',
        'guardian_name', 'guardian_phone', 'guardian_email', 'address', 'phone', 'email',
        'admission_date', 'notes',
    ];

    public function __construct(
        private readonly IdentifierGenerator $identifiers,
        private readonly StudentLicenceAllocation $licences,
        private readonly UploadStorage $uploads,
    ) {}

    public function index(Request $request, School $school): View
    {
        // $school comes from the address and is checked against the staff
        // member's own school by EnsureStaffIsActive; the record used is
        // always their own.
        $staff = $request->user('staff');
        $school = $staff->school;
        $classes = $this->classesOf($staff, $school, refuse: false);

        $class = in_array($request->query('class'), $classes, true) ? $request->query('class') : ($classes[0] ?? null);

        $students = $class === null ? collect() : $school->students()->where('class_name', $class)->alphabetical()->get();

        return view('staff.students.index', [
            'school' => $school,
            'classes' => $classes,
            'selectedClass' => $class,
            'students' => $students,

            // What the edit form opens with, per pupil.
            'editable' => $students->mapWithKeys(fn (Student $student) => [$student->uuid => [
                'action' => route('staff.students.update', [$school, $student]),
                'name' => $student->fullName(),
                'admission_number' => $student->admission_number,
                'photo' => $student->photoUrl(),
                ...collect(self::EDITABLE)->mapWithKeys(fn (string $field) => [$field => match (true) {
                    $student->{$field} instanceof \BackedEnum => $student->{$field}->value,
                    $student->{$field} instanceof \DateTimeInterface => $student->{$field}->format('Y-m-d'),
                    default => $student->{$field},
                }])->all(),
            ]]),
            'autoAdmissionNumbers' => (bool) $school->auto_generate_admission_numbers,
            'genders' => Gender::cases(),
        ]);
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        $staff = $request->user('staff');
        $school = $staff->school;
        $classes = $this->classesOf($staff, $school);
        $auto = (bool) $school->auto_generate_admission_numbers;

        // A taken admission number is checked below, not here: when it belongs
        // to this same pupil, the teacher is offered the existing record
        // rather than told the number is taken.
        $validated = $request->validate([
            ...$this->rules($classes),
            'admission_number' => [$auto ? 'prohibited' : 'required', 'nullable', 'string', 'max:50'],
        ], $this->messages() + [
            'admission_number.prohibited' => 'Admission numbers are given automatically in your school.',
        ]);

        Gate::forUser($staff)->authorize('create', [Student::class, $validated['class_name']]);

        $back = redirect()->route('staff.students.index', ['school' => $school, 'class' => $validated['class_name']]);

        // MAYBE ALREADY REGISTERED. Nothing is saved yet: the teacher is shown
        // the existing record(s) and confirms whether this is the same pupil
        // (whose record is then updated, admission number kept) or a
        // different child with the same name. A teacher can update only a
        // pupil already in one of their own classes; see
        // ClassStudentExistingMatchController.
        $finder = StudentMatchCandidates::forSchool($school);
        $typedNumber = $auto ? null : ($validated['admission_number'] ?? null);
        $candidates = $finder->find($validated, $typedNumber);
        $holder = $finder->holderOfAdmissionNumber($typedNumber);

        if ($holder !== null && ! collect($candidates)->contains('student_id', $holder)) {
            return $back->withErrors(['admission_number' => 'The admission number has already been taken.'])->withInput();
        }

        if ($candidates !== []) {
            $token = Str::random(40);

            Cache::put(ClassStudentExistingMatchController::cacheKey($staff, $token), [
                'school_id' => $school->id,
                'data' => Arr::only($validated, [...self::EDITABLE, 'admission_number']),
                'photo_path' => $this->storePhoto($request),
                'candidates' => $candidates,
            ], now()->addMinutes(ClassStudentExistingMatchController::TTL_MINUTES));

            return redirect()->route('staff.students.existing.show', ['school' => $school, 'token' => $token]);
        }

        $student = $this->licences->withCapacity($school, function () use ($school, $request, $validated, $auto) {
            School::whereKey($school->id)->lockForUpdate()->first();

            $duplicate = ExistingStudentMatcher::forSchool($school, $validated['class_name'])->find($validated);

            if ($duplicate !== null) {
                return DuplicateStudentNotice::for(Student::findOrFail($duplicate['student_id']), $duplicate['reason']);
            }

            if ($auto) {
                $validated['admission_number'] = $this->identifiers->nextAdmissionNumber($school, $validated['class_name']);
            }

            return $school->students()->create([
                ...Arr::except($validated, 'photo'),
                'photo_path' => $this->storePhoto($request),
                'registration_source' => RegistrationSource::Single,
            ]);
        });

        if ($student === null) {
            return $back->withErrors(['first_name' => $this->licences->limitReachedMessage($school)])->withInput();
        }

        if (is_array($student)) {
            return $back->with('duplicate_students', [$student])->withInput();
        }

        AuditLog::record(
            'students.added_by_class_teacher',
            "{$staff->fullName()} (Class Teacher) added {$student->fullName()} ({$student->admission_number}) to {$student->class_name}.",
            $student,
            $staff->fullName(),
        );

        return $back->with('status', "{$student->fullName()} was added to {$student->class_name} with admission number {$student->admission_number}.");
    }

    /**
     * Edit a pupil's details. Only the fields in EDITABLE (and a new photo)
     * are ever read from the request.
     */
    public function update(Request $request, School $school, Student $student): RedirectResponse
    {
        $staff = $request->user('staff');
        $school = $staff->school;

        Gate::forUser($staff)->authorize('update', $student);

        $validated = $request->validate($this->rules($this->classesOf($staff, $school)), $this->messages());

        $student->fill(Arr::only($validated, self::EDITABLE));
        $changed = array_keys($student->getDirty());

        $previousPhoto = $student->photo_path;
        $newPhoto = $this->storePhoto($request);

        if ($newPhoto !== null) {
            $student->photo_path = $newPhoto;
            $changed[] = 'photo';
        }

        $student->save();

        if ($newPhoto && $previousPhoto && $previousPhoto !== $newPhoto) {
            $this->uploads->delete('local', $previousPhoto);
        }

        if ($changed !== []) {
            AuditLog::record(
                'students.updated_by_class_teacher',
                "{$staff->fullName()} (Class Teacher) updated {$student->fullName()} ({$student->admission_number}): "
                    .collect($changed)->map(fn (string $field) => str_replace('_', ' ', $field))->join(', ').'.',
                $student,
                $staff->fullName(),
            );
        }

        return redirect()
            ->route('staff.students.index', ['school' => $school, 'class' => $student->class_name])
            ->with('status', $changed === [] ? "No changes were made to {$student->fullName()}." : "{$student->fullName()}'s details were updated.");
    }

    /**
     * The same rules the School Admin's form uses for these fields, with the
     * class limited to this teacher's own classes.
     *
     * @param  list<string>  $classes
     * @return array<string, mixed>
     */
    private function rules(array $classes): array
    {
        return [
            'class_name' => ['required', 'string', Rule::in($classes)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'house' => ['nullable', 'string', 'max:100'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'admission_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => UploadedImage::rules(ImageProfile::Portrait),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'class_name.in' => 'You can only place students/pupils in a class you are the Class Teacher of.',
        ];
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        return $this->uploads->storeImage($request->file('photo'), 'local', 'students', ImageProfile::Portrait, 'photo')->path;
    }

    /**
     * The classes this teacher is Class Teacher of, in the school's own class
     * order. Adding and editing are refused outright for anyone who is not a
     * Class Teacher; the list page just says so.
     *
     * @return list<string>
     */
    private function classesOf(Staff $staff, School $school, bool $refuse = true): array
    {
        $mine = $staff->classTeacherClassNames();

        abort_if($refuse && $mine->isEmpty(), 403, 'Only a Class Teacher can add or edit students/pupils.');

        $order = array_flip($school->configuredClassNames());

        return $mine
            ->sortBy(fn (string $class) => [$order[$class] ?? PHP_INT_MAX, $class])
            ->values()
            ->all();
    }
}
