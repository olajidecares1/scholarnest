<?php

namespace App\Http\Requests\Subscriptions;

use App\Services\AvailablePaymentMethods;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentMethodRequest extends FormRequest
{
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
        $available = app(AvailablePaymentMethods::class);

        return [
            // The list comes from what the AkademicNest Team has enabled, not from
            // a hard-coded "in:bank_transfer". A method turned off must be
            // refused HERE - the brief is explicit that hiding the radio while
            // leaving the endpoint open is not the same thing, and it is not:
            // the form is a suggestion and this is the rule.
            'payment_method' => ['required', 'string', Rule::in($available->keys())],

            'receipt' => ['required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.in' => 'That payment method is not currently available. Please choose one of the options shown.',
        ];
    }
}
