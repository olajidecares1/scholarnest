<?php

namespace App\Http\Controllers\Staff\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\LoginRequest;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the staff login view.
     */
    public function create(School $school): View
    {
        return view('staff.auth.login', ['school' => $school]);
    }

    /**
     * Handle an incoming staff authentication request.
     */
    public function store(LoginRequest $request, School $school): RedirectResponse
    {
        $request->authenticate($school);

        $request->session()->regenerate();

        return redirect()->intended(route('staff.dashboard', $school, absolute: false));
    }

    /**
     * Destroy an authenticated staff session.
     */
    public function destroy(Request $request, School $school): RedirectResponse
    {
        Auth::guard('staff')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // portalLoginUrl() uses publicUrl(), not route() - see Student\Auth\AuthenticatedSessionController.
        return redirect()->to($school->portalLoginUrl('staff'));
    }
}
