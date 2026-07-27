<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\AdminRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminRoleRequest extends FormRequest
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
        $roleId = $this->route('role')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(AdminRole::class, 'name')->ignore($roleId)],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::in(array_keys(AdminRole::PERMISSIONS))],
        ];
    }
}
