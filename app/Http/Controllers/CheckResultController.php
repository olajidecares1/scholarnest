<?php

namespace App\Http\Controllers;

use App\Http\Requests\IdentifyStudentRequest;
use App\Http\Requests\VerifyResultPinRequest;
use App\Models\ResultCheckingPinUsage;
use App\Models\School;
use App\Models\Student;
use App\Services\ReportCardData;
use App\Services\ResultAccessPolicy;
use App\Services\ResultCheckIdentity;
use App\Services\ResultTokenVerifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Redeeming a result token.
 *
 * Deliberately independent of the front-facing school website, because it must
 * work identically on all three plans - Basic schools have no website and no
 * portal at all, and a result token is not a plan feature.
 *
 * Nothing on this path takes a student, a result, a term or a session from the
 * request. The only input is the token, and everything shown is read from what
 * that token is bound to. There is no id in a URL for anyone to increment.
 */
class CheckResultController extends Controller
{
    public function __construct(
        private readonly ResultTokenVerifier $verifier,
        private readonly ResultAccessPolicy $access,
        private readonly ResultCheckIdentity $identity,
    ) {}

    public function create(Request $request, School $school): View
    {
        $this->assertResultLinkIsLive($school);

        // Coming back to step one abandons whoever was identified, so a shared
        // computer does not offer the last family's child to the next.
        $this->identity->forget($request, $school);

        return view('check-result.show', ['school' => $school]);
    }

    /**
     * Step one: name the pupil from their School ID / Admission Number.
     *
     * Answering this confirms that a given admission number belongs to a named
     * child - before any token has been shown - so it is rate limited on
     * successes as well as failures. See {@see IdentifyStudentRequest}.
     */
    public function identify(IdentifyStudentRequest $request, School $school): RedirectResponse
    {
        $this->assertResultLinkIsLive($school);

        $request->ensureIsNotRateLimited($school);

        $student = Student::where('school_id', $school->id)
            ->where('is_active', true)
            ->whereRaw('LOWER(admission_number) = ?', [mb_strtolower(trim($request->string('admission_number')->toString()))])
            ->first();

        if ($student === null) {
            $request->recordFailure($school);

            // Deliberately not "no pupil has that admission number". The reply
            // to a wrong number and the reply to a number belonging to a
            // deactivated pupil are the same sentence.
            throw ValidationException::withMessages([
                'admission_number' => 'We could not find that School ID or Admission Number at this school. Check it and try again.',
            ]);
        }

        $request->clearFailures($school);
        $request->recordLookup($school);

        $this->identity->remember($request, $school, $student);

        return redirect()->route($this->routeName('confirm', $school), [
            'school' => $this->schoolRouteValue($school),
        ]);
    }

    /**
     * Step two: confirm who was found, then ask for the token.
     *
     * The pupil comes from the session, never the address, so there is no id
     * here for anyone to change.
     */
    public function confirm(Request $request, School $school): View|RedirectResponse
    {
        $this->assertResultLinkIsLive($school);

        $student = $this->identity->resolve($request, $school);

        if ($student === null) {
            return redirect()->route($this->routeName('show', $school), [
                'school' => $this->schoolRouteValue($school),
            ]);
        }

        return view('check-result.confirm', [
            'school' => $school,
            'student' => $student,
        ]);
    }

    public function verify(VerifyResultPinRequest $request, School $school): RedirectResponse
    {
        $this->assertResultLinkIsLive($school);

        // The token is only ever checked against the pupil already identified.
        // Losing the session means starting again rather than falling back to
        // a token-only check, which would reopen the flow this replaced.
        $student = $this->identity->resolve($request, $school);

        if ($student === null) {
            return redirect()->route($this->routeName('show', $school), [
                'school' => $this->schoolRouteValue($school),
            ])->withErrors(['admission_number' => 'Please enter the School ID or Admission Number again.']);
        }

        try {
            $request->ensureIsNotRateLimited($school);
        } catch (ValidationException $exception) {
            // Logged as well, because a burst of attempts hitting the limiter
            // is precisely the pattern a school would want to see.
            $this->verifier->logRateLimited($school, $request);

            throw $exception;
        }

        // $forStudent is what makes another child's token useless here: the
        // verifier refuses a token bound to anyone but this pupil, and refuses
        // it before counting the use, so a mistyped token costs nothing.
        $usage = $this->verifier->verify(
            $school,
            $request->string('code')->toString(),
            $request,
            forStudent: $student,
        );

        if ($usage === null) {
            $request->hit($school);

            // One message for every kind of failure. Saying which check failed
            // would let someone learn that a token is real but revoked, or that
            // a student exists but has no published result - each of which is a
            // fact worth keeping to ourselves.
            throw ValidationException::withMessages([
                'code' => ResultTokenVerifier::GENERIC_FAILURE_MESSAGE,
            ]);
        }

        $request->clearLimiter($school);

        return redirect()->route($this->routeName('result', $school), [
            'school' => $this->schoolRouteValue($school),
            'usage' => $usage,
        ]);
    }

