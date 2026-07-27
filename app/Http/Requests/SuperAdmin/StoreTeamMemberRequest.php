<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreTeamMemberRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'admin_role_id' => ['nullable', 'integer', 'exists:'.AdminRole::class.',id'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
