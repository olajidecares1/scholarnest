<?php

namespace App\Http\Requests\Guardian;

use App\Models\School;
use App\Support\GuardianLoginIdentifier;
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
            'login' => ['required', 'string', 'max:255'],
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

        // A parent signs in with their Parent ID or their phone number - never
        // their email. Which of the two they typed is worked out by
        // GuardianLoginIdentifier, scoped to this school, because neither is
        // unique across schools.
        $identifier = GuardianLoginIdentifier::resolve($school, $this->string('login')->toString());

        if ($identifier->ambiguous) {
            RateLimiter::hit($this->throttleKey($school), self::LOCKOUT_DECAY_SECONDS);

            // More than one parent at this school has that phone number, so it
            // does not say who is signing in. Their Parent ID does.
            throw ValidationException::withMessages([
                'login' => 'That phone number is registered to more than one parent at this school. Please sign in with your Parent ID instead.',
            ]);
        }

        // Authenticating by primary key: the identifier has already decided
        // WHO this is, and the password check below decides whether they are
        // really them. Passing the id keeps that decision in one place rather
        // than re-running the match inside the user provider.
        $credentials = [
            'id' => $identifier->guardian?->id ?? 0,
            'password' => $this->string('password')->toString(),
            'school_id' => $school->id,
        ];

        if (! Auth::guard('guardian')->attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($school), self::LOCKOUT_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        $guardian = Auth::guard('guardian')->user();

        if (! $guardian->is_active || ! $guardian->school?->is_active) {
            Auth::guard('guardian')->logout();

            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated. Please contact your school.',
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
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * Scoped by school_id, not just the identifier, because a Parent ID and a
     * phone number are only unique per-school - without this, a parent at one
     * school could be locked out by failed attempts against an
     * identically-numbered parent at a completely different school sharing the
     * same IP address.
     *
     * Keyed on the NORMALISED phone where the input is one, so that
     * "08031234567" and "+234 803 123 4567" count against the same allowance.
     * Keyed on the raw input otherwise. Without that, reformatting the same
     * number would hand out a fresh five attempts each time.
     */
    public function throttleKey(School $school): string
    {
        $login = $this->string('login')->toString();
        $identifier = GuardianLoginIdentifier::normalisePhone($login) ?? Str::lower($login);

        return Str::transliterate($identifier.'|'.$school->id.'|'.$this->ip());
    }
}
