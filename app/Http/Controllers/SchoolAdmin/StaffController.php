<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\Gender;
use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(private readonly ImageOptimizer $optimizer) {}

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
            'staff' => $staff,
            'totalCount' => $school->staff()->count(),
            'activeCount' => $school->staff()->where('is_active', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules($school->id));

        $member = $school->staff()->create([
            ...Arr::except($validated, 'photo'),
            'photo_path' => $this->storePhoto($request),
        ]);

        return back()->with('status', "{$member->fullName()} was added successfully.");
    }

    public function show(Staff $member): View
    {
        $this->authorizeStaff($member);

        return view('school-admin.staff.show', [
            'member' => $member,
        ]);
    }

    public function update(Request $request, Staff $member): RedirectResponse
    {
        $this->authorizeStaff($member);

        $validated = $request->validate($this->rules($member->school_id, $member->id));

        $member->update([
            ...Arr::except($validated, 'photo'),
            'photo_path' => $this->storePhoto($request) ?: $member->photo_path,
        ]);

        return back()->with('status', "{$member->fullName()} was updated successfully.");
    }

    public function destroy(Staff $member): RedirectResponse
    {
        $this->authorizeStaff($member);

        if ($member->photo_path) {
            Storage::disk('public')->delete($member->photo_path);
        }

        $name = $member->fullName();
        $member->delete();

        return back()->with('status', "{$name} was removed successfully.");
    }

    public function toggleActive(Staff $member): RedirectResponse
    {
        $this->authorizeStaff($member);

        $member->update(['is_active' => ! $member->is_active]);

        return back()->with('status', $member->is_active ? "{$member->fullName()} is now active." : "{$member->fullName()} was deactivated.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(int $schoolId, ?int $ignoreId = null): array
    {
        return [
            'staff_number' => [
                'required', 'string', 'max:50',
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
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        $file = $request->file('photo');
        $path = $file->storeAs('staff', (string) Str::uuid().'.'.$file->getClientOriginalExtension(), 'public');

        $this->optimizer->optimize(Storage::disk('public')->path($path), (string) $file->getMimeType());

        return $path;
    }

    private function authorizeStaff(Staff $member): void
    {
        abort_unless($member->school_id === auth()->user()->school_id, 403);
    }
}
