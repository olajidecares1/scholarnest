<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Portal\LoginRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Signing in from a mobile client.
 *
 * Extends the portal's own login request rather than restating its rules,
 * which means the API gets the per-guard credential lookup, the per-school
 * throttle key, the is_active check and the school-active check exactly as the
 * browser does - and gets them again automatically when any of those change.
 * An API that reimplemented four guards' worth of sign-in would be four more
 * places for the rules to drift apart.
 *
 * Two differences from the web form:
 *
 *   school_code is always required. On the web a Standard school is resolved
 *   from the domain the request arrived on; an API client has no domain to be
 *   recognised by, so it names its school every time.
 *
 *   The School Admin guard is not offered. There are no administrative
 *   endpoints yet, and a token that can be minted but not used is surface for
 *   nothing.
 */
class IssueTokenRequest extends LoginRequest
{
    /**
     * @var list<string>
     */
    private const API_GUARDS = ['student', 'guardian', 'staff'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'role' => ['required', Rule::in(self::API_GUARDS)],
            'school_code' => ['required', 'string', 'max:20'],

            // Named so a person can recognise the entry in a list of their
            // own sessions and revoke the one they no longer have.
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
