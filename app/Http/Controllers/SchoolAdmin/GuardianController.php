<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Concerns\SetsPortalCredentials;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Student;
use App\Rules\NotDerivedFromIdentity;
use App\Services\IdentifierGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class GuardianController extends Controller
{
    use AuthorizesSchoolOwnership;
    use SetsPortalCredentials;

    public function __construct(private readonly IdentifierGenerator $identifiers) {}

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
            // The guardian does not exist yet, so the identity comes from what
            // is being submitted for them plus the school they are joining.
            'password' => ['nullable', 'string', Password::defaults(), new NotDerivedFromIdentity([
                $school->name,
                $request->input('name'),
                $request->input('email'),
            ])],
        ]);

        // The parent's ID, like every other login identifier, is the system's
        // to issue. It is generated here rather than typed anywhere.
        $guardianNumber = $this->identifiers->nextGuardianId($school);

        $guardian = $school->guardians()->create([
            'guardian_number' => $guardianNumber,
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

        $this->applyCredentialFields($request, $guardian, 'guardian_number', 'Parent ID');

        return $request->filled('login_password')
            ? redirect()->route('guardians.show', $guardian)->with('status', "{$validated['name']} was added successfully.")
            : back()->with('status', "{$validated['name']} was added successfully.");
    }

    public function show(Guardian $guardian): View
    {
        $this->authorizeGuardian($guardian);

        return view('school-admin.guardians.show', [
            'credentialShare' => $this->credentialShareLink($guardian, 'Parent ID', (string) $guardian->guardian_number),
            'guardian' => $guardian,

            // Only the classes, for the picker's filter. The pupils themselves
            // are fetched as they are searched for - loading every one of them
            // into the page was what made this unusable for a school with more
            // than a few classes. See linkCandidates().
            'classOptions' => $guardian->school->configuredClassNames(),
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

        $this->applyCredentialFields($request, $guardian, 'guardian_number', 'Parent ID');

        return back()->with('status', "{$guardian->name} was updated successfully.");
    }

    public function destroy(Guardian $guardian): RedirectResponse
    {
        $this->authorizeGuardian($guardian);

        if ($guardian->photo_path) {
            Storage::disk('local')->delete($guardian->photo_path);
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
            'password' => ['required', 'string', Password::defaults(), $this->identityRuleFor($guardian)],
        ]);

        $guardian->update(['password' => Hash::make($validated['password']), 'must_change_password' => false]);

        AuditLog::record('password.reset', "Portal password reset for guardian {$guardian->name}.", $guardian);

        return back()->with('status', "Portal password set for {$guardian->name}.");
    }

    /**
     * Children this guardian could be linked to.
     *
     * Narrowed by class and by a search, because the alternative - every pupil
     * in the school in one dropdown - stops being usable at about the third
     * class and is how the wrong child gets linked.
     *
     * Scoped to the guardian's own school, and that is the only scoping that
     * matters: the class and the search are conveniences, the school_id is the
     * rule. A guardian at one school must never be offered a pupil at another.
     */
    public function linkCandidates(Request $request, Guardian $guardian): JsonResponse
    {
        $this->authorizeGuardian($guardian);

        $search = trim($request->string('q')->toString());

        $students = Student::query()
            ->where('school_id', $guardian->school_id)
            ->whereNotIn('id', $guardian->students()->pluck('students.id'))
            ->when($request->filled('class'), fn ($query) => $query->where('class_name', $request->string('class')->toString()))
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('admission_number', 'like', "%{$search}%");
            }))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(25)
            ->get();

        return response()->json([
            'students' => $students->map(fn (Student $student) => [
                'uuid' => $student->uuid,
                'name' => $student->fullName(),
                'admission_number' => $student->admission_number,
                'class_name' => $student->class_name,
            ])->all(),
        ]);
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
        $this->authorizeSchoolOwnership($guardian);
    }

    /**
     * Issue this account its login details: the username it signs in with,
     * and the password to go with it.
     *
     * The School Admin is the authority on both. Users may edit their own
     * contact details, but never their login identifier and never their
     * password - see the portal profile controllers.
     */
    public function updateCredentials(Request $request, Guardian $guardian): RedirectResponse
    {
        $this->authorizeGuardian($guardian);

        $status = $this->saveCredentials(
            $request,
            $guardian,
            'guardian_number',
            $guardian->name,
            'Parent ID',
        );

        return back()->with('status', $status);
    }
}
