<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
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
            'site_name' => ['required', 'string', 'max:255'],
            'support_email' => ['nullable', 'string', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'notification_from_name' => ['required', 'string', 'max:255'],
            'notification_from_email' => ['nullable', 'string', 'email', 'max:255'],
            'maintenance_mode' => ['boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