    /**
     * The result itself.
     *
     * Reached only by redirect after a successful verification. The usage is
     * bound by its uuid, so the address cannot be walked from one result to the
     * next, and it is re-checked against this school on every request rather
     * than trusted because it was valid a moment ago.
     */
    public function result(Request $request, School $school, ResultCheckingPinUsage $usage): View
    {
        $this->assertResultLinkIsLive($school);

        // The identification has done its job. Dropping it here means a shared
        // or public computer does not leave the next visitor one step from
        // this child's result.
        $this->identity->forget($request, $school);

        // The school comes from the address; the token's school comes from the
        // token. They must be the same school, and this is the check that says
        // so - editing the address to another school's link cannot reach this
        // result, because the usage behind it still belongs where it did.
        abort_unless($usage->pin?->school_id === $school->id, 404);

        // The token that authorised this view may have been revoked or
        // suspended since - by the school, or by the Super Admin looking into
        // something. Re-checking here means the result page closes with it,
        // rather than staying open to whoever still holds the link.
        $token = $usage->pin;

        abort_if(
            $token->status->allowsAccess() === false && $token->status->value !== 'exhausted',
            404,
        );

        // The same shared engine every other portal renders from - School
        // Admin, Teacher, Student and Guardian all call ReportCardData::for().
        //
        // This page used to hand-roll its own subject list and average, which
        // meant a token-delivered result was quietly a different document from
        // the one the school saw: no attendance, no position, no remarks, and
        // its own idea of what the average was. A parent comparing the two
        // would have been right to ask which was correct.
        // Fees. A valid token proves the holder is entitled to THIS student's
        // result; it does not decide whether the school is ready to hand it
        // over. Checked here rather than at redemption so that a balance
        // settled after the token was used opens the result on the next visit,
        // and one raised afterwards closes it again.
        //
        // The reason is stated plainly, unlike the token errors, which are
        // deliberately vague. Different audience: a vague token error protects
        // against someone guessing at tokens, whereas whoever reaches this
        // point has already proved their claim to this particular result. The
        // balance is the one thing they can act on.
        if ($lockMessage = $this->access->lockMessage($usage->student, $usage->examination)) {
            return view('check-result.locked', [
                'school' => $school,
                'student' => $usage->student,
                'examination' => $usage->examination,
                'message' => $lockMessage,
            ]);
        }

        return view('check-result.result', [
            ...ReportCardData::for($usage->examination, $usage->student),
            'usage' => $usage,
        ]);
    }

    /**
     * The same report card as a file.
     *
     * Guarded exactly as the result page is, and for the same reason: this is a
     * second address that hands over a child's marks, so it re-runs every check
     * rather than assuming whoever reached it came through the page. The fee
     * hold applies here too - a result that cannot be read cannot be saved
     * either.
     */
    public function download(Request $request, School $school, ResultCheckingPinUsage $usage): Response
    {
        $this->assertResultLinkIsLive($school);
        $this->assertUsageIsStillGood($school, $usage);

        abort_if($this->access->isLocked($usage->student, $usage->examination), 404);

        $pdf = Pdf::loadView(
            'school-admin.results.pdf.report-card',
            ReportCardData::for($usage->examination, $usage->student),
        )->setPaper('a4');

        return $pdf->download("result-{$usage->student->admission_number}.pdf");
    }

    /**
     * The checks that stand between a usage uuid and a child's marks.
     *
     * The school on the address and the school on the token must agree, and the
     * token must still be one that grants access - it may have been revoked or
     * suspended since it was redeemed.
     */
    private function assertUsageIsStillGood(School $school, ResultCheckingPinUsage $usage): void
    {
        abort_unless($usage->pin?->school_id === $school->id, 404);

        $token = $usage->pin;

        abort_if(
            $token->status->allowsAccess() === false && $token->status->value !== 'exhausted',
            404,
        );
    }

    /**
     * Refuse a school's own result address once the school has switched it off.
     *
     * 404 rather than a message, because a revoked link should look like no
     * link. Only reachable through the school-result routes: the older
     * /schools/{slug}/check-result path is not the address a school hands out
     * and is not something it can revoke.
     */
    private function assertResultLinkIsLive(School $school): void
    {
        if (request()->routeIs('school-result.*')) {
            abort_unless($school->resultLinkIsLive(), 404);
        }
    }

    /**
     * The sibling route in whichever group this request arrived through.
     *
     * One controller serves two addresses - a school's own
     * /greenfield-college/result and the older /schools/{slug}/check-result -
     * and a redirect has to stay inside the one the visitor is actually using.
     */
    private function routeName(string $action, School $school): string
    {
        return request()->routeIs('school-result.*')
            ? "school-result.{$action}"
            : "check-result.{$action}";
    }

    /**
     * What to put in the URL for this school: its result-link address on the
     * school-result routes, and the school itself elsewhere.
     */
    private function schoolRouteValue(School $school): string|School
    {
        return request()->routeIs('school-result.*')
            ? $school->result_link_slug
            : $school;
    }
}
