<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\User;
use App\Rules\NotDerivedFromIdentity;
use App\Rules\SchoolAddressAvailable;
use App\Support\SchoolAddress;
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

            // The same rule as self-registration: one school per address.
            'school_address' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
                if (! SchoolAddress::isSpecific(is_string($value) ? $value : null)) {
                    $fail('Please enter the school\'s full street address, for example "12 Allen Avenue, Ikeja, Lagos".');
                }
            }, new SchoolAddressAvailable],
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
