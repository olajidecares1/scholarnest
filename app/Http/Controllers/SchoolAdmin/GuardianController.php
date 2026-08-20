<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class GuardianController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $guardians = $school->guardians()
            ->withCount('students')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('school-admin.guardians.index', [
            'school' => $school,
            'guardians' => $guardians,
            'totalCount' => $school->guardians()->count(),
            'activeCount' => $school->guardians()->where('is_active', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('guardians', 'email')->where('school_id', $school->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', Password::defaults()],
        ]);

        $guardian = $school->guardians()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => isset($validated['password']) ? Hash::make($validated['password']) : null,
            'must_change_password' => isset($validated['password']),
            'is_active' => true,
        ]);

        if (isset($validated['password'])) {
            AuditLog::record('password.set', "Portal password set for guardian {$guardian->name}.", $guardian);
        }

        return back()->with('status', "{$validated['name']} was added successfully.");
    }

    public function show(Guardian $guardian): View
    {
        $this->authorizeGuardian($guardian);

        $linkedStudentIds = $guardian->students()->pluck('students.id');

        return view('school-admin.guardians.show', [
            'guardian' => $guardian,
            // Only existing, unlinked students of this same school can ever
            // be offered here - the point is to connect an existing student
            // record to a guardian, never to create a new one.
            'availableStudents' => Student::where('school_id', $guardian->school_id)
                ->whereNotIn('id', $linkedStudentIds)
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function update(Request $request, Guardian $guardian): RedirectResponse
    {
        $this->authorizeGuardian($guardian);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('guardians', 'email')->where('school_id', $guardian->school_id)->ignore($guardian->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $guardian->update($validated);

        return back()->with('status', "{$guardian->name} was updated successfully.");
    }

    public function destroy(Guardian $guardian): RedirectResponse
    {
        $this->authorizeGuardian($guardian);

        if ($guardian->photo_path) {
            Storage::disk('public')->delete($guardian->photo_path);
        }

        $name = $guardian->name;
        $guardian->delete();

        return redirect()->route('guardians.index')->with('status', "{$name} was removed successfully.");
    }

    public function toggleActive(Guardian $guardian): RedirectResponse
    {
        $this->authorizeGuardian($guardian);

        $guardian->update(['is_active' => ! $guardian->is_active]);

        return back()->with('status', $guardian->is_active ? "{$guardian->name} is now active." : "{$guardian->name} was deactivated.");
    }

    public function updatePassword(Request $request, Guardian $guardian): RedirectResponse
    {
        $this->authorizeGuardian($guardian);

        $validated = $request->validate([
            'password' => ['required', 'string', Password::defaults()],
        ]);

        $guardian->update(['password' => Hash::make($validated['password']), 'must_change_password' => true]);

        AuditLog::record('password.reset', "Portal password reset for guardian {$guardian->name}.", $guardian);

        return back()->with('status', "Portal password set for {$guardian->name}.");
    }

    public function linkStudent(Request $request, Guardian $guardian): RedirectResponse
    {
        $this->authorizeGuardian($guardian);

        $validated = $request->validate([
            'student' => ['required', 'string'],
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        // The selected student must belong to the same school as the
        // guardian - never trust the submitted UUID alone, it could
        // otherwise be swapped for any student's UUID from any school.
        $student = Student::where('uuid', $validated['student'])
            ->where('school_id', $guardian->school_id)
            ->firstOrFail();

        $guardian->students()->syncWithoutDetaching([
            $student->id => ['relationship' => $validated['relationship'] ?? null],
        ]);

        return back()->with('status', "{$student->fullName()} was linked to {$guardian->name}.");
    }

    public function unlinkStudent(Guardian $guardian, Student $student): RedirectResponse
    {
        $this->authorizeGuardian($guardian);
        abort_unless($student->school_id === $guardian->school_id, 403);

        $guardian->students()->detach($student->id);

        return back()->with('status', "{$student->fullName()} was unlinked from {$guardian->name}.");
    }

    private function authorizeGuardian(Guardian $guardian): void
    {
        abort_unless($guardian->school_id === auth()->user()->school_id, 403);
    }
}
