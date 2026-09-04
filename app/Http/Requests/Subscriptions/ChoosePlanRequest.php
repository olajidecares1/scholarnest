<?php

namespace App\Http\Requests\Subscriptions;

use App\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChoosePlanRequest extends FormRequest
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
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,per_term'],
            'students_count' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $plan = Plan::find($this->integer('plan_id'));

            if (! $plan) {
                return;
            }

            // Coming soon, and refused here rather than only hidden on the
            // page. A disabled radio button is a suggestion; this is the rule.
            if (! $plan->key->isAvailableToSubscribe()) {
                $validator->errors()->add(
                    'plan_id',
                    $plan->key->label().' is coming soon and cannot be subscribed to yet.',
                );

                return;
            }

            // No billing-cycle check any more. Basic and Standard are both
            // priced per student and both ask the quantity on a step of their
            // own; requiring an answer on this screen would mean a school
            // could not reach the step that asks the question.
        });
    }
}
