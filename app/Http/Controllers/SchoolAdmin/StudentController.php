<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\IdentifierGenerator;
use App\Services\ImageOptimizer;
use App\Services\StudentLicenceAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(
        private readonly ImageOptimizer $optimizer,
        private readonly IdentifierGenerator $identifiers,
        private readonly StudentLicenceAllocation $licences,
    ) {}

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $students = $school->students()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('admission_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('class'), fn ($query) => $query->where('class_name', $request->string('class')))
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        $academicLevels = $school->academicLevels()->with('classes')->get();

        return view('school-admin.students.index', [
            'school' => $school,
            'students' => $students,
            'academicLevels' => $academicLevels,
            'totalCount' => $school->students()->count(),
            'activeCount' => $school->students()->where('is_active', true)->count(),
            'studentSlotLimit' => $this->licences->allocated($school),
            'studentSlotsRemaining' => $this->licences->remaining($school),
            'studentSlotsExhausted' => $this->licences->isExhausted($school),
            'studentSlotsRunningLow' => $this->licences->isRunningLow($school),
            'levelCodesByClassName' => $academicLevels->flatMap(
                fn ($level) => $level->classes->mapWithKeys(fn ($class) => [$class->name => $level->code ?: 'GEN'])
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules($school->id, null, $school->auto_generate_admission_numbers));

        // The capacity check and the insert happen together, under a lock. A
        // check followed by a separate create would let two simultaneous
        // submissions - a double-click is enough - both pass and both insert.
        $student = $this->licences->withCapacity($school, function () use ($school, $request, $validated) {
            if ($school->auto_generate_admission_numbers) {
                $validated['admission_number'] = $this->identifiers->nextAdmissionNumber($school, $validated['class_name'] ?? null);
            }

            return $school->students()->create([
                ...Arr::except($validated, 'photo'),
                'photo_path' => $this->storePhoto($request),
            ]);
        });

        if ($student === null) {
            return back()->withErrors([
                'admission_number' => $this->licences->limitReachedMessage($school),
            ])->withInput();
        }

        return back()->with('status', "{$student->fullName()} was added successfully.");
    }

    public function show(Student $student): View
    {
        $this->authorizeStudent($student);

        return view('school-admin.students.show', [
            'student' => $student,
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($student);
        $autoGenerate = $student->school->auto_generate_admission_numbers;

        $validated = $request->validate($this->rules($student->school_id, $student->id, $autoGenerate));

        if ($autoGenerate) {
            // The admission number is locked once auto-generation is on -
            // any value submitted for it (the field is disabled in the
            // form, but never trust client input for this) is ignored.
            unset($validated['admission_number']);
        }

        $student->update([
            ...Arr::except($validated, 'photo'),
            'photo_path' => $this->storePhoto($request) ?: $student->photo_path,
        ]);

        return back()->with('status', "{$student->fullName()} was updated successfully.");
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->authorizeStudent($student);

        if ($student->photo_path) {
            Storage::disk('public')->delete($student->photo_path);
        }

        $name = $student->fullName();
        $student->delete();

        return back()->with('status', "{$name} was removed successfully.");
    }

    public function toggleActive(Student $student): RedirectResponse
    {
        $this->authorizeStudent($student);

        // Deactivating always works - it releases a licence.
        if ($student->is_active) {
            $student->update(['is_active' => false]);

            return back()->with('status', "{$student->fullName()} was deactivated.");
        }

        // Reactivating consumes a licence, so it must pass the same check as
        // creating one. Without this, a school at its limit could deactivate a
        // student, admit a new one, then reactivate the first - ending up over
        // its allocation with every individual step looking legitimate.
        $reactivated = $this->licences->withCapacity(
            $student->school,
            function () use ($student): bool {
                $student->update(['is_active' => true]);

                return true;
            },
        );

        if ($reactivated === null) {
            return back()->withErrors([
                'student' => $this->licences->limitReachedMessage($student->school),
            ]);
        }

        return back()->with('status', "{$student->fullName()} is now active.");
    }

    public function updatePassword(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($student);

        $validated = $request->validate([
            'password' => ['required', 'string', Password::defaults()],
        ]);

        $student->update(['password' => Hash::make($validated['password']), 'must_change_password' => true]);

        AuditLog::record('password.reset', "Portal password reset for student {$student->fullName()}.", $student);

        return back()->with('status', "Portal password set for {$student->fullName()}.");
    }

    public function storeGuardian(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($student);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        $guardian = Guardian::firstOrCreate(
            ['school_id' => $student->school_id, 'email' => $validated['email']],
            ['name' => $validated['name'], 'phone' => $validated['phone'] ?? null],
        );

        $guardian->students()->syncWithoutDetaching([
            $student->id => ['relationship' => $validated['relationship'] ?? null],
        ]);

        return back()->with('status', "{$guardian->name} was linked as a guardian for {$student->fullName()}.");
    }

    public function updateGuardianPassword(Request $request, Guardian $guardian): RedirectResponse
    {
        abort_unless($guardian->school_id === auth()->user()->school_id, 403);

        $validated = $request->validate([
            'password' => ['required', 'string', Password::defaults()],
        ]);

        $guardian->update(['password' => Hash::make($validated['password']), 'must_change_password' => true]);

        AuditLog::record('password.reset', "Portal password reset for guardian {$guardian->name}.", $guardian);

        return back()->with('status', "Portal password set for {$guardian->name}.");
    }

    public function destroyGuardian(Student $student, Guardian $guardian): RedirectResponse
    {
        $this->authorizeStudent($student);
        abort_unless($guardian->school_id === auth()->user()->school_id, 403);

        $guardian->students()->detach($student->id);

        return back()->with('status', "{$guardian->name} was unlinked from {$student->fullName()}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(int $schoolId, ?int $ignoreId = null, bool $autoGenerateAdmissionNumber = false): array
    {
        return [
            'admission_number' => [
                $autoGenerateAdmissionNumber ? 'nullable' : 'required', 'string', 'max:50',
                Rule::unique('students', 'admission_number')->where('school_id', $schoolId)->ignore($ignoreId),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'class_name' => ['nullable', 'string', 'max:50'],
            'house' => ['nullable', 'string', 'max:100'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'admission_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        $file = $request->file('photo');
        $path = $file->storeAs('students', (string) Str::uuid().'.'.$file->getClientOriginalExtension(), 'public');

        $this->optimizer->optimize(Storage::disk('public')->path($path), (string) $file->getMimeType());

        return $path;
    }

    private function authorizeStudent(Student $student): void
    {
        abort_unless($student->school_id === auth()->user()->school_id, 403);
    }
}
