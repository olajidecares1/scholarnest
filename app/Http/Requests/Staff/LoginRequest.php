<?php

namespace App\Http\Requests\Staff;

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
            'login' => ['required', 'string'],
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

        $field = str_contains((string) $this->string('login'), '@') ? 'email' : 'staff_number';

        // staff_number/email are only unique per-school, not globally, so the
        // credential lookup must always be scoped to the school the staff member
        // is logging in through - mirrors the same constraint on student login.
        $credentials = [
            $field => $this->string('login')->toString(),
            'password' => $this->string('password')->toString(),
            'school_id' => $school->id,
        ];

        if (! Auth::guard('staff')->attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($school), self::LOCKOUT_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        $staff = Auth::guard('staff')->user();

        if (! $staff->is_active || ! $staff->school?->is_active) {
            Auth::guard('staff')->logout();

            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated. Please contact your school.',
            ]);
        }

        $staff->forceFill(['last_login_at' => now()])->save();

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
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * Scoped by school_id, not just the login string, because
     * staff_number/email are only unique per-school - without this, a
     * staff member at one school could be locked out by failed attempts
     * against an identically-numbered/named staff member at a completely
     * different school sharing the same IP address.
     */
    public function throttleKey(School $school): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$school->id.'|'.$this->ip());
    }
}
