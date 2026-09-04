<?php

namespace App\Http\Requests\Portal;

use App\Models\School;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const GUARDS = ['web', 'student', 'staff', 'guardian'];

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
            'role' => ['required', Rule::in(self::GUARDS)],
            'school_code' => [$this->resolvedTenant() ? 'nullable' : 'required', 'string', 'max:20'],
        ];
    }

    /**
     * The school for a Standard/Exclusive request is already known from the
     * domain (resolved by ResolveTenantFromCustomDomain before this request
     * is even validated) - only a Basic-plan request on the shared default
     * host needs the school_code field to disambiguate.
     */
    public function resolvedTenant(): ?School
    {
        $school = $this->route('tenantDomain');

        return $school instanceof School ? $school : null;
    }

    /**
     * @throws ValidationException
     */
    public function resolveSchool(): School
    {
        if ($school = $this->resolvedTenant()) {
            return $school;
        }

        $school = School::where('school_code', $this->string('school_code')->toString())->first();

        if (! $school) {
            // Deliberately the same generic message a wrong password
            // produces below - a bad school code must not read any
            // differently from a bad password to whoever is trying it.
            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        return $school;
    }

    /**
     * Dispatches to the matching, already-tested per-guard LoginRequest's
     * own authenticate() method - reused completely unmodified via a cloned
     * request, rather than reimplementing rate-limiting/is_active/school-
     * active checks a second time for the same four guards.
     *
     * @throws ValidationException
     */
    public function authenticateAs(string $guard, School $school): void
    {
        // Guardian\LoginRequest used to be the outlier of the four, taking a
        // field named "email" while the rest took "login". It now takes a
        // Parent ID or a phone number under the same "login" name as the
        // others, so the bridge that used to be needed here is gone.
        match ($guard) {
            'web' => \App\Http\Requests\SchoolPortal\LoginRequest::createFrom($this)->authenticate($school),
            'student' => \App\Http\Requests\Student\LoginRequest::createFrom($this)->authenticate($school),
            'staff' => \App\Http\Requests\Staff\LoginRequest::createFrom($this)->authenticate($school),
            'guardian' => \App\Http\Requests\Guardian\LoginRequest::createFrom($this)->authenticate($school),
        };
    }
}
