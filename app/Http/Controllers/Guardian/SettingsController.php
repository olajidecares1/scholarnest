<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
        return view('guardian.settings.index', [
            'school' => $school,
            'guardian' => $request->user('guardian'),
        ]);
    }

    public function updateProfile(Request $request, School $school): RedirectResponse
    {
        $guardian = $request->user('guardian');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $guardian->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
        ]);

        return back()->with('status', 'Your profile was updated.');
    }

    public function updatePassword(Request $request, School $school): RedirectResponse
    {
        $guardian = $request->user('guardian');

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $guardian->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $guardian->update(['password' => Hash::make($validated['password']), 'must_change_password' => false]);

        AuditLog::record('password.changed', "{$guardian->name} changed their own password.", $guardian, actorName: $guardian->name);

        return back()->with('status', 'Your password was updated.');
    }
}
