<?php

namespace App\Http\Requests\Subscriptions;

use App\Enums\PlanKey;
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

            // Basic no longer asks for the student count here: it has a step
            // of its own, and requiring it on this screen would mean the
            // school could not get to that step without first answering the
            // question it is there to ask.
            if ($plan->key === PlanKey::Standard && ! $this->filled('billing_cycle')) {
                $validator->errors()->add('billing_cycle', 'Please choose a billing cycle.');
            }
        });
    }
}
