<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_DECAY_SECONDS = 900;

    /**
     * Failed attempts allowed from a single IP address, across every account,
     * within the same 15-minute window.
     *
     * MAX_ATTEMPTS above stops someone guessing many passwords against ONE
     * account. It does nothing about the opposite attack: trying one common
     * password against thousands of DIFFERENT accounts. Each of those attempts
     * uses a different email, so each gets its own counter and none of them
     * ever reaches 5.
     *
     * This second, wider limit closes that gap.
     *
     * It is deliberately generous. A whole school often shares one internet
     * connection, so a single IP can legitimately produce a burst of mistyped
     * passwords first thing on a Monday morning.
     */
    private const MAX_ATTEMPTS_PER_IP = 30;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $field = str_contains((string) $this->string('login'), '@') ? 'email' : 'username';

        $credentials = [
            $field => $this->string('login')->toString(),
            'password' => $this->string('password')->toString(),
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::LOCKOUT_DECAY_SECONDS);
            RateLimiter::hit($this->ipThrottleKey(), self::LOCKOUT_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active || ($user->role === UserRole::SchoolAdmin && $user->school && ! $user->school->is_active)) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated. Please contact support.',
            ]);
        }

        // Only the per-credential counter is cleared. The per-IP counter
        // deliberately survives a successful sign-in: otherwise an attacker
        // spraying passwords could reset their own IP counter at will simply
        // by signing in to an account they legitimately own.
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * Two independent limits apply, and tripping either one refuses the
     * request:
     *
     *   - this email address from this IP  (5 failures / 15 min)
     *   - this IP across all addresses     (30 failures / 15 min)
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $credentialsBlocked = RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS);
        $ipBlocked = RateLimiter::tooManyAttempts($this->ipThrottleKey(), self::MAX_ATTEMPTS_PER_IP);

        if (! $credentialsBlocked && ! $ipBlocked) {
            return;
        }

        event(new Lockout($this));

        // Report whichever lockout lasts longer, so the message never suggests
        // trying again sooner than the request would actually be accepted.
        $seconds = max(
            $credentialsBlocked ? RateLimiter::availableIn($this->throttleKey()) : 0,
            $ipBlocked ? RateLimiter::availableIn($this->ipThrottleKey()) : 0,
        );

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Counts failures for one email address from one IP address.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }

    /**
     * Counts failures from one IP address against any account.
     *
     * The 'login-ip|' prefix keeps this in a separate namespace from
     * throttleKey(), so the two counters can never collide.
     */
    public function ipThrottleKey(): string
    {
        return 'login-ip|'.$this->ip();
    }
}
