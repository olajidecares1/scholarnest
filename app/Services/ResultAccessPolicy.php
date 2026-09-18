<?php

namespace App\Services;

use App\Enums\ExamTerm;
use App\Models\Examination;
use App\Models\ResultFeeClearance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Whether a student's result may be seen, and why not.
 *
 * One class, because the same question is asked from four places that have
 * nothing else in common: the token flow a Basic school's parents use, the
 * student portal, the guardian portal, and the School Admin's own screens. A
 * rule about money written separately in four places is a rule that will
 * disagree with itself, and the disagreement will be a result released to
 * someone who has not paid for it.
 *
 * The rule itself is short: a result is withheld while the student owes the
 * school money, unless the school has released that student's results for
 * that term. The school always has the final say, a bursary, a payment plan,
 * a balance the office knows is wrong, and the release is a record of who
 * decided, not a switch.
 *
 * A school with no fees on record owes nothing, so nothing is withheld. That
 * is what makes this safe to apply on every plan, including the ones with no
 * Finance module: the gate only closes on a balance the school itself raised.
 */
class ResultAccessPolicy
{
    /**
     * Releases already read, keyed by school. One request's worth.
     *
     * @var array<int, list<string>>
     */
    private array $releases = [];

    /**
     * What this student still owes across every invoice raised against them.
     *
     * Read from the invoices themselves rather than a cached total, because a
     * payment recorded a minute ago has to count.
     */
    public function outstandingBalance(Student $student): float
    {
        return $this->outstandingBalances([$student->id])[$student->id] ?? 0.0;
    }

    /**
     * What each of these students still owes, in one query.
     *
     * The screens that list a class ask this about thirty students at once,
     * and asking per student is how a page ends up running a query per row.
     * Summed in the database rather than in PHP so the answer does not depend
     * on which relations somebody remembered to eager-load.
     *
     * Deliberately NOT read from a loaded invoices relation. A Student object
     * held across a payment keeps the collection it loaded before it, and a
     * balance that reports the position from a moment ago is a result released
     * to someone who has not paid.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, float>
     */
    public function outstandingBalances(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $paid = DB::table('fee_payments')
            ->select('invoice_id', DB::raw('SUM(amount) as paid'))
            ->whereIn('invoice_id', DB::table('invoices')->select('id')->whereIn('student_id', $studentIds))
            ->groupBy('invoice_id');

        return DB::table('invoices')
            ->leftJoinSub($paid, 'paid', 'paid.invoice_id', '=', 'invoices.id')
            ->whereIn('invoices.student_id', $studentIds)
            ->groupBy('invoices.student_id')
            // A CASE, not MAX(x, 0), AND NOT GREATEST(x, 0) EITHER.
            //
            // A two-argument MAX() is SQLite's, and only SQLite's. MySQL and
            // MariaDB have MAX() as an AGGREGATE that takes exactly one
            // argument, so this was a syntax error on every production query
            // that reached it, and the tests could not see it because they run
            // on SQLite. GREATEST() is the MySQL spelling and is the mirror
            // image of the same trap: SQLite does not have it.
            //
            // CASE is in the SQL standard and behaves identically on both, so
            // this no longer depends on which database happens to be running.
            //
            // The clamp itself matters: an overpaid invoice has a negative
            // balance, and summed with the rest it would quietly pay off a
            // sibling's unpaid one and release a result that is not settled.
            ->selectRaw(
                'invoices.student_id, SUM(CASE WHEN invoices.amount - COALESCE(paid.paid, 0) > 0'
                .' THEN invoices.amount - COALESCE(paid.paid, 0) ELSE 0 END) as balance'
            )
            ->pluck('balance', 'student_id')
            ->map(fn ($balance) => round((float) $balance, 2))
            ->all();
    }

