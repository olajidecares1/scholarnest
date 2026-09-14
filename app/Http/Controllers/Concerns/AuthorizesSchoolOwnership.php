<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * "Is this record mine?", asked once, answered in one place.
 *
 * This is the most repeated line in the application. Forty-five controller
 * methods each carried their own copy of
 *
 *     $this->authorizeSchoolOwnership($thing);
 *
 * and it is the line that stands between one school and another school's
 * pupils. Forty-five copies of a rule is forty-five chances to write it
 * slightly differently, and the forty-sixth is the one somebody forgets.
 *
 * The check is also stricter here than the copies were. Every copy compared
 * two values for equality, which passes when BOTH are null, an account whose
 * school has been deleted, looking at a record whose school_id was never set.
 * Vanishingly unlikely, and a 403 is the right answer to it either way.
 */
trait AuthorizesSchoolOwnership
{
    /**
     * Refuse the request unless every record belongs to the acting school.
     *
     * Takes several because the alternative is several calls in a row, and a
     * list is easier to read than a stack of near-identical lines.
     */
    protected function authorizeSchoolOwnership(?Model ...$records): void
    {
        $schoolId = auth()->user()?->school_id;

        // No school of one's own is not a licence to see everybody's.
        abort_if($schoolId === null, 403);

        foreach ($records as $record) {
            abort_unless($record?->school_id === $schoolId, 403);
        }
    }
}
