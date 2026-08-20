<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProfileChangeRequest;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
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

    public function updateProfile(Request $request, School $school): RedirectResponse
    {
        $staff = $request->user('staff');

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $staff->update([
            'phone' => $validated['phone'] ?? null,
        ]);

        return back()->with('status', 'Your profile was updated.');
    }

    public function updatePassword(Request $request, School $school): RedirectResponse
    {
        $staff = $request->user('staff');

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $staff->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $staff->update(['password' => Hash::make($validated['password']), 'must_change_password' => false]);

        AuditLog::record('password.changed', "{$staff->fullName()} changed their own password.", $staff, actorName: $staff->fullName());

        return back()->with('status', 'Your password was updated.');
    }
}
