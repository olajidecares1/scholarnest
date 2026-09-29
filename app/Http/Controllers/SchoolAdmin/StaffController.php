<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\Gender;
use App\Enums\StaffRole;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Concerns\SetsPortalCredentials;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Staff;
use App\Rules\UploadedImage;
use App\Services\IdentifierGenerator;
use App\Services\Uploads\UploadStorage;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller
{
    use AuthorizesSchoolOwnership;
    use SetsPortalCredentials;

    public function __construct(
        private readonly UploadStorage $uploads,
        private readonly IdentifierGenerator $identifiers,
    ) {}

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $staff = $school->staff()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('staff_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        return view('school-admin.staff.index', [
            'school' => $school,
            'staff' => $staff,
            'totalCount' => $school->staff()->count(),
            'activeCount' => $school->staff()->where('is_active', true)->count(),
            'nextStaffSequence' => $this->identifiers->peekStaffSequence($school),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        // Always generated, never typed. The Staff ID is what a teacher
        // signs in with and what the numbering sequence keeps in order, so it
        // is not the School Admin's to set, and a setting that made it
        // sometimes theirs would make "cannot be edited" untrue on some
        // schools and true on others.
        $validated = $request->validate($this->rules($school->id, null, autoGenerateStaffId: true));

        if (StaffRole::from($validated['role']) === StaffRole::Teacher) {
            $limit = $school->teacherAccountLimit();
            $activeTeacherCount = $school->staff()->where('role', StaffRole::Teacher)->where('is_active', true)->count();

            if ($limit !== null && $activeTeacherCount >= $limit) {
                return back()->withErrors([
                    // No plan caps teachers today, Basic stopped doing so when
                    // teacher accounts were decoupled from the student licence,
                    // but the check stays, generic, so reintroducing a cap on any
                    // plan is a data change rather than a code change.
                    'role' => "You have reached the maximum of {$limit} teacher accounts available on your current plan. Please upgrade your subscription to add more teachers.",
                ])->withInput();
            }
        }

        // The ID is reserved and the record written in one transaction, with
        // the school row locked: a freed Staff ID is reused, and two people
        // added at the same moment must not both be handed it.
        $member = DB::transaction(function () use ($school, $request, $validated) {
            $validated['staff_number'] = $this->identifiers->nextStaffId($school);

            return $school->staff()->create([
                ...Arr::except($validated, 'photo'),
                'photo_path' => $this->storePhoto($request),
            ]);
        });

        $this->applyCredentialFields($request, $member, 'staff_number', 'Staff ID');

        // To their own page when login details were issued, because that is
        // where the share sits and the password is only offered once. Back to
        // the list otherwise, which is where the admin was.
        return $request->filled('login_password')
            ? redirect()->route('staff.show', $member)->with('status', "{$member->fullName()} was added successfully.")
            : back()->with('status', "{$member->fullName()} was added successfully.");
    }

    public function show(Staff $member): View
    {
        $this->authorizeStaff($member);

        return view('school-admin.staff.show', [
            'credentialShare' => $this->credentialShareLink($member, 'Staff ID', (string) $member->staff_number),
            'member' => $member,
        ]);
    }

    public function update(Request $request, Staff $member): RedirectResponse
    {
        $this->authorizeStaff($member);

        $validated = $request->validate($this->rules($member->school_id, $member->id, autoGenerateStaffId: true));

        // The Staff ID is never editable. The form does not offer it, and any
        // value that arrives anyway is dropped here rather than trusted, a
        // field the interface refuses to show but the controller would still
        // honour is not read-only.
        unset($validated['staff_number']);

        $previousPhoto = $member->photo_path;
        $newPhoto = $this->storePhoto($request);

        $member->update([
            ...Arr::except($validated, 'photo'),
            'photo_path' => $newPhoto ?: $member->photo_path,
        ]);

        // The replaced photograph is removed once nothing points at it.
        if ($newPhoto && $previousPhoto && $previousPhoto !== $newPhoto) {
            $this->uploads->delete('local', $previousPhoto);
        }

        $this->applyCredentialFields($request, $member, 'staff_number', 'Staff ID');

        return $request->filled('login_password')
            ? redirect()->route('staff.show', $member)->with('status', "{$member->fullName()} was updated successfully.")
            : back()->with('status', "{$member->fullName()} was updated successfully.");
    }

    public function destroy(Staff $member): RedirectResponse
    {
        $this->authorizeStaff($member);

        if ($member->photo_path) {
            Storage::disk('local')->delete($member->photo_path);
        }

        $name = $member->fullName();
        $staffId = $member->staff_number;
        $member->delete();

        // Nobody else's Staff ID changes. The deleted one is simply free
        // again, and IdentifierGenerator issues it to the next staff member
        // registered, before the numbering moves on.
        return back()->with('status', "{$name} was removed successfully. Staff ID {$staffId} is free and will be given to the next staff member you add.");
    }

    public function toggleActive(Staff $member): RedirectResponse
    {
        $this->authorizeStaff($member);

        $member->update(['is_active' => ! $member->is_active]);

        return back()->with('status', $member->is_active ? "{$member->fullName()} is now active." : "{$member->fullName()} was deactivated.");
    }

    public function updatePassword(Request $request, Staff $member): RedirectResponse
    {
        $this->authorizeStaff($member);

        $validated = $request->validate([
            'password' => ['required', 'string', Password::defaults(), $this->identityRuleFor($member)],
        ]);

        $member->update(['password' => Hash::make($validated['password']), 'must_change_password' => false]);

        AuditLog::record('password.reset', "Portal password reset for staff member {$member->fullName()}.", $member);

        return back()->with('status', "Portal password set for {$member->fullName()}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(int $schoolId, ?int $ignoreId = null, bool $autoGenerateStaffId = false): array
    {
        return [
            'staff_number' => [
                $autoGenerateStaffId ? 'nullable' : 'required', 'string', 'max:50',
                Rule::unique('staff', 'staff_number')->where('school_id', $schoolId)->ignore($ignoreId),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'role' => ['required', Rule::enum(StaffRole::class)],
            'department' => ['nullable', 'string', 'max:100'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'employment_date' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => UploadedImage::rules(ImageProfile::Portrait),
        ];
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        return $this->uploads->storeImage($request->file('photo'), 'local', 'staff', ImageProfile::Portrait, 'photo')->path;
    }

    private function authorizeStaff(Staff $member): void
    {
        $this->authorizeSchoolOwnership($member);
    }

    /**
     * Issue this account its login details: the username it signs in with,
     * and the password to go with it.
     *
     * The School Admin is the authority on both. Users may edit their own
     * contact details, but never their login identifier and never their
     * password, see the portal profile controllers.
     */
    public function updateCredentials(Request $request, Staff $member): RedirectResponse
    {
        $this->authorizeStaff($member);

        $status = $this->saveCredentials(
            $request,
            $member,
            'staff_number',
            $member->fullName(),
            'Staff ID',
        );

        return back()->with('status', $status);
    }
}
