<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Rules\NotDerivedFromIdentity;
use App\Support\PasswordResetCode;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Forgot password?", the reset half.
 *
 * TWO THINGS ARE REQUIRED, NOT ONE. The token in the link proves possession of
 * the URL; the six-digit code proves the email body was actually read. A link
 * that leaks, through a referrer header, a shared inbox, a screen left open in
 * a staffroom, is not by itself enough to take an administrator's account.
 *
 * Laravel owns the token entirely: it generates it, decides when it expires,
 * refuses it once used, and replaces it when a new one is requested. The code
 * is derived from that token rather than stored beside it, so it inherits every
 * one of those properties instead of restating them. See
 * App\Support\PasswordResetCode.
 */
class NewPasswordController extends Controller
{
    /**
     * Wrong codes tolerated before this link is finished with.
     *
     * Six digits is a million possibilities and the route is already throttled,
     * so this is not what stops a brute force, the throttle is. This stops a
     * SLOW one: an attacker holding the link who tries a handful an hour would
     * otherwise sit under every per-minute limit indefinitely.
     */
    private const MAX_CODE_ATTEMPTS = 5;

    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            // The new password must not be built out of the account either.
            // A reset is exactly when somebody reaches for the easiest thing
            // they will remember, which is usually the school's name.
            //
            // The email comes from the request because that is what the reset
            // is for; the school name is looked up from it rather than taken
            // from the form, so nothing submitted can weaken the check.
            'password' => [
                'required',
                'confirmed',
                Rules\Password::defaults(),
                new NotDerivedFromIdentity([
                    $request->input('email'),
                    User::where('email', $request->input('email'))->value('name'),
                    User::where('email', $request->input('email'))
                        ->join('schools', 'schools.id', '=', 'users.school_id')
                        ->value('schools.name'),
                ]),
            ],
        ], [
            'code.required' => 'Enter the 6-digit verification code from your email.',
            'code.digits' => 'The verification code is 6 digits.',
        ]);

        $this->assertCodeIsCorrect($validated['token'], $validated['email'], $validated['code']);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),

                    // A new remember token, so a "remember me" cookie taken
                    // from this account before the reset stops working. A
                    // password change that leaves the old session usable has
                    // not locked anybody out.
                    'remember_token' => Str::random(60),
                ])->save();

                // LogPasswordReset listens for this: it writes the audit entry
                // and sends the "your password was changed" notification.
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        RateLimiter::clear($this->attemptKey($validated['token']));

        return redirect()->route('login')->with('status', 'Your password has been reset. Sign in with your new password.');
    }

    /**
     * @throws ValidationException
     */
    private function assertCodeIsCorrect(string $token, string $email, string $code): void
    {
        $key = $this->attemptKey($token);

        if (RateLimiter::tooManyAttempts($key, self::MAX_CODE_ATTEMPTS)) {
            AuditLog::record(
                'password-reset.code-exhausted',
                "Too many incorrect verification codes were entered for {$email}. The reset link was abandoned.",
                actorName: 'System',
            );

            throw ValidationException::withMessages([
                'code' => 'Too many incorrect codes. Request a new reset link.',
            ]);
        }

        if (PasswordResetCode::matches($token, $code)) {
            return;
        }

        // Counted against the TOKEN, not the address, so somebody holding one
        // link cannot spend another person's allowance, and so a fresh link
        // starts with a fresh allowance.
        // Positional, not named: a facade routes calls through __callStatic,
        // which cannot forward named arguments.
        RateLimiter::hit($key, 3600);

        throw ValidationException::withMessages([
            'code' => 'That verification code is not correct. Check the email again.',
        ]);
    }

    private function attemptKey(string $token): string
    {
        return 'password-reset-code:'.hash('sha256', $token);
    }
}
