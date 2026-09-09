<?php

namespace App\Http\Controllers\SchoolPortal\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolPortal\LoginRequest;
use App\Models\School;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the school-scoped School Admin login view.
     */
    public function create(School $school): View
    {
        return view('school-portal.admin-login', [
            'school' => $school,

            // The platform's configured sign-in background. It used to render
            // on the shared login page; with that page gone this is where
            // School Admins actually sign in, so the setting follows them here
            // rather than becoming a control in the media library that changes
            // nothing.
            'background' => Setting::current()->loginBackgroundMedia,
        ]);
    }

    /**
     * Handle an incoming School Admin authentication request.
     */
    public function store(LoginRequest $request, School $school): RedirectResponse
    {
        $request->authenticate($school);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated School Admin session, returning to this
     * school's own portal login rather than the global AkademicNest login.
     */
    public function destroy(Request $request, School $school): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // portalLoginUrl() uses publicUrl(), not route() - see Student\Auth\AuthenticatedSessionController.
        return redirect()->to($school->portalLoginUrl('web'));
    }
}