    /**
     * Which of these students' results are withheld for one examination.
     *
     * Two queries whatever the size of the class: the balances, and the
     * releases. The same rule as isLocked(), asked of many at once.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, bool>
     */
    public function lockedFor(array $studentIds, Examination $examination, int $schoolId): array
    {
        $balances = $this->outstandingBalances($studentIds);
        $releases = $this->releasesFor($schoolId);

        return collect($studentIds)
            ->mapWithKeys(fn (int $studentId) => [
                $studentId => ($balances[$studentId] ?? 0.0) > 0
                    && ! in_array("{$studentId}:{$examination->session}:{$examination->term->value}", $releases, true),
            ])
            ->all();
    }

    public function hasOutstandingFees(Student $student): bool
    {
        return $this->outstandingBalance($student) > 0;
    }

    /**
     * Has the school released this student's results for this term?
     */
    public function isReleased(Student $student, string $session, ExamTerm $term): bool
    {
        return in_array(
            "{$student->id}:{$session}:{$term->value}",
            $this->releasesFor($student->school_id),
            true,
        );
    }

    /**
     * Every release this school has granted, read once.
     *
     * A school has a handful of these, they are exceptions, not the normal
     * case, so fetching all of them once is cheaper than asking per student,
     * and it is what stops a screen listing a class from running a query per
     * row. Held for the life of this instance, which is one request.
     *
     * @return list<string>
     */
    private function releasesFor(int $schoolId): array
    {
        return $this->releases[$schoolId] ??= ResultFeeClearance::query()
            ->where('school_id', $schoolId)
            ->get(['student_id', 'session', 'term'])
            ->map(fn (ResultFeeClearance $release) => "{$release->student_id}:{$release->session}:{$release->term->value}")
            ->all();
    }

    /**
     * The decision. True means the result must not be shown, downloaded, or
     * linked to.
     */
    public function isLocked(Student $student, Examination $examination): bool
    {
        if (! $this->hasOutstandingFees($student)) {
            return false;
        }

        return ! $this->isReleased($student, $examination->session, $examination->term);
    }

    /**
     * What to tell the person in front of the screen.
     *
     * Said plainly, unlike the token refusals, which are deliberately vague.
     * The difference is who is asking: a vague token error protects against
     * someone guessing at tokens, whereas this message is only ever reached
     * by a person who has already proved they are entitled to THIS student's
     * result. Telling them the balance is the whole point, it is the one
     * thing they can act on.
     */
    public function lockMessage(Student $student, Examination $examination): ?string
    {
        if (! $this->isLocked($student, $examination)) {
            return null;
        }

        $balance = number_format($this->outstandingBalance($student), 2);

        return "This result is on hold because there is an outstanding school-fee balance of \u{20A6}{$balance}. "
            .'Please settle the balance with the school office, or contact the school if you believe this is an error. '
            .'The result becomes available as soon as the school releases it.';
    }

    /**
     * Release one student's results for one term.
     *
     * Idempotent: releasing twice is the same decision, and the record keeps
     * the first one rather than rewriting who made it.
     */
    public function release(Student $student, string $session, ExamTerm $term, ?User $releasedBy = null, ?string $reason = null): ResultFeeClearance
    {
        unset($this->releases[$student->school_id]);

        return DB::transaction(fn () => ResultFeeClearance::firstOrCreate(
            [
                'student_id' => $student->id,
                'session' => $session,
                'term' => $term->value,
            ],
            [
                'school_id' => $student->school_id,
                'released_by' => $releasedBy?->id,
                'released_at' => now(),
                'reason' => $reason,
            ],
        ));
    }

    /**
     * Withdraw a release, putting the result back behind the fee balance.
     */
    public function withdrawRelease(Student $student, string $session, ExamTerm $term): void
    {
        unset($this->releases[$student->school_id]);

        ResultFeeClearance::query()
            ->where('student_id', $student->id)
            ->where('session', $session)
            ->where('term', $term->value)
            ->delete();
    }
}
