<?php

namespace App\Services;

use App\Enums\SubscriptionTopUpStatus;
use App\Models\School;
use App\Models\Student;
use App\Models\SubscriptionTopUp;
use Closure;
use Illuminate\Database\Eloquent\Collection;
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
 * least two ways to break it that a single check misses,
 *
 *   Two requests at once. Counting students and then creating one is two
 *   separate statements. Two simultaneous submissions can both count 99
 *   against a limit of 100 and both create, leaving 101. A double-click is
 *   enough to do it by accident.
 *
 *   Reactivating. A school at 100/100 can deactivate a student, add a new one,
 *   then reactivate the old one, 101 active students, with every individual
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
     * negative, an over-allocation caused by a plan change reads as 0 rather
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
     * Every capacity request this school has ever made, oldest first.
     *
     * History, not allowances. Each row records what a request asked for and
     * what the decision moved the single cumulative figure from and to; none
     * of them is a separate pool the school can draw on.
     *
     * @return Collection<int, SubscriptionTopUp>
     */
    public function requestHistory(School $school): Collection
    {
        return SubscriptionTopUp::query()
            ->whereIn('subscription_id', $school->subscriptions()->select('id'))
            ->with('verifiedBy')
            ->orderBy('id')
            ->get();
    }

    /**
     * What the school was allocated at activation, before any additional
     * request, the figure every approved addition was added to.
     *
     * Read back off the first request's "previous" snapshot rather than stored
     * separately, so it cannot drift from the additions that followed it.
     */
    public function initial(School $school): ?int
    {
        $allocated = $this->allocated($school);

        if ($allocated === null) {
            return null;
        }

        return $this->requestHistory($school)->first()?->previous_students_count ?? $allocated;
    }

    /**
     * How many additional spaces are sitting in requests awaiting a decision.
     *
     * Deliberately NOT part of allocated(): a submitted payment is a request,
     * and a request buys nothing until a Super Admin has approved it. This
     * figure exists so the school can be told its request is being reviewed
     * without that number ever counting towards what it may use.
     */
    public function pendingRequests(School $school): int
    {
        return (int) SubscriptionTopUp::query()
            ->whereIn('subscription_id', $school->subscriptions()->select('id'))
            ->where('status', SubscriptionTopUpStatus::PendingVerification)
            ->sum('additional_students_count');
    }

    /**
     * The whole capacity picture for one school, or null when its plan is not
     * sold per student.
     *
     * One method because these figures are only ever meaningful together, and
     * because every screen that shows them must show the same ones. Total is
     * the initial allocation plus every APPROVED addition, and remaining is
     * that total minus the students actually on record, both read from the
     * database at the moment of asking, never from anything the browser sent.
     *
     * @return array{initial: int, allocated: int, used: int, remaining: int, pending: int, runningLow: bool}|null
     */
    public function summary(School $school): ?array
    {
        $allocated = $this->allocated($school);

        if ($allocated === null) {
            return null;
        }

        return [
            'initial' => $this->initial($school),
            'allocated' => $allocated,
            'used' => $this->used($school),
            'remaining' => $this->remaining($school),
            'pending' => $this->pendingRequests($school),
            'runningLow' => $this->isRunningLow($school),
        ];
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
        // larger, so a 30-student school is warned at 5 rather than at 3.
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
        return $this->withCapacityFor($school, 1, $work);
    }

    /**
     * withCapacity() for several students at once, a bulk import. Either the
     * school has room for every one of them and $work runs, or it does not
     * and nothing is created: an import that stopped half way at the limit
     * would leave the school guessing which pupils made it in.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $work
     * @return TReturn|null
     */
    public function withCapacityFor(School $school, int $count, Closure $work): mixed
    {
        return DB::transaction(function () use ($school, $count, $work) {
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

            if ($used + max(1, $count) > $allocated) {
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

        return 'Student/Pupil Capacity Reached. You have used all '.number_format($allocated).' approved student/pupil spaces '
            ."({$used} out of {$allocated} in use). "
            .'To register more students/pupils, please make an additional payment and submit your payment receipt for approval.';
    }
}
