<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Examination;
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
 * What is remembered is the pair (student, examination), in the session. Not
 * the token: once it has been redeemed there is no reason to keep it, and a
 * session that held one would be a place to steal it from. Signing out closes
 * every result that was opened.
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
     * Has this session already redeemed a token for this exact result?
     */
    protected function resultIsUnlocked(Student $student, Examination $examination): bool
    {
        return in_array(
            $this->unlockKey($student, $examination),
            session(self::UNLOCKED_SESSION_KEY, []),
            true,
        );
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
