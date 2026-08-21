<?php

namespace App\Http\Requests\Portal\Basic;

use Illuminate\Foundation\Http\FormRequest;

class FindSchoolRequest extends FormRequest
{
    /**
     * The token in the URL is what authorises reaching this form at all, and
     * ValidateBasicPortalToken has already checked it before we get here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // 120 matches the schools.name column, so nothing longer could
            // ever match a real school anyway.
            'school' => ['required', 'string', 'min:2', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'school.required' => 'Please enter your school name.',
            'school.min' => 'Please enter at least two characters of your school name.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['school' => 'school name'];
    }

    /**
     * What the person actually typed, tidied up.
     */
    public function searchTerm(): string
    {
        return trim($this->string('school')->toString());
    }
}
