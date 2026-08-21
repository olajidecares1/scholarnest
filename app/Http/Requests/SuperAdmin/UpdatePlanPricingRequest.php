<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanPricingRequest extends FormRequest
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
        // Nullable because not every plan uses every field: Basic is priced per
        // student and has no monthly rate, Standard and Exclusive are the other
        // way round. min:0 rather than min:1 so a plan can legitimately be made
        // free without having to be deleted.
        return [
            'price_per_student_per_term' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_monthly' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'price_per_term' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'price_per_student_per_term' => 'price per student, per term',
            'price_monthly' => 'monthly price',
            'price_per_term' => 'price per term',
        ];
    }
}
