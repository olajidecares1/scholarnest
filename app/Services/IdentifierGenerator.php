<?php

namespace App\Services;

use App\Enums\AcademicStage;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
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

    /**
     * Reserves and returns the next parent/guardian ID, formatted
     * {school_code}-PARENT-{sequence:03d}.
     */
    public function nextGuardianId(School $school): string
    {
        $this->assertSchoolCodeConfigured($school);

        return DB::transaction(function () use ($school) {
            $locked = School::where('id', $school->id)->lockForUpdate()->first();
            $sequence = $locked->next_guardian_sequence;
            $locked->increment('next_guardian_sequence');

            return sprintf('%s-PARENT-%03d', $locked->school_code, $sequence);
        });
    }

    /**
     * Close the gap a deleted staff member leaves in the numbering.
     *
     * Asked for explicitly: deleting STAFF-001 must make STAFF-002 into
     * STAFF-001, and so on down the list, so the sequence has no holes in it.
     *
     * This renames people's LOGIN IDENTIFIERS, which is worth being clear
     * about rather than burying:
     *
     *   Anyone whose ID moves can no longer sign in with the one they were
     *   given. Their new ID has to be passed on to them - the staff page
     *   shows it, and the WhatsApp share is there for exactly this.
     *
     *   Anything printed or recorded elsewhere that quotes the old ID - an ID
     *   card, a payslip reference, a note in a file - now disagrees with the
     *   system. The audit log records every move so the two can be
     *   reconciled.
     *
     * Done in two passes, because renaming 003 to 002 while a 002 still exists
     * would collide with the unique index. Everything moves to a temporary
     * name first, then to its final one. The whole thing is one transaction
     * with the school row locked, so a concurrent deletion cannot interleave
     * and produce two people holding one number.
     *
     * @return array<string, string> old ID => new ID, for the ones that moved
     */
    public function resequenceStaffIds(School $school): array
    {
        $this->assertSchoolCodeConfigured($school);

        return DB::transaction(function () use ($school) {
            $locked = School::where('id', $school->id)->lockForUpdate()->first();

            $prefix = sprintf('%s-STAFF-', $locked->school_code);

            // Only the IDs this system generated. A school that entered its
            // own numbering has a reason for it, and rewriting it would be
            // this feature reaching past what it was asked to do.
            $members = Staff::query()
                ->where('school_id', $locked->id)
                ->where('staff_number', 'like', $prefix.'%')
                ->orderBy('id')
                ->get(['id', 'staff_number'])

                // Sorted in PHP rather than by the database: extracting the
                // number out of the string needs a different function on
                // every driver, and this list is one school's staff.
                ->sortBy(fn (Staff $member) => (int) substr($member->staff_number, strlen($prefix)))
                ->values();

            $moves = [];
            $sequence = 1;

            foreach ($members as $member) {
                $target = sprintf('%s%03d', $prefix, $sequence);

                if ($member->staff_number !== $target) {
                    $moves[$member->staff_number] = $target;
                }

                $sequence++;
            }

            if ($moves !== []) {
                // Pass one: out of the way of the unique index.
                foreach ($members as $member) {
                    if (isset($moves[$member->staff_number])) {
                        Staff::where('id', $member->id)->update([
                            'staff_number' => 'RESEQ-'.$member->id,
                        ]);
                    }
                }

                // Pass two: into place.
                $sequence = 1;

                foreach ($members as $member) {
                    Staff::where('id', $member->id)->update([
                        'staff_number' => sprintf('%s%03d', $prefix, $sequence),
                    ]);

                    $sequence++;
                }
            }

            // The next new hire takes the number after the last one in use,
            // so the sequence stays closed rather than resuming past the gap.
            $locked->update(['next_staff_sequence' => $members->count() + 1]);

            return $moves;
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
