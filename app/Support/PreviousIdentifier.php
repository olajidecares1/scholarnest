<?php

namespace App\Support;

use App\Models\IdentifierChange;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Services\IdentifierGenerator;

/**
 * An admission number or Staff ID somebody used to have, before the school
 * regenerated its numbers (see App\Services\IdentifierRegenerator).
 *
 * Old numbers keep working wherever a person types their number in: signing
 * in, checking a result. A number somebody holds TODAY always wins, so an old
 * number can never take a person to someone else's account.
 */
class PreviousIdentifier
{
    /**
     * The record that used to be known by this number at the school, when
     * nobody holds it now.
     */
    public static function holder(School|int $school, string $type, string $number): Student|Staff|null
    {
        $schoolId = $school instanceof School ? $school->id : $school;
        $number = trim($number);

        if ($number === '' || str_contains($number, '@')) {
            return null;
        }

        $model = $type === IdentifierGenerator::ADMISSION ? Student::class : Staff::class;
        $column = $type === IdentifierGenerator::ADMISSION ? 'admission_number' : 'staff_number';

        $heldNow = $model::query()
            ->where('school_id', $schoolId)
            ->whereRaw("LOWER({$column}) = ?", [mb_strtolower($number)])
            ->exists();

        if ($heldNow) {
            return null;
        }

        $holderId = IdentifierChange::holderFor($schoolId, $type, $number);

        return $holderId === null ? null : $model::query()->where('school_id', $schoolId)->find($holderId);
    }

    /**
     * The number that person goes by now, or null.
     */
    public static function current(School|int $school, string $type, string $number): ?string
    {
        $holder = static::holder($school, $type, $number);

        if ($holder === null) {
            return null;
        }

        return (string) ($holder instanceof Student ? $holder->admission_number : $holder->staff_number);
    }
}
