<?php

namespace App\Rules;

use App\Models\School;
use App\Support\SchoolAddress;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The address must not already belong to another registered school.
 *
 * Says only that the address is taken. Never which school holds it, its
 * email, its name or anything else about it: the person registering may not
 * be who they say, and a duplicate check must not become a way of looking up
 * other schools' details.
 */
class SchoolAddressAvailable implements ValidationRule
{
    public const MESSAGE = 'A school is already registered at this address, and each school location can only have one account. '
        .'If this is your school, please sign in to your school\'s portal instead. '
        .'If you believe this is a mistake, contact AkademicNest support.';

    public function __construct(private readonly ?int $exceptSchoolId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $fingerprint = SchoolAddress::fingerprint(is_string($value) ? $value : null);

        if ($fingerprint === null) {
            return;
        }

        $taken = School::query()
            ->where('address_fingerprint', $fingerprint)
            ->when($this->exceptSchoolId, fn ($query) => $query->whereKeyNot($this->exceptSchoolId))
            ->exists();

        if ($taken) {
            $fail(self::MESSAGE);
        }
    }
}
