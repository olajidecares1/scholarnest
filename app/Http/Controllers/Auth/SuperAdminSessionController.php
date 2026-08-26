<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Signing in as a Super Admin.
 *
 * The dialog this serves is hidden behind a click sequence on the logo, but
 * that sequence is a curtain, not a lock. Nothing here trusts it: this endpoint
 * behaves exactly the same whether the request came from the revealed dialog or
 * from somebody who found the URL and posted at it directly, and it is the
 * checks below - not the curtain - that keep the account safe.
 *
 * Separate from the shared sign-in page because that page admits School Admins
 * too. This one admits nobody but a Super Admin, which means a stolen School
 * Admin password cannot be used here even though it is perfectly valid
 * elsewhere.
 */
class SuperAdminSessionController extends Controller
{
    /**
     * A discovered URL should reveal nothing.
     *
     * There is no Super Admin sign-in PAGE to serve - the dialog only ever
     * exists on the registration page, behind the click sequence - so anyone
     * arriving here by typing the address gets the ordinary sign-in page, the
     * same as any other visitor.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $login = $request->string('login')->toString();

        try {
            // The shared request object, so this endpoint inherits the same
            // credential check, the same account-status check, and both rate
            // limiters - per credential and per IP - rather than a second,
            // weaker copy of them written just for this door.
            $request->authenticate();
        } catch (ValidationException $exception) {
            $this->log('super-admin.login-failed', "Failed EduNest Team sign-in attempt for '{$login}'.", $login);

            throw $exception;
        }

        $user = Auth::user();

        if ($user->role !== UserRole::SuperAdmin) {
            // Valid credentials, wrong role. They are logged straight back out
            // and told exactly what a wrong password is told - confirming that
            // a real account exists behind this address would turn the hidden
            // door into an account oracle.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->log(
                'super-admin.login-refused',
                "Refused EduNest Team sign-in for '{$login}': the account is not a EduNest Team.",
                $login,
            );

            throw ValidationException::withMessages(['login' => trans('auth.failed')]);
        }

        $request->session()->regenerate();

        AuditLog::record('super-admin.login', "EduNest Team {$user->name} signed in.", $user);

        return redirect()->intended(route('super-admin.dashboard', absolute: false));
    }

    /**
     * Record an attempt that never produced a session.
     *
     * AuditLog::record() reads the acting user for its actor, and there is not
     * one here, so the attempted login is passed explicitly instead - that
     * string is the only trace a failed attempt leaves.
     */
    private function log(string $action, string $description, string $login): void
    {
        AuditLog::record($action, $description, null, "Unauthenticated ({$login})");
    }
}
