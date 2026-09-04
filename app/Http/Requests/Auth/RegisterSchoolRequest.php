<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\NotDerivedFromIdentity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterSchoolRequest extends FormRequest
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
            'school_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],

            // Asked for on the form, so it is validated and stored rather than
            // collected and dropped. It becomes the school's billing contact
            // number, which is the only phone number a school has at the point
            // of registration.
            'phone' => ['required', 'string', 'max:30'],

            // The school name and email are the two things printed at the top
            // of the school's own website, and "Greenfield2026!" satisfies
            // every complexity rule while being the first guess anybody makes
            // against Greenfield College. Read from THIS request because at
            // registration there is no account yet - they are the values the
            // account is about to be created with.
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
                new NotDerivedFromIdentity([
                    $this->input('school_name'),
                    $this->input('email'),
                ]),
            ],

            // THE AGREEMENT IS A SERVER-SIDE RULE, and this line is the whole
            // of it. The checkbox on the form carries `required`, but that is
            // a browser convenience: it is trivially removed with developer
            // tools and absent entirely from a request made outside a browser.
            //
            // `accepted` refuses anything that is not a true value - and
            // refuses a request in which the field is missing altogether,
            // which is exactly what an unticked checkbox sends, since a
            // browser omits unchecked boxes rather than sending them as false.
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Names the three documents that exist. It used to name a "Data
            // Protection Policy", which never did - so a school reading the
            // refusal was told to accept something it could not find.
            'terms.accepted' => 'Please read and accept the Terms & Conditions, Privacy Policy and Cookie Policy to continue.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'school_name' => 'school name',
            'email' => 'school email address',
            'phone' => 'phone number',
        ];
    }
}
