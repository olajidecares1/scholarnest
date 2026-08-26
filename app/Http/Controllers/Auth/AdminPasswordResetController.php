<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminPasswordResetBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The School Admin (and Super Admin, since both share the "web" guard)
 * forgot-password flow: email link -> 6-digit code (from the same email,
 * entered via a form field, never a URL) -> new password. Staff, Student,
 * and Guardian guards deliberately have no equivalent of this controller at
 * all - there is no route for them to reach, so a direct request to a
 * password-reset endpoint for those guards 404s rather than needing an
 * explicit rejection.
 */
class AdminPasswordResetController extends Controller
{
    /**
     * Seconds between one code and the next. Mirrored by the countdown on the
     * verification page, so the button re-enables exactly when the server
     * would accept another request.
     */
    public const RESEND_COOLDOWN_SECONDS = 60;

    public function create(): View
    {
        return view('auth.admin-password-reset.request');
    }

    public function store(Request $request, AdminPasswordResetBroker $broker): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $key = 'admin-password-reset-request:'.strtolower($validated['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'email' => 'Too many reset requests. Please try again in a few minutes.',
            ]);
        }

        RateLimiter::hit($key, 900);

        $user = User::where('email', $validated['email'])->first();

        if ($user && $user->is_active) {
            $broker->request($user);
        }

        // Same response whether or not the email matched an account - the
        // email's existence must never be revealed by a different message.
        return back()->with('status', 'If that email address belongs to an account, we\'ve sent password reset instructions to it.');
    }

    public function show(string $token, AdminPasswordResetBroker $broker): View
    {
        $reset = $broker->resolve($token);

        if (! $reset) {
            return view('auth.admin-password-reset.invalid');
        }

        if ($reset->code_verified_at) {
            return view('auth.admin-password-reset.new-password', ['token' => $token]);
        }

        return view('auth.admin-password-reset.verify-code', ['token' => $token]);
    }

    /**
     * Send another code for a reset already in progress.
     *
     * Cooled down to one a minute per reset, which is what the countdown on
     * the page reflects. Without it "Resend" is a button that mails the
     * account holder as fast as it can be clicked.
     */
    public function resend(Request $request, string $token, AdminPasswordResetBroker $broker): RedirectResponse
    {
        $reset = $broker->resolve($token);

        if (! $reset) {
            return redirect()->route('admin.password-reset.show', $token);
        }

        $key = 'admin-password-reset-resend:'.$reset->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors([
                'code' => 'Please wait a moment before requesting another code.',
            ]);
        }

        RateLimiter::hit($key, self::RESEND_COOLDOWN_SECONDS);

        $broker->resendCode($reset, $token);

        return back()->with('status', 'We\'ve sent a new code to your email address.');
    }

    public function verifyCode(Request $request, string $token, AdminPasswordResetBroker $broker): RedirectResponse
    {
        $reset = $broker->resolve($token);

        if (! $reset) {
            return redirect()->route('admin.password-reset.show', $token);
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $key = 'admin-password-reset-verify:'.$reset->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Please request a new reset link.',
            ]);
        }

        RateLimiter::hit($key, 900);

        if (! $broker->verifyCode($reset, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => 'That code is incorrect or has expired.',
            ]);
        }

        RateLimiter::clear($key);

        return redirect()->route('admin.password-reset.show', $token);
    }

    public function complete(Request $request, string $token, AdminPasswordResetBroker $broker): RedirectResponse
    {
        $reset = $broker->resolve($token);

        if (! $reset || ! $reset->code_verified_at) {
            return redirect()->route('admin.password-reset.show', $token);
        }

        $validated = $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $school = $reset->user->school;

        $broker->complete($reset, $validated['password']);

        // Where this person actually signs in, worked out before the session
        // is anything and remembered for the success screen. A School Admin
        // belongs to one school and signs in at its portal; a Super Admin has
        // no school and uses the hidden dialog on the registration page.
        return redirect()->route('admin.password-reset.done')->with(
            'password_reset_sign_in_url',
            $school?->portalLoginUrl('web') ?? route('register'),
        );
    }

    /**
     * The end of the flow: password changed, here is the way back in.
     *
     * A page of its own rather than a flash message on the sign-in screen,
     * because there is no longer one sign-in screen to flash it on - and
     * because "it worked" deserves to be stated plainly rather than as a
     * banner above a form.
     */
    public function done(Request $request): View
    {
        return view('auth.admin-password-reset.done', [
            'signInUrl' => $request->session()->get('password_reset_sign_in_url', route('register')),
        ]);
    }
}
