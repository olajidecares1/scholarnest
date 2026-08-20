<?php

namespace App\Http\Controllers\Student;

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
        $student = $request->user('student');

        return view('student.settings.index', [
            'school' => $school,
            'student' => $student,
            'protectedFields' => ProfileChangeRequestController::protectedFields(),
            'changeRequests' => ProfileChangeRequest::where('school_id', $school->id)
                ->where('requester_type', 'student')
                ->where('requester_uuid', $student->uuid)
                ->latest()
                ->get(),
        ]);
    }

    public function updateProfile(Request $request, School $school): RedirectResponse
    {
        $student = $request->user('student');

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $student->update([
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
        ]);

        return back()->with('status', 'Your profile was updated.');
    }

    public function updatePassword(Request $request, School $school): RedirectResponse
    {
        $student = $request->user('student');

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $student->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $student->update(['password' => Hash::make($validated['password']), 'must_change_password' => false]);

        AuditLog::record('password.changed', "{$student->fullName()} changed their own password.", $student, actorName: $student->fullName());

        return back()->with('status', 'Your password was updated.');
    }
}
