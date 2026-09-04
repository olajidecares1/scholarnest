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
            $this->log('super-admin.login-failed', "Failed ScholarNest Team sign-in attempt for '{$login}'.", $login);

            throw $exception;
        }

        $user = Auth::user();

        if ($user->role !== UserRole::SuperAdmin) {
            // Valid credentials, wrong role. No session is granted here - this
            // door admits Super Admins only - but they are told plainly where
            // to go instead.
            //
            // THAT IS SAFE, AND ONLY BECAUSE THE PASSWORD WAS CORRECT. The
            // oracle this door has to avoid is telling a stranger whether an
            // address belongs to an account; somebody who has just proved they
            // hold that account's password already knows it exists, because it
            // is theirs. A WRONG password never reaches this line - it is
            // refused above with the generic message, unchanged.
            //
            // It used to say "These credentials do not match our records",
            // which is what a wrong password is told. School Admins reach this
            // dialog by accident, and that sentence sent them away certain
            // their password was broken when it was perfectly good.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->log(
                'super-admin.login-refused',
                "Refused ScholarNest Team sign-in for '{$login}': the account is not a ScholarNest Team.",
                $login,
            );

            // The finder, not route('login') - that one redirects straight
            // back to the registration page, which is where they started.
            return redirect()->route('portal.find.show')->with(
                'status',
                'That was the ScholarNest Team sign-in. Your details are correct - please sign in to your school here instead.',
            );
        }

        $request->session()->regenerate();

        AuditLog::record('super-admin.login', "ScholarNest Team {$user->name} signed in.", $user);

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
