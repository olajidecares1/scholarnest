<?php

namespace App\Http\Requests\Guardian;

use App\Models\School;
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(School $school): void
    {
        $this->ensureIsNotRateLimited($school);

        // email is only unique per-school, not globally, so the credential
        // lookup must always be scoped to the school the guardian is logging
        // in through - mirrors the same constraint on student login.
        $credentials = [
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'school_id' => $school->id,
        ];

        if (! Auth::guard('guardian')->attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($school), self::LOCKOUT_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $guardian = Auth::guard('guardian')->user();

        if (! $guardian->is_active || ! $guardian->school?->is_active) {
            Auth::guard('guardian')->logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Please contact your school.',
            ]);
        }

        $guardian->forceFill(['last_login_at' => now()])->save();

        RateLimiter::clear($this->throttleKey($school));
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(School $school): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($school), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey($school));

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * Scoped by school_id, not just the email, because a guardian's email
     * is only unique per-school - without this, a parent at one school
     * could be locked out by failed attempts against an identically-emailed
     * guardian at a completely different school sharing the same IP address.
     */
    public function throttleKey(School $school): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$school->id.'|'.$this->ip());
    }
}
