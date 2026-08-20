<?php

namespace App\Http\Controllers\Guardian\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\LoginRequest;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the guardian login view.
     */
    public function create(School $school): View
    {
        return view('guardian.auth.login', ['school' => $school]);
    }

    /**
     * Handle an incoming guardian authentication request.
     */
    public function store(LoginRequest $request, School $school): RedirectResponse
    {
        $request->authenticate($school);

        $request->session()->regenerate();

        return redirect()->intended(route('guardian.dashboard', $school, absolute: false));
    }

    /**
     * Destroy an authenticated guardian session.
     */
    public function destroy(Request $request, School $school): RedirectResponse
    {
        Auth::guard('guardian')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // portalLoginUrl() uses publicUrl(), not route() - see Student\Auth\AuthenticatedSessionController.
        return redirect()->to($school->portalLoginUrl('guardian'));
    }
}
