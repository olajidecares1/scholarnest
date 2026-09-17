<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\School;
use App\Services\Tenancy\TenantResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Super Admin renaming a school's address, greenfield.akademicanest.com.
 *
 * Held to the same rules generation follows (School::availableSubdomain()):
 * one DNS label, letters and digits only, no platform-reserved names, and
 * unique, here as well as by the database's unique index.
 */
class UpdateSchoolSubdomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subdomain' => strtolower(trim((string) $this->input('subdomain'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var School $school */
        $school = $this->route('school');

        $reserved = array_unique([
            ...array_map('strtolower', (array) config('basic_portal.reserved_slugs', [])),
            ...TenantResolver::PLATFORM_ALIASES,
        ]);

        return [
            'subdomain' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]+$/',
                Rule::notIn($reserved),
                Rule::unique('schools', 'subdomain')->ignore($school->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subdomain.regex' => 'Use lowercase letters and numbers only, with no spaces, hyphens or dots.',
            'subdomain.not_in' => 'That address is reserved for the platform. Choose another.',
            'subdomain.unique' => 'Another school already uses that address.',
        ];
    }
}
