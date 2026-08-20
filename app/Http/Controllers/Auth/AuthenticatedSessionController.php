<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login', [
            'background' => Setting::current()->loginBackgroundMedia,
        ]);
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
