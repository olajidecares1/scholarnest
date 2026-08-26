<?php

namespace App\Support;

use App\Http\Resources\Api\V1\GuardianResource;
use App\Http\Resources\Api\V1\SchoolResource;
use App\Http\Resources\Api\V1\StaffResource;
use App\Http\Resources\Api\V1\StudentResource;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

/**
 * What kind of account is behind this token, and how to describe it.
 *
 * Sanctum's tokens are polymorphic, so `$request->user()` on the API can be a
 * Student, a Guardian or a Staff member. Three types means three matches, and
 * three matches written in three controllers is how one of them ends up
 * missing a case. They are written here once.
 *
 * The role string is the same word the portal login uses - student, guardian,
 * staff - so a client that knows how to sign in already knows this vocabulary.
 */
class ApiAccount
{
    /**
     * @return 'student'|'guardian'|'staff'
     */
    public static function role(Model $account): string
    {
        return match (true) {
            $account instanceof Student => 'student',
            $account instanceof Guardian => 'guardian',
            $account instanceof Staff => 'staff',
            default => throw new InvalidArgumentException(
                'No API role for '.$account::class.'. Add one here rather than at the call site.'
            ),
        };
    }

    public static function resource(Model $account): JsonResource
    {
        return match (self::role($account)) {
            'student' => new StudentResource($account),
            'guardian' => new GuardianResource($account),
            'staff' => new StaffResource($account),
        };
    }

    /**
     * The name to write in an audit entry.
     *
     * Passed explicitly wherever it is used, because AuditLog falls back to
     * the web guard's user and an API request has none - without this every
     * token event would be attributed to "System".
     */
    public static function displayName(Model $account): string
    {
        return match (self::role($account)) {
            'guardian' => $account->name,
            default => trim($account->first_name.' '.$account->last_name),
        };
    }

    /**
     * The account block returned beside a freshly issued token, and by /me.
     *
     * @return array<string, mixed>
     */
    public static function describe(Model $account, ?string $role = null): array
    {
        return [
            'role' => $role ?? self::role($account),
            'profile' => self::resource($account),
            'school' => new SchoolResource($account->school),
        ];
    }
}
