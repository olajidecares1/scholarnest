<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Rules\NotDerivedFromIdentity;
use App\Services\Auth\PasswordResetCodes;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Throwable;

/**
 * "Forgot password?", for the AkademicNest Team and School Admins.
 *
 * Three steps on one page, each checked on the server:
 *
 *   1. Email.    A 6-digit code is emailed to the account, if there is one.
 *   2. Code.     A wrong code is refused and the password fields stay hidden.
 *   3. Password. Only after the code is verified, and only in the same browser.
 *
 * WHAT THE EMAIL STEP SAYS. An address with no administrator account is told
 * so on the email step, and nothing is sent. An address with one moves on to
 * the code step with a short note saying where the code went. When the email
 * could not be sent, the page says so instead of pretending a code is on its
 * way, and that failed attempt does not count towards the hourly limit.
 *
 * Staff, students and parents have no self-service reset. Their School Admin
 * resets them. See docs/PASSWORD-RESET-POLICY.md.
 */
class PasswordResetController extends Controller
{
    private const SESSION_EMAIL = 'password_reset.email';

    private const SESSION_KEY = 'password_reset.key';

    private const SESSION_DONE = 'password_reset.done';

    /**
     * Codes sent to one address in an hour, beyond which no more are sent.
     * Keeps the form from being used to flood an inbox. Only codes that were
     * actually delivered count.
     */
    private const REQUESTS_PER_HOUR = 5;

    public const NO_ACCOUNT = 'We could not find an administrator account with that email address. Check the address and try again.';

    public const THROTTLED = 'Too many codes have been requested for this address. Use the latest code we sent, or try again in an hour.';

    public const INVALID_CODE = 'Invalid verification code. Please check the code sent to your email and try again.';

    public function __construct(private readonly PasswordResetCodes $codes) {}

    public function show(Request $request): View
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        $step = match (true) {
            $request->session()->has(self::SESSION_DONE) => 'done',
            $email !== null && $request->session()->has(self::SESSION_KEY) => 'password',
            $email !== null => 'code',
            default => 'email',
        };

