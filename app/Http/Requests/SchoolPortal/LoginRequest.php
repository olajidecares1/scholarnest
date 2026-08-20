<?php

namespace App\Http\Requests\SchoolPortal;

use App\Enums\UserRole;
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
     * Attempt to authenticate the request's credentials against the "web"
     * guard, scoped to School Admins of the given school.
     *
     * @throws ValidationException
     */
    public function authenticate(School $school): void
    {
        $this->ensureIsNotRateLimited();

        $field = str_contains((string) $this->string('login'), '@') ? 'email' : 'username';

        // school_id is included directly in the credentials, not checked
        // afterwards, so a School B admin's row simply fails to match here -
        // same pattern already used by Student/Guardian/Staff login.
        $credentials = [
            $field => $this->string('login')->toString(),
            'password' => $this->string('password')->toString(),
            'school_id' => $school->id,
        ];

        if (! Auth::guard('web')->attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::LOCKOUT_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        $user = Auth::guard('web')->user();

        if ($user->role !== UserRole::SchoolAdmin || ! $user->is_active || ! $school->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

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
     * Deliberately keyed by login+IP only, NOT +school_id - unlike Student/
     * Guardian/Staff, a School Admin's email/username is globally unique
     * across the whole platform, not per-school, so scoping this by school
     * would let an attacker reset their attempt budget just by retrying the
     * same credentials through a different school's portal login page.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
