<?php

namespace App\Http\Requests\Portal;

use App\Models\School;
use App\Services\PortalSchoolResolver;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const GUARDS = ['web', 'student', 'staff', 'guardian'];

    /**
     * Where the schools a person may choose between are kept, between the
     * attempt that found them and the one that picks one.
     */
    public const CHOICES_SESSION_KEY = 'portal_school_choices';

    public const CHOICE_MESSAGE = 'Your details match an account at more than one school. Choose the school you want to sign in to.';

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
            'role' => ['required', Rule::in(self::GUARDS)],
            'school_code' => ['nullable', 'string', 'max:20'],
            'school' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * The school for a Standard/Exclusive request is already known from the
     * domain (resolved by ResolveTenantFromCustomDomain before this request
     * is even validated).
     */
    public function resolvedTenant(): ?School
    {
        $school = $this->route('tenantDomain');

        return $school instanceof School ? $school : null;
    }

    /**
     * The school this sign-in is for.
     *
     * On a school's own address it is that school. On the shared address it is
     * found from the account itself, with no school code needed: see
     * PortalSchoolResolver. A school code is still honoured when one is sent.
     *
     * @throws ValidationException
     */
    public function resolveSchool(): School
    {
        if ($school = $this->resolvedTenant()) {
            return $school;
        }

        if ($this->filled('school_code')) {
            return School::where('school_code', $this->string('school_code')->toString())->first()
                ?? $this->refuse();
        }

        $this->ensureIsNotRateLimited();

        $schools = app(PortalSchoolResolver::class)->schoolsFor(
            $this->string('role')->toString(),
            $this->string('login')->toString(),
            $this->string('password')->toString(),
        );

        if ($schools->isEmpty()) {
            RateLimiter::hit($this->throttleKey(), self::LOCKOUT_DECAY_SECONDS);

            $this->refuse();
        }

        RateLimiter::clear($this->throttleKey());

        // A school picked from the list offered earlier. It is only accepted if
        // these same details, password included, open an account there, so
        // nothing about the choice is taken on trust.
        if ($this->filled('school')) {
            return $schools->firstWhere('portal_key', $this->string('school')->toString())
                ?? $this->refuse();
        }

        $active = $schools->where('is_active', true)->values();

        if ($schools->count() === 1 || $active->count() <= 1) {
            return $active->first() ?? $schools->first();
        }

        // The same person, with the same password, at more than one school.
        // They have proved who they are, so they are shown only those schools
        // and asked which one they mean.
        $this->offerSchoolChoice($active);
    }

    /**
     * Ask which of these schools the person means.
     *
     * The browser keeps the list in the session to draw it on the page.
     *
     * @param  Collection<int, School>  $schools
     *
     * @throws ValidationException
     */
    protected function offerSchoolChoice(Collection $schools): never
    {
        $this->session()->put(
            self::CHOICES_SESSION_KEY,
            $schools->mapWithKeys(fn (School $school) => [$school->portal_key => $school->name])->all(),
        );

        throw ValidationException::withMessages([
            'school' => self::CHOICE_MESSAGE,
        ]);
    }

    /**
     * @throws ValidationException
     */
    protected function refuse(): never
    {
        // The same message a wrong password produces, so nothing about the
        // response says whether the account, the school or the password was
        // the problem.
        throw ValidationException::withMessages([
            'login' => trans('auth.failed'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
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
     * Keyed on the account type, the login and the address, across every
     * school, because on this page no school has been chosen yet.
     */
    private function throttleKey(): string
    {
        return 'portal-sign-in|'.Str::transliterate($this->string('role').'|'.Str::lower($this->string('login')).'|'.$this->ip());
    }

    /**
     * Dispatches to the matching, already-tested per-guard LoginRequest's
     * own authenticate() method, reused completely unmodified via a cloned
     * request, rather than reimplementing rate-limiting/is_active/school-
     * active checks a second time for the same four guards.
     *
     * @throws ValidationException
     */
    public function authenticateAs(string $guard, School $school): void
    {
        match ($guard) {
            'web' => \App\Http\Requests\SchoolPortal\LoginRequest::createFrom($this)->authenticate($school),
            'student' => \App\Http\Requests\Student\LoginRequest::createFrom($this)->authenticate($school),
            'staff' => \App\Http\Requests\Staff\LoginRequest::createFrom($this)->authenticate($school),
            'guardian' => \App\Http\Requests\Guardian\LoginRequest::createFrom($this)->authenticate($school),
        };

        if ($this->hasSession()) {
            $this->session()->forget(self::CHOICES_SESSION_KEY);
        }
    }
}