        return view('auth.forgot-password', [
            'step' => $step,
            'email' => $email,
            'signInUrl' => $request->session()->get(self::SESSION_DONE),
            'expiresMinutes' => PasswordResetCodes::EXPIRES_MINUTES,
        ]);
    }

    /**
     * Step 1: send a code.
     */
    public function sendCode(Request $request, TransactionalMailer $mailer): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $user = $this->codes->eligibleUser($email);
        $throttleKey = 'password-reset-request:'.hash('sha256', $email);

        $outcome = match (true) {
            $user === null => 'no-account',
            RateLimiter::tooManyAttempts($throttleKey, self::REQUESTS_PER_HOUR) => 'throttled',
            $this->codes->recentlyIssued($email) => 'recently-sent',
            default => 'send',
        };

        if ($outcome === 'no-account') {
            $this->record($email, $outcome, $request->ip());
            $request->session()->forget([self::SESSION_EMAIL, self::SESSION_KEY, self::SESSION_DONE]);

            return redirect()->route('password.request')
                ->withInput(['email' => $validated['email']])
                ->withErrors(['email' => self::NO_ACCOUNT]);
        }

        if ($outcome === 'send') {
            $code = $this->codes->issue($user);
            $delivery = $mailer->send($user, new ResetPasswordNotification($code), 'password-reset-code', $user);

            if (! $delivery->wasSent()) {
                // The code cannot reach them, so it must not exist either.
                $this->codes->discard($email);
                $this->record($email, 'mail-failed', $request->ip());

                return back()
                    ->withInput(['email' => $validated['email']])
                    ->withErrors(['email' => 'We could not send the verification code right now. Please try again in a few minutes. If this keeps happening, contact AkademicNest support.']);
            }

            // Counted only once the code has actually gone out, so a mail
            // outage cannot lock an administrator out of their own reset.
            RateLimiter::hit($throttleKey, 3600);

            AuditLog::record('password-reset.requested', "A password reset code was sent to {$user->email}.", actorName: 'System');
        }

        $this->record($email, $outcome, $request->ip());

        $request->session()->put(self::SESSION_EMAIL, $email);
        $request->session()->forget([self::SESSION_KEY, self::SESSION_DONE]);

        if ($outcome === 'throttled') {
            return redirect()->route('password.request')->with('warning', self::THROTTLED);
        }

        return redirect()->route('password.request')->with(
            'status',
            "Code sent to {$email}. Check your inbox or spam folder."
        );
    }

    /**
     * Step 2: check the code.
     */
    public function verifyCode(Request $request): RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        if ($email === null) {
            return redirect()->route('password.request');
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Enter the 6-digit verification code from your email.',
            'code.digits' => 'The verification code is 6 digits.',
        ]);

        [$outcome, $resetKey] = $this->codes->verify($email, $validated['code']);

        if ($outcome !== PasswordResetCodes::VERIFIED) {
            if ($outcome === PasswordResetCodes::LOCKED) {
                AuditLog::record('password-reset.code-exhausted', "Too many incorrect verification codes were entered for {$email}. The code was cancelled.", actorName: 'System');
            }

            return back()->withErrors(['code' => match ($outcome) {
                PasswordResetCodes::EXPIRED => 'This verification code has expired. Request a new code.',
                PasswordResetCodes::LOCKED => 'Too many incorrect codes. For your security this code has been cancelled. Request a new code.',
                default => self::INVALID_CODE,
            }]);
        }

        $request->session()->put(self::SESSION_KEY, $resetKey);

        return redirect()->route('password.request')->with('status', 'Code verified. Now choose a new password.');
    }

    /**
     * Step 3: set the new password.
     */
    public function reset(Request $request): RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_EMAIL);
        $resetKey = $request->session()->get(self::SESSION_KEY);
        $user = $email !== null ? $this->codes->eligibleUser($email) : null;

        if ($user === null || ! is_string($resetKey) || ! $this->codes->mayReset($email, $resetKey)) {
            $request->session()->forget([self::SESSION_EMAIL, self::SESSION_KEY]);

            return redirect()->route('password.request')->withErrors([
                'email' => 'Your password reset has expired. Enter your email to get a new code.',
            ]);
        }

        $request->validate([
            // The new password must not be built out of the account either. A
            // reset is exactly when somebody reaches for the easiest thing they
            // will remember, which is usually the school's name.
            'password' => [
                'required',
                'confirmed',
                Rules\Password::defaults(),
                new NotDerivedFromIdentity([$user->email, $user->name, $user->username, $user->school?->name]),
            ],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),

            // A "remember me" cookie from before the reset stops working.
            'remember_token' => Str::random(60),
        ])->save();

        // Spent: the same code, or the same verified session, cannot be used again.
        $this->codes->discard($email);
        $request->session()->forget([self::SESSION_EMAIL, self::SESSION_KEY]);
        $request->session()->flash(self::SESSION_DONE, $this->signInUrlFor($user));

        AuditLog::record('password-reset.completed', "The password for {$user->email} was reset with an emailed code.", $user, actorName: 'System');

        // LogPasswordReset listens: it logs the reset and emails the account
        // holder that their password was changed.
        event(new PasswordReset($user));

        return redirect()->route('password.request');
    }

    /**
     * Start again with a different email address.
     */
    public function restart(Request $request): RedirectResponse
    {
        $request->session()->forget([self::SESSION_EMAIL, self::SESSION_KEY, self::SESSION_DONE]);

        return redirect()->route('password.request');
    }

    /**
     * Where this account signs in: the AkademicNest Team dialog, or the
     * school's own admin sign-in page.
     */
    private function signInUrlFor(User $user): string
    {
        if ($user->role === UserRole::SuperAdmin) {
            return route('super-admin.login');
        }

        try {
            return $user->school?->portalLoginUrl('web') ?? route('portal.show');
        } catch (Throwable) {
            return route('portal.show');
        }
    }

    /**
     * Every request is logged, including the ones that matched nothing: a run
     * of misses is what an enumeration attempt looks like.
     */
    private function record(string $email, string $outcome, ?string $ip): void
    {
        Log::info('Password reset code requested', [
            'email' => $email,
            'ip' => $ip,
            'outcome' => $outcome,
        ]);
    }
}
