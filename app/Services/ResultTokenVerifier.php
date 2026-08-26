<?php

namespace App\Services;

use App\Enums\ResultCheckingPinStatus;
use App\Enums\ResultTokenAccessOutcome;
use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\ResultCheckingPinUsage;
use App\Models\ResultTokenAccessLog;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Verifies a result token and, if it holds up, records the view.
 *
 * Two principles run through this class.
 *
 * **The server decides.** Nothing about which result gets shown comes from the
 * request except the token itself. The student, the examination, the term and
 * the session are all read off the token's own bindings, so there is no
 * student id, result id or term in the URL for anyone to edit.
 *
 * **The reason is never revealed.** A token that does not exist, one that was
 * revoked, one that belongs to another term and one whose result is not
 * published all produce the same refusal. Distinguishing them would let
 * somebody map which tokens are real, which students exist, and which results
 * are out - so the reason goes to the log and never to the screen.
 */
class ResultTokenVerifier
{
    /**
     * What every failure says, whatever actually went wrong.
     */
    public const GENERIC_FAILURE_MESSAGE = 'Invalid Result Token. The token you entered is invalid or cannot be used to access this result. Please check the token provided by your school and try again.';

    /**
     * Attempt to redeem a token.
     *
     * Returns the recorded usage on success, or null on any failure. Either
     * way an entry is written to the access log.
     *
     * $forStudent and $forExamination are for the portals, where the reader is
     * already signed in and the result they are opening is already known. On
     * the public check-result page there is nothing to compare against - the
     * token names its own student, which is the whole point - but in a portal
     * a token for another child must be refused even though it is perfectly
     * valid, and refused BEFORE it is counted as used, so that trying somebody
     * else's token cannot spend it.
     */
    public function verify(
        School $school,
        string $plainToken,
        Request $request,
        ?Student $forStudent = null,
        ?Examination $forExamination = null,
        ?Model $redeemedBy = null,
    ): ?ResultCheckingPinUsage {
        $hash = ResultCheckingPin::hashToken($plainToken);

        return DB::transaction(function () use ($school, $hash, $request, $forStudent, $forExamination, $redeemedBy): ?ResultCheckingPinUsage {
            // Scoped to this school as well as the hash. Without the school
            // clause a token would work from any school's check-result page,
            // which leaks nothing by itself but blurs a boundary worth keeping
            // sharp.
            $token = ResultCheckingPin::query()
                ->forSchool($school)
                ->where('token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                $this->log($school, null, ResultTokenAccessOutcome::NotFound, $request, $hash);

                return null;
            }

            if ($refusal = $token->accessRefusalReason()) {
                $this->log($school, $token, $refusal, $request, $hash);

                return null;
            }

            // Before the use is counted, so a parent who mistypes their own
            // token - or tries one belonging to another child - has not spent
            // anything.
            $boundElsewhere = ($forStudent !== null && $token->bound_student_id !== $forStudent->id)
                || ($forExamination !== null && $token->examination_id !== $forExamination->id);

            if ($boundElsewhere) {
                $this->log($school, $token, ResultTokenAccessOutcome::BoundElsewhere, $request, $hash);

                return null;
            }

            if (! $this->resultIsAvailable($token)) {
                $this->log($school, $token, ResultTokenAccessOutcome::ResultUnavailable, $request, $hash);

                return null;
            }

            $token->uses_count++;

            $token->update([
                'uses_count' => $token->uses_count,
                'last_accessed_at' => now(),
                'status' => $token->uses_count >= $token->max_uses
                    ? ResultCheckingPinStatus::Exhausted
                    : ResultCheckingPinStatus::Active,
            ]);

            $usage = ResultCheckingPinUsage::create([
                'result_checking_pin_id' => $token->id,

                // Taken from the token's own bindings, never from the request.
                'student_id' => $token->bound_student_id,
                'examination_id' => $token->examination_id,

                'ip_address' => $request->ip(),
                'used_at' => now(),

                // Only where somebody was signed in. On the public result page
                // nobody is, and that is the design rather than a gap.
                'redeemed_by_type' => $redeemedBy?->getMorphClass(),
                'redeemed_by_id' => $redeemedBy?->getKey(),
            ]);

            $this->log($school, $token, ResultTokenAccessOutcome::Succeeded, $request, $hash);

            return $usage;
        });
    }

    /**
     * Record an attempt that never got as far as being checked, because the
     * rate limiter turned it away.
     */
    public function logRateLimited(School $school, Request $request): void
    {
        $this->log($school, null, ResultTokenAccessOutcome::RateLimited, $request, null);
    }

    /**
     * Is there actually a result behind this token yet?
     *
     * A token can legitimately be issued before scores are entered - a school
     * may print them with the report cards - so this is checked at redemption
     * rather than assumed at issue. With no scores there is nothing to show,
     * and showing an empty result would look like a mistake by the school.
     */
    private function resultIsAvailable(ResultCheckingPin $token): bool
    {
        $examination = $token->examination;

        if ($examination === null) {
            return false;
        }

        // Scores hang off the examination's subjects rather than the
        // examination itself, so this asks whether any subject in it carries a
        // score for this particular student.
        return $examination->subjects()
            ->whereHas('scores', fn ($query) => $query->where('student_id', $token->bound_student_id))
            ->exists();
    }

    private function log(
        School $school,
        ?ResultCheckingPin $token,
        ResultTokenAccessOutcome $outcome,
        Request $request,
        ?string $attemptedHash,
    ): void {
        $userAgent = $request->userAgent();

        ResultTokenAccessLog::create([
            'school_id' => $school->id,
            'result_checking_pin_id' => $token?->id,
            'student_id' => $token?->bound_student_id,
            'examination_id' => $token?->examination_id,
            'outcome' => $outcome,

            // The hash of what was typed, never the token. Enough to correlate
            // repeated attempts, useless to anyone reading the log.
            'token_hash_attempted' => $attemptedHash,

            'ip_address' => $request->ip(),
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 512),
            'occurred_at' => now(),
        ]);
    }
}
