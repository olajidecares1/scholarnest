<?php

namespace App\Http\Controllers\Student\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\LoginRequest;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the student login view.
     */
    public function create(School $school): View
    {
        return view('student.auth.login', ['school' => $school]);
    }

    /**
     * Handle an incoming student authentication request.
     */
    public function store(LoginRequest $request, School $school): RedirectResponse
    {
        $request->authenticate($school);

        $request->session()->regenerate();

        return redirect()->intended(route('student.dashboard', $school, absolute: false));
    }

    /**
     * Destroy an authenticated student session.
     */
    public function destroy(Request $request, School $school): RedirectResponse
    {
        Auth::guard('student')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // portalLoginUrl() uses publicUrl() internally, not route(), so
        // logging out from the school's subdomain returns there instead of
        // jumping back to the default path.
        return redirect()->to($school->portalLoginUrl('student'));
    }
}
