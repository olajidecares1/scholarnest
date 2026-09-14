<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Portal\LoginRequest;
use App\Models\School;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Signing in from a mobile client.
 *
 * Extends the portal's own login request rather than restating its rules,
 * which means the API gets the per-guard credential lookup, the per-school
 * throttle key, the is_active check and the school-active check exactly as the
 * browser does, and gets them again automatically when any of those change.
 * An API that reimplemented four guards' worth of sign-in would be four more
 * places for the rules to drift apart.
 *
 * No school code is needed. As on the shared web sign-in, the school is found
 * from the account itself. Two differences from the web form:
 *
 *   When the same details open accounts at more than one school, the choice is
 *   returned in the response rather than kept in a session, because an API
 *   client has none. The client sends the same details again with "school".
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

            // Named so a person can recognise the entry in a list of their
            // own sessions and revoke the one they no longer have.
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @param  Collection<int, School>  $schools
     */
    protected function offerSchoolChoice(Collection $schools): never
    {
        throw new HttpResponseException(response()->json([
            'message' => self::CHOICE_MESSAGE,
            'errors' => ['school' => [self::CHOICE_MESSAGE]],
            'schools' => $schools
                ->map(fn (School $school) => ['school' => $school->portal_key, 'name' => $school->name])
                ->values(),
        ], 422));
    }
}
