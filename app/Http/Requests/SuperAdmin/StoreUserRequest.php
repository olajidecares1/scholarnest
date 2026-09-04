<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\School;
use App\Models\User;
use App\Rules\NotDerivedFromIdentity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
            'school_id' => ['required', 'integer', 'exists:'.School::class.',id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            'password' => ['required', 'confirmed', Password::defaults(), new NotDerivedFromIdentity([
                $this->input('name'),
                $this->input('email'),
                School::find($this->input('school_id'))?->name,
            ])],
        ];
    }
}
