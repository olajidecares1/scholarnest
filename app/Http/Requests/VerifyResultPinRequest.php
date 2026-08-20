<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerifyResultPinRequest extends FormRequest
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
            'code' => ['required', 'string'],
            'admission_number' => ['required', 'string'],
        ];
    }

    /**
     * Ensure the request is not rate limited.
     *
     * Mirrors the same school_id-scoped throttle pattern used for portal
     * logins - a PIN is a credential too, and brute-forcing it should be
     * exactly as difficult as brute-forcing a password.
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
            'code' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function hit(School $school): void
    {
        RateLimiter::hit($this->throttleKey($school), self::LOCKOUT_DECAY_SECONDS);
    }

    public function clearLimiter(School $school): void
    {
        RateLimiter::clear($this->throttleKey($school));
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(School $school): string
    {
        return Str::transliterate(Str::lower($this->string('code')).'|'.$school->id.'|'.$this->ip());
    }
}
