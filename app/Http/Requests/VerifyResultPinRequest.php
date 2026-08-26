<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class VerifyResultPinRequest extends FormRequest
{
    /**
     * Failed attempts allowed from one address, against one school, within the
     * decay window below.
     *
     * The counter is keyed on the ADDRESS, not on the token typed. Keying it
     * per token would give every wrong guess its own counter and never fire,
     * which is the opposite of what a limiter on a guessable credential is
     * for: the attack IS trying many different values, so many different
     * values is exactly what has to be counted.
     */
    private const MAX_ATTEMPTS = 8;

    private const LOCKOUT_DECAY_SECONDS = 900;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // The token alone. Nothing else is asked for and nothing else is used:
        // the student, examination, term and session all come from the token's
        // own bindings, server-side.
        //
        // The admission number this form used to require made things worse
        // rather than better. A wrong one produced "no student was found with
        // that admission number", which confirmed to anyone who cared exactly
        // which admission numbers were real.
        return [
            'code' => ['required', 'string', 'min:8', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Please enter the result token your school gave you.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'result token'];
    }

    /**
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
     * One counter per address, per school.
     */
    public function throttleKey(School $school): string
    {
        return 'result-token|'.$school->id.'|'.$this->ip();
    }
}
