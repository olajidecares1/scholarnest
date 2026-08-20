<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTopUpRequest extends FormRequest
{
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
            'additional_students_count' => ['required', 'integer', 'min:1'],
            // Only bank_transfer is accepted for now; Paystack is not yet integrated.
            'payment_method' => ['required', 'string', 'in:bank_transfer'],
            'receipt' => ['required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:5120'],
        ];
    }
}
