<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\User;
use App\Rules\NotDerivedFromIdentity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreSchoolRequest extends FormRequest
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
            'school_name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            'password' => ['required', 'confirmed', Password::defaults(), new NotDerivedFromIdentity([
                $this->input('school_name'),
                $this->input('admin_name'),
                $this->input('admin_email'),
            ])],
        ];
    }
}
