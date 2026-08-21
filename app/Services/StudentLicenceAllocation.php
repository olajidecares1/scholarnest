<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The single authority on how many students a Basic-plan school may have.
 *
 * A Basic school is billed per student, so its capacity is exactly the number
 * of student licences the Super Admin has allocated after verifying payment.
 * Standard and Exclusive are flat-fee and uncapped, which is why every method
 * here returns null for them rather than a number.
 *
 * Everything that could push a school over its allocation goes through
 * withCapacity(). That matters more than it sounds: the limit is not one check
 * in one controller, it is a rule about the whole system, and there are at
 * least two ways to break it that a single check misses -
 *
 *   Two requests at once. Counting students and then creating one is two
 *   separate statements. Two simultaneous submissions can both count 99
 *   against a limit of 100 and both create, leaving 101. A double-click is
 *   enough to do it by accident.
 *
 *   Reactivating. A school at 100/100 can deactivate a student, add a new one,
 *   then reactivate the old one - 101 active students, with every individual
 *   step passing a naive check.
 *
 * withCapacity() closes both by taking a database lock and re-counting inside
 * it, and by being used on the reactivation path as well as on creation.
 */
class StudentLicenceAllocation
{
    /**
     * How many student licences this school has been allocated, or null if its
     * plan is not sold per student.
     */
    public function allocated(School $school): ?int
    {
        return $school->studentSlotLimit();
    }

    /**
     * How many licences are in use.
     *
     * Active students only: deactivating a student who has left releases their
     * licence, which is what makes the per-student price fair across a year.
     */
    public function used(School $school): int
    {
        return $school->students()->where('is_active', true)->count();
    }

    /**
     * Licences still available, or null when the plan is uncapped. Never
     * negative - an over-allocation caused by a plan change reads as 0 rather
     * than as a negative number that would confuse the interface.
     */
    public function remaining(School $school): ?int
    {
        $allocated = $this->allocated($school);

        if ($allocated === null) {
            return null;
        }

        return max(0, $allocated - $this->used($school));
    }

    /**
     * Is this school out of licences right now?
     */
    public function isExhausted(School $school): bool
    {
        return $this->remaining($school) === 0;
    }

    /**
     * Should the interface warn that capacity is nearly gone?
     */
    public function isRunningLow(School $school): bool
    {
        $remaining = $this->remaining($school);
        $allocated = $this->allocated($school);

        if ($remaining === null || $allocated === null || $remaining === 0) {
            return false;
        }

        // Within a tenth of the allocation, or five licences, whichever is
        // larger - so a 30-student school is warned at 5 rather than at 3.
        return $remaining <= max(5, (int) ceil($allocated * 0.1));
    }

    /**
     * Run $work only if the school has a spare licence, holding a lock for the
     * whole operation so nothing can slip in alongside it.
     *
     * Returns whatever $work returns, or null if there was no capacity. The
     * caller decides what to tell the user; this class only decides whether it
     * is allowed.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $work
     * @return TReturn|null
     */
    public function withCapacity(School $school, Closure $work): mixed
    {
        return DB::transaction(function () use ($school, $work) {
            $allocated = $this->allocated($school);

            // Uncapped plan: nothing to check.
            if ($allocated === null) {
                return $work();
            }

            // The lock is taken on the students table for THIS school, so two
            // concurrent creations for the same school queue up rather than
            // both reading a stale count. Counting outside a lock is what makes
            // the naive version wrong.
            $used = Student::query()
                ->where('school_id', $school->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->count();

            if ($used >= $allocated) {
                return null;
            }

            return $work();
        });
    }

    /**
     * The message shown when a school has run out.
     *
     * Kept here so the wording is identical wherever the limit is hit, rather
     * than drifting between the create form and the reactivate button.
     */
    public function limitReachedMessage(School $school): string
    {
        $allocated = $this->allocated($school) ?? 0;
        $used = $this->used($school);

        return "Student Limit Reached. You have reached the maximum number of students included in your current Basic Plan allocation. "
            ."You currently have {$used} out of {$allocated} student licences in use. "
            .'To add more students, please make an additional payment and submit your payment receipt for approval.';
    }
}
