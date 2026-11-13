<?php

namespace App\Http\Requests\Subscriptions;

use App\Services\AvailablePaymentMethods;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentMethodRequest extends FormRequest
{
    /** The largest receipt accepted, in kilobytes; the page says 5MB. */
    public const MAX_KILOBYTES = 5120;

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
            // refused HERE, the brief is explicit that hiding the radio while
            // leaving the endpoint open is not the same thing, and it is not:
            // the form is a suggestion and this is the rule.
            'payment_method' => ['required', 'string', Rule::in($available->keys())],

            // Both the name and the content must say PNG, JPG or PDF:
            // "extensions" checks the name the browser sent, "mimes" what the
            // bytes really are, so a script renamed receipt.png is refused
            // by one and a PNG renamed receipt.php by the other. Images are
            // then re-encoded before they are stored (ReceiptUploadService),
            // which leaves nothing but pixels behind.
            'receipt' => ['required', 'file', 'extensions:png,jpg,jpeg,pdf', 'mimes:png,jpg,jpeg,pdf', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.in' => 'That payment method is not currently available. Please choose one of the options shown.',
            'payment_method.required' => 'Please choose how you paid.',
            'receipt.required' => 'Please choose your payment receipt to upload.',
            'receipt.uploaded' => 'Your receipt did not finish uploading. It may be larger than 5MB, or the connection dropped. Please try again, or upload a screenshot of the receipt instead of a photo.',
            'receipt.file' => 'Your receipt did not finish uploading. Please try again.',
            'receipt.extensions' => 'The receipt must be a PNG, JPG or PDF file.',
            'receipt.mimes' => 'The receipt must be a PNG, JPG or PDF file. This file is not really one of those, even if its name says it is.',
            'receipt.max' => 'The receipt must be 5MB or smaller. A screenshot of the receipt is usually much smaller than a photo.',
        ];
    }
}
