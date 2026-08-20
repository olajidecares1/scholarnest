<?php

namespace App\Services;

use App\Enums\AcademicStage;
use App\Models\School;
use App\Models\SchoolClass;
use App\Support\AcademicStageDetector;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IdentifierGenerator
{
    /**
     * Fallback level codes used when a class's AcademicLevel has no `code`
     * configured (or the free-text class name isn't in the catalogue at
     * all) - keyed by the same AcademicStage that AcademicStageDetector
     * already infers from a class name elsewhere in the app, so generation
     * still produces a sensible code out of the box before an admin has
     * touched their level codes.
     */
    private const STAGE_FALLBACK_CODES = [
        'early_years' => 'NUR',
        'primary' => 'PRY',
        'junior_secondary' => 'JUR',
        'senior_secondary' => 'SEN',
    ];

    private const GENERIC_LEVEL_CODE = 'GEN';

    /**
     * Reserves and returns the next admission number for the school,
     * formatted {school_code}-{session}-{levelCode}-{sequence:03d}. Locks
     * the school row for the duration of the transaction so two concurrent
     * requests can never reserve the same sequence number.
     */
    public function nextAdmissionNumber(School $school, ?string $className): string
    {
        $this->assertSchoolCodeConfigured($school);

        $levelCode = $this->levelCodeFor($school, $className);

        return DB::transaction(function () use ($school, $levelCode) {
            $locked = School::where('id', $school->id)->lockForUpdate()->first();
            $sequence = $locked->next_admission_sequence;
            $locked->increment('next_admission_sequence');

            $session = $locked->current_session ?: 'NOSESSION';

            return sprintf('%s-%s-%s-%03d', $locked->school_code, $session, $levelCode, $sequence);
        });
    }

    /**
     * Reserves and returns the next staff ID for the school, formatted
     * {school_code}-STAFF-{sequence:03d}. Same locked-transaction guarantee
     * as nextAdmissionNumber().
     */
    public function nextStaffId(School $school): string
    {
        $this->assertSchoolCodeConfigured($school);

        return DB::transaction(function () use ($school) {
            $locked = School::where('id', $school->id)->lockForUpdate()->first();
            $sequence = $locked->next_staff_sequence;
            $locked->increment('next_staff_sequence');

            return sprintf('%s-STAFF-%03d', $locked->school_code, $sequence);
        });
    }

    private function assertSchoolCodeConfigured(School $school): void
    {
        if (! $school->school_code) {
            throw new RuntimeException('Cannot auto-generate an identifier before the school has a school code configured.');
        }
    }

    private function levelCodeFor(School $school, ?string $className): string
    {
        if ($className) {
            $class = SchoolClass::where('school_id', $school->id)
                ->where('name', $className)
                ->with('academicLevel')
                ->first();

            if ($class?->academicLevel?->code) {
                return $class->academicLevel->code;
            }
        }

        $stage = AcademicStageDetector::detect($className);

        if ($stage instanceof AcademicStage) {
            return self::STAGE_FALLBACK_CODES[$stage->value] ?? self::GENERIC_LEVEL_CODE;
        }

        return self::GENERIC_LEVEL_CODE;
    }
}
