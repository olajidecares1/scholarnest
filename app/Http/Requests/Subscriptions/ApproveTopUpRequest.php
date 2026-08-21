<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Super Admin's decision on a student-licence top-up.
 *
 * The number here - not the number the school requested - is what gets added
 * to the subscription. The two are allowed to differ, because the Super Admin
 * verifies the receipt against the amount actually paid, and a school that
 * asked for 50 students but paid for 40 must receive 40.
 */
class ApproveTopUpRequest extends FormRequest
{
    /**
     * Reaching this route already requires the super_admin middleware.
     */
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
            // Required with no default. Approving must be a deliberate entry of
            // a number, not a click that silently accepts whatever the school
            // typed - that is the whole point of the rule.
            //
            // Capped at 100,000 so a slipped keystroke cannot hand a school an
            // allocation nobody intended.
            'approved_students_count' => ['required', 'integer', 'min:1', 'max:100000'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'approved_students_count.required' => 'Enter the number of student licences this payment covers.',
            'approved_students_count.min' => 'An approved top-up must allocate at least one student licence.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['approved_students_count' => 'number of student licences'];
    }
}
