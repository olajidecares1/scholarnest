<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * There is no shared sign-in page any more.
     *
     * Every school signs in at its own portal - /schools/{slug}/portal/admin/
     * {token}/login - which is where logging out and an expired session both
     * return them, and the Super Admin signs in through the hidden dialog on
     * the registration page. A generic "AkademicNest login" served neither, and
     * looked enough like the registration page to be mistaken for it.
     *
     * The route NAME stays, because it is what Laravel's auth middleware
     * redirects guests to and what seven places in the application fall back
     * on. It now sends them to the public front door instead of rendering a
     * page of its own.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('register');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // A School Admin who ended up authenticated via this shared,
        // school-agnostic login page still belongs to a specific school and
        // must be returned to that school's own portal - never to this page,
        // which is registration/first-login territory only. Super Admins
        // have no school to return to, so they alone keep this destination.
        if ($user?->role === UserRole::SchoolAdmin && $user->school) {
            return redirect()->to($user->school->portalLoginUrl('web'));
        }

        return redirect()->route('login');
    }
}
