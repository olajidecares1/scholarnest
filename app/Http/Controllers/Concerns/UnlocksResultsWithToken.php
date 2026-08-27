<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Examination;
use App\Models\ResultCheckingPinUsage;
use App\Models\School;
use App\Models\Student;
use App\Services\ResultTokenVerifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The token gate on the Standard and Exclusive portals.
 *
 * A signed-in parent or student proves they are entitled to an account. It
 * does not follow that the school is ready to hand over a particular result,
 * so a portal result stays shut until the exam token for it is entered. Same
 * token, same binding, same 15 characters as the one a Basic school hands out
 * on a slip of paper - the difference is only where it gets typed.
 *
 * Shared by both portals deliberately. The student portal and the guardian
 * portal are separate controllers on separate guards, and a gate implemented
 * twice is a gate that will eventually be two different gates.
 *
 * What is remembered is the pair (student, examination) - never the token.
 * Once a token has been redeemed there is no reason to keep it, and a session
 * that held one would be a place to steal it from.
 *
 * An unlock OUTLIVES the session. It has to: schools issue one token per child
 * per term and do not reissue them, so a parent asked for the token again in
 * June would be locked out of a card they were shown in February. The durable
 * answer is the redemption record itself, which already proved the token
 * belonged to this school, this child and this examination.
 */
trait UnlocksResultsWithToken
{
    /**
     * Where the opened results are remembered. One key for both portals: a
     * guardian and their child are different sessions on different guards, so
     * there is nothing to collide.
     */
    private const UNLOCKED_SESSION_KEY = 'unlocked_results';

    /**
     * Has the token for this exact result ever been entered successfully?
     *
     * Ever, not "in this session". A token opens a result once and for good:
     * a parent who unlocked last term's card in February must not be asked for
     * that token again in June, and asking would be worse than an
     * inconvenience - schools do not reissue tokens, so a parent who has lost
     * the slip would be locked out of a result they had already been shown.
     *
     * What makes that safe is WHERE the durable answer comes from. A usage row
     * is the receipt of a verification that already proved the token belonged
     * to this school, this child and this examination, so an unlock inherits
     * exactly the binding the token had. It is not a second, looser rule.
     *
     * The session is consulted first only because it saves a query on the
     * request that has just unlocked something.
     */
    protected function resultIsUnlocked(Student $student, Examination $examination): bool
    {
        $inSession = in_array(
            $this->unlockKey($student, $examination),
            session(self::UNLOCKED_SESSION_KEY, []),
            true,
        );

        return $inSession || $this->hasBeenRedeemed($student, $examination);
    }

    /**
     * A successful redemption on record for this pair.
     *
     * Deliberately not scoped to who redeemed it. The rule in the brief is
     * that a token unlocks "that particular result for that child", so a
     * result opened by the pupil is open to the parent linked to them and the
     * other way round - one token per child per term is exactly what the
     * school issued, and making each account redeem it separately would need
     * two.
     */
    private function hasBeenRedeemed(Student $student, Examination $examination): bool
    {
        return ResultCheckingPinUsage::query()
            ->where('student_id', $student->id)
            ->where('examination_id', $examination->id)
            ->exists();
    }

    /**
     * How many of this pupil's results are open, and how many still need a
     * token.
     *
     * For the profile pages, which say "Check Result" without listing
     * anything. Counted over the examinations the pupil actually has marks in,
     * which is the same set the results page lists - a card promising two
     * locked results against a page showing three would be its own small
     * betrayal.
     *
     * @return array{locked: int, unlocked: int}
     */
    protected function resultLockSummary(Student $student): array
    {
        $examinations = Examination::query()
            ->where('school_id', $student->school_id)
            ->whereHas('subjects.scores', fn ($query) => $query->where('student_id', $student->id))
            ->get();

        $unlocked = $examinations
            ->filter(fn (Examination $examination) => $this->resultIsUnlocked($student, $examination))
            ->count();

        return [
            'locked' => $examinations->count() - $unlocked,
            'unlocked' => $unlocked,
        ];
    }

    /**
     * Refuse a result whose token has not been entered in this session.
     *
     * On the server, on every route that produces the document. The modal in
     * front of it is a convenience; this is the rule.
     */
    protected function assertResultIsUnlocked(Student $student, Examination $examination): void
    {
        abort_unless(
            $this->resultIsUnlocked($student, $examination),
            403,
            'Enter the exam token for this result before viewing or downloading it.',
        );
    }

    /**
     * Redeem a token for one result, from inside a portal.
     *
     * The token is checked against the student and the examination being
     * opened, so another child's token - valid, unexpired, unspent - is
     * refused here rather than opening the wrong result. That check happens
     * before the use is counted, so a wrong token costs nothing.
     *
     * @throws ValidationException
     */
    protected function redeemTokenFor(
        Request $request,
        School $school,
        Student $student,
        Examination $examination,
        ?Model $redeemedBy = null,
    ): void {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:8', 'max:64'],
        ], [
            'token.required' => 'Enter the exam token your school gave you.',
        ]);

        $usage = app(ResultTokenVerifier::class)->verify(
            $school,
            $validated['token'],
            $request,
            $student,
            $examination,
            $redeemedBy,
        );

        if ($usage === null) {
            // Which result was being opened, so the page that comes back can
            // reopen that modal with the error inside it. A page with several
            // results on it would otherwise put the message on all of them,
            // or on none.
            session()->flash('token_gate_examination', $examination->id);

            // One message for every kind of failure, as everywhere else a
            // token is checked. Saying which check failed would tell someone
            // holding a token whether it is real.
            throw ValidationException::withMessages([
                'token' => ResultTokenVerifier::GENERIC_FAILURE_MESSAGE,
            ]);
        }

        session()->push(self::UNLOCKED_SESSION_KEY, $this->unlockKey($student, $examination));
    }

    private function unlockKey(Student $student, Examination $examination): string
    {
        return "{$student->id}:{$examination->id}";
    }
}
