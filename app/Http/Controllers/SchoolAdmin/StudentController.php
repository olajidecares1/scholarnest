<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(private readonly ImageOptimizer $optimizer) {}

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

        return view('school-admin.students.index', [
            'students' => $students,
            'classes' => $school->students()->whereNotNull('class_name')->distinct()->orderBy('class_name')->pluck('class_name'),
            'totalCount' => $school->students()->count(),
            'activeCount' => $school->students()->where('is_active', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules($school->id));

        $student = $school->students()->create([
            ...Arr::except($validated, 'photo'),
            'photo_path' => $this->storePhoto($request),
        ]);

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

        $validated = $request->validate($this->rules($student->school_id, $student->id));

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

        $student->update(['is_active' => ! $student->is_active]);

        return back()->with('status', $student->is_active ? "{$student->fullName()} is now active." : "{$student->fullName()} was deactivated.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(int $schoolId, ?int $ignoreId = null): array
    {
        return [
            'admission_number' => [
                'required', 'string', 'max:50',
                Rule::unique('students', 'admission_number')->where('school_id', $schoolId)->ignore($ignoreId),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'class_name' => ['nullable', 'string', 'max:50'],
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
