<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Gender;
use App\Enums\RegistrationSource;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Services\IdentifierGenerator;
use App\Services\StudentImport\ExistingStudentMatcher;
use App\Services\StudentLicenceAllocation;
use App\Support\DuplicateStudentNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A Class Teacher's own class list: add a pupil, correct a pupil's name.
 *
 * DELIBERATELY NOTHING ELSE. There is no delete here, no route for one and no
 * button: removing a student's record removes their results with it, and that
 * is the School Admin's decision alone (StudentPolicy::delete). Nor can a
 * teacher reach any other class: every action is checked against the classes
 * the School Admin made them Class Teacher of, on the server, through
 * StudentPolicy, whatever the form sends.
 *
 * A pupil added here is registered exactly as one added by the School Admin:
 * the same duplicate check, the same admission number sequence, the same
 * limit on how many students the school's plan allows.
 */
class ClassStudentController extends Controller
{
    public function __construct(
        private readonly IdentifierGenerator $identifiers,
        private readonly StudentLicenceAllocation $licences,
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

        return view('staff.students.index', [
            'school' => $school,
            'classes' => $classes,
            'selectedClass' => $class,
            'students' => $class === null ? collect() : $school->students()->where('class_name', $class)->alphabetical()->get(),
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

        $validated = $request->validate([
            'class_name' => ['required', 'string', Rule::in($classes)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'admission_number' => [
                $auto ? 'prohibited' : 'required', 'nullable', 'string', 'max:50',
                Rule::unique('students', 'admission_number')->where('school_id', $school->id),
            ],
        ], [
            'class_name.in' => 'You can only add students/pupils to a class you are the Class Teacher of.',
            'admission_number.prohibited' => 'Admission numbers are given automatically in your school.',
        ]);

        Gate::forUser($staff)->authorize('create', [Student::class, $validated['class_name']]);

        $student = $this->licences->withCapacity($school, function () use ($school, $validated, $auto) {
            School::whereKey($school->id)->lockForUpdate()->first();

            $duplicate = ExistingStudentMatcher::forSchool($school, $validated['class_name'])->find($validated);

            if ($duplicate !== null) {
                return DuplicateStudentNotice::for(Student::findOrFail($duplicate['student_id']), $duplicate['reason']);
            }

            if ($auto) {
                $validated['admission_number'] = $this->identifiers->nextAdmissionNumber($school, $validated['class_name']);
            }

            return $school->students()->create([
                ...$validated,
                'registration_source' => RegistrationSource::Single,
            ]);
        });

        $back = redirect()->route('staff.students.index', ['school' => $school, 'class' => $validated['class_name']]);

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
     * Correct a pupil's first name and surname. Nothing else on the record
     * can be changed from here.
     */
    public function updateName(Request $request, School $school, Student $student): RedirectResponse
    {
        $staff = $request->user('staff');
        $school = $staff->school;

        Gate::forUser($staff)->authorize('updateName', $student);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
        ]);

        $before = $student->fullName();
        $student->update($validated);

        if ($before !== $student->fullName()) {
            AuditLog::record(
                'students.renamed_by_class_teacher',
                "{$staff->fullName()} (Class Teacher) changed the name of {$before} ({$student->admission_number}) to {$student->fullName()}.",
                $student,
                $staff->fullName(),
            );
        }

        return redirect()
            ->route('staff.students.index', ['school' => $school, 'class' => $student->class_name])
            ->with('status', $before === $student->fullName() ? 'No change was made to the name.' : "{$before} is now {$student->fullName()}.");
    }

    /**
     * The classes this teacher is Class Teacher of, in the school's own class
     * order. Adding is refused outright for anyone who is not a Class
     * Teacher; the list page just says so.
     *
     * @return list<string>
     */
    private function classesOf(Staff $staff, School $school, bool $refuse = true): array
    {
        $mine = $staff->classTeacherClassNames();

        abort_if($refuse && $mine->isEmpty(), 403, 'Only a Class Teacher can add students/pupils.');

        $order = array_flip($school->configuredClassNames());

        return $mine
            ->sortBy(fn (string $class) => [$order[$class] ?? PHP_INT_MAX, $class])
            ->values()
            ->all();
    }
}
