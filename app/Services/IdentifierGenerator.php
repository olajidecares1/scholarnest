<?php

namespace App\Services;

use App\Enums\AcademicStage;
use App\Models\ReleasedIdentifier;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Support\AcademicStageDetector;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IdentifierGenerator
{
    public const ADMISSION = 'admission';

    public const STAFF = 'staff';

    /**
     * Fallback level codes used when a class's AcademicLevel has no `code`
     * configured (or the free-text class name isn't in the catalogue at
     * all), keyed by the same AcademicStage that AcademicStageDetector
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
     *
     * NUMBERS FREED BY A DELETION ARE ISSUED AGAIN. When a student is deleted
     * their sequence is released (release()), and the next student registered
     * is given the lowest released one before the counter moves on. Only when
     * nothing has been released does the counter advance.
     *
     * The sequence is shared by every class, so a freed number is reissued
     * with the new student's own level code and the current session: a
     * Primary pupil registered after PRY-003 was deleted gets PRY-003 back,
     * a Senior Secondary student gets SEN-003.
     */
    public function nextAdmissionNumber(School $school, ?string $className): string
    {
        $this->assertSchoolCodeConfigured($school);

        $levelCode = $this->levelCodeFor($school, $className);

        return DB::transaction(function () use ($school, $levelCode) {
            $locked = School::where('id', $school->id)->lockForUpdate()->first();
            $session = $locked->current_session ?: 'NOSESSION';
            $format = fn (int $sequence) => sprintf('%s-%s-%s-%03d', $locked->school_code, $session, $levelCode, $sequence);

            $taken = $this->admissionNumbersInUse($locked);
            $inUse = $this->admissionSequencesInUse($locked, $taken);

            // A number freed by a deletion first, lowest first.
            $sequence = $this->claimReleased($locked, self::ADMISSION, $inUse, fn (int $sequence) => isset($taken[mb_strtolower($format($sequence))]));

            if ($sequence === null) {
                // Otherwise the next one up, never one somebody already holds.
                $sequence = (int) $locked->next_admission_sequence;

                while (isset($inUse[$sequence]) || isset($taken[mb_strtolower($format($sequence))])) {
                    $sequence++;
                }

                $locked->update(['next_admission_sequence' => $sequence + 1]);
            }

            return $format($sequence);
        });
    }

    /**
     * The sequence the next admission number will use, without reserving it,
     * for the preview on the Add Student form.
     */
    public function peekAdmissionSequence(School $school): int
    {
        if (! $school->school_code) {
            return (int) $school->next_admission_sequence;
        }

        return $this->lowestReleased($school, self::ADMISSION, $this->admissionSequencesInUse($school, $this->admissionNumbersInUse($school)))
            ?? (int) $school->next_admission_sequence;
    }

    /**
     * Reserves and returns the next staff ID for the school, formatted
     * {school_code}-STAFF-{sequence:03d}. Same locked-transaction guarantee
     * as nextAdmissionNumber(), and the same reuse: the Staff ID of a deleted
     * staff member is the next one issued. Nobody else's ID is ever changed.
     */
    public function nextStaffId(School $school): string
    {
        $this->assertSchoolCodeConfigured($school);

        return DB::transaction(function () use ($school) {
            $locked = School::where('id', $school->id)->lockForUpdate()->first();
            $inUse = $this->staffSequencesInUse($locked);

            $sequence = $this->claimReleased($locked, self::STAFF, $inUse);

            if ($sequence === null) {
                $sequence = (int) $locked->next_staff_sequence;

                while (isset($inUse[$sequence])) {
                    $sequence++;
                }

                $locked->update(['next_staff_sequence' => $sequence + 1]);
            }

            return sprintf('%s-STAFF-%03d', $locked->school_code, $sequence);
        });
    }

    /**
     * The sequence the next Staff ID will use, without reserving it.
     */
    public function peekStaffSequence(School $school): int
    {
        if (! $school->school_code) {
            return (int) $school->next_staff_sequence;
        }

        return $this->lowestReleased($school, self::STAFF, $this->staffSequencesInUse($school))
            ?? (int) $school->next_staff_sequence;
    }

    /**
     * Put a deleted student's admission number or staff member's Staff ID
     * back in the pool, so the next registration is given it. Called when the
     * record is deleted; numbers the school typed in its own format are not
     * part of the sequence and are ignored.
     */
    public function release(Student|Staff $record): void
    {
        $school = $record->school()->first();

        if (! $school?->school_code) {
            return;
        }

        if ($record instanceof Student) {
            $type = self::ADMISSION;
            $sequences = $this->admissionSequencesInUse($school, [mb_strtolower((string) $record->admission_number) => true]);
        } else {
            $type = self::STAFF;
            $prefix = sprintf('%s-STAFF-', $school->school_code);
            $tail = str_starts_with((string) $record->staff_number, $prefix) ? substr((string) $record->staff_number, strlen($prefix)) : '';
            $sequences = ctype_digit($tail) ? [(int) $tail => true] : [];
        }

        foreach (array_keys($sequences) as $sequence) {
            ReleasedIdentifier::query()->insertOrIgnore([
                'school_id' => $school->id,
                'type' => $type,
                'sequence' => $sequence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
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
     * Take the lowest freed sequence that nobody holds, and remove it from
     * the pool. Called inside the transaction that has the school row locked.
     *
     * @param  array<int, true>  $inUse
     * @param  (callable(int): bool)|null  $taken  whether the full number built from a sequence is held
     */
    private function claimReleased(School $school, string $type, array $inUse, ?callable $taken = null): ?int
    {
        $released = ReleasedIdentifier::query()
            ->where('school_id', $school->id)
            ->where('type', $type)
            ->orderBy('sequence')
            ->get();

        foreach ($released as $row) {
            // Held again somehow (typed in by hand, say): no longer free.
            if (isset($inUse[$row->sequence]) || ($taken !== null && $taken($row->sequence))) {
                $row->delete();

                continue;
            }

            $row->delete();

            return (int) $row->sequence;
        }

        return null;
    }

    /**
     * @param  array<int, true>  $inUse
     */
    private function lowestReleased(School $school, string $type, array $inUse): ?int
    {
        return ReleasedIdentifier::query()
            ->where('school_id', $school->id)
            ->where('type', $type)
            ->orderBy('sequence')
            ->pluck('sequence')
            ->first(fn ($sequence) => ! isset($inUse[(int) $sequence]));
    }

    /**
     * Every admission number at the school, lower-cased, as a set.
     *
     * @return array<string, true>
     */
    private function admissionNumbersInUse(School $school): array
    {
        return Student::query()
            ->where('school_id', $school->id)
            ->pluck('admission_number')
            ->mapWithKeys(fn ($number) => [mb_strtolower((string) $number) => true])
            ->all();
    }

    /**
     * The sequences held by admission numbers this system generated for the
     * school: {school_code}-{session}-{level}-{sequence}. Numbers the school
     * typed itself in another shape are not part of the sequence.
     *
     * @param  array<string, true>  $numbers
     * @return array<int, true>
     */
    private function admissionSequencesInUse(School $school, array $numbers): array
    {
        $pattern = '/^'.preg_quote(mb_strtolower((string) $school->school_code), '/').'-.+-[^-]+-(\d+)$/u';
        $sequences = [];

        foreach (array_keys($numbers) as $number) {
            if (preg_match($pattern, (string) $number, $m)) {
                $sequences[(int) $m[1]] = true;
            }
        }

        return $sequences;
    }

    /**
     * @return array<int, true>
     */
    private function staffSequencesInUse(School $school): array
    {
        $prefix = sprintf('%s-STAFF-', $school->school_code);
        $sequences = [];

        Staff::query()
            ->where('school_id', $school->id)
            ->where('staff_number', 'like', $prefix.'%')
            ->pluck('staff_number')
            ->each(function ($number) use ($prefix, &$sequences) {
                $tail = substr((string) $number, strlen($prefix));

                if (ctype_digit($tail)) {
                    $sequences[(int) $tail] = true;
                }
            });

        return $sequences;
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
