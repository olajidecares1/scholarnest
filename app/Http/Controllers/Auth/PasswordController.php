<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Rules\NotDerivedFromIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                Password::defaults(),
                'confirmed',

                // From the signed-in account, never from the form.
                new NotDerivedFromIdentity([
                    $request->user()->school?->name,
                    $request->user()->name,
                    $request->user()->email,
                    $request->user()->username,
                ]),
            ],
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        AuditLog::record('password.changed', "{$user->name} changed their own password.", $user);

        Auth::guard('web')->logoutOtherDevices($validated['password']);

        return back()->with('status', 'password-updated');
    }
}
