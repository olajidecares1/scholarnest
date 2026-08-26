<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\NotifiesSchoolOfProfileChanges;
use App\Http\Controllers\Controller;
use App\Models\ProfileChangeRequest;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    use NotifiesSchoolOfProfileChanges;

    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');

        return view('staff.settings.index', [
            'school' => $school,
            'staff' => $staff,
            'protectedFields' => ProfileChangeRequestController::protectedFields(),
            'changeRequests' => ProfileChangeRequest::where('school_id', $school->id)
                ->where('requester_type', 'staff')
                ->where('requester_uuid', $staff->uuid)
                ->latest()
                ->get(),
        ]);
    }

    /**
     * A teacher's own contact details.
     *
     * Their Staff ID, role, school and password are not here and are not
     * accepted from this request: those are the school's to set, and a field
     * the page does not show but the controller would still honour is not
     * read-only.
     */
    public function updateProfile(Request $request, School $school): RedirectResponse
    {
        $staff = $request->user('staff');

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $this->applyProfileChanges(
            $staff,
            [
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            ],
            $staff->fullName(),
            'teacher/staff',
        );

        return back()->with('status', 'Your details were updated. Your school has been notified.');
    }
}
