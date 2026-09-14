<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Step one of checking a result: naming the pupil.
 *
 * This step is the reason the limiter below is stricter than the token one, and
 * why it counts successes as well as failures.
 *
 * Confirming a pupil's name, photo and class from an admission number alone,
 * before any token, means the school's public result address can answer the
 * question "who is admission number 004?". Admission numbers run in sequence,
 * so anyone who wanted to could walk the whole register. A limiter on failures
 * would not touch that: every request in such a walk SUCCEEDS. So successful
 * lookups are counted too, and the ceiling is set where a family checking a
 * result never reaches it and a harvest stops early.
 */
class IdentifyStudentRequest extends FormRequest
{
    /**
     * Wrong numbers allowed from one address, against one school, per window.
     */
    private const MAX_FAILURES = 8;

    private const FAILURE_DECAY_SECONDS = 900;

    /**
     * Successful lookups allowed from one address, against one school, per
     * hour. Generous enough for a household, or a school office helping
     * several families from one connection, and far below a register.
     */
    private const MAX_LOOKUPS = 20;

    private const LOOKUP_DECAY_SECONDS = 3600;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'admission_number' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'admission_number.required' => 'Please enter the School ID or Admission Number.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['admission_number' => 'School ID / Admission Number'];
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(School $school): void
    {
        foreach ([$this->failureKey($school) => self::MAX_FAILURES, $this->lookupKey($school) => self::MAX_LOOKUPS] as $key => $limit) {
            if (! RateLimiter::tooManyAttempts($key, $limit)) {
                continue;
            }

            event(new Lockout($this));

            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'admission_number' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }
    }

    public function recordFailure(School $school): void
    {
        RateLimiter::hit($this->failureKey($school), self::FAILURE_DECAY_SECONDS);
    }

    /**
     * Counted even though nothing went wrong, see the note on this class.
     */
    public function recordLookup(School $school): void
    {
        RateLimiter::hit($this->lookupKey($school), self::LOOKUP_DECAY_SECONDS);
    }

    public function clearFailures(School $school): void
    {
        RateLimiter::clear($this->failureKey($school));
    }

    private function failureKey(School $school): string
    {
        return 'result-identify-failed|'.$school->id.'|'.$this->ip();
    }

    private function lookupKey(School $school): string
    {
        return 'result-identify-lookup|'.$school->id.'|'.$this->ip();
    }
}
