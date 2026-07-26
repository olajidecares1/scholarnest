<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BillingDetailsRequest extends FormRequest
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
            'billing_contact_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'string', 'email', 'max:255'],
            'billing_phone' => ['required', 'string', 'max:30'],
            'billing_address' => ['required', 'string', 'max:255'],
        ];
    }
}
