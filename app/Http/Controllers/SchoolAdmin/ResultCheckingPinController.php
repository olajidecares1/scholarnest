<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ExamTerm;
use App\Enums\ResultCheckingPinStatus;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\ResultTokenAccessLog;
use App\Models\School;
use App\Models\Student;
use App\Notifications\ResultTokenIssuedNotification;
use App\Services\ExaminationResolver;
use App\Services\ResultAccessPolicy;
use App\Services\ResultTokenIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

/**
 * The school's result-token management screen.
 *
 * Schools issue their own tokens here, one per student per examination. The
 * Super Admin no longer has to mint them: it keeps oversight and the power to
 * revoke, which is a different thing from standing in the way of every school's
 * end of term.
 */
class ResultCheckingPinController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function __construct(
        private readonly ResultTokenIssuer $issuer,
        private readonly ResultAccessPolicy $access,
        private readonly ExaminationResolver $examinations,
    ) {}

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $tokens = $school->resultCheckingPins()
            ->with(['examination', 'boundStudent'])
            ->when($request->filled('examination_id'), fn ($query) => $query->where('examination_id', $request->integer('examination_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('school-admin.result-pins.index', [
            'school' => $school,

            // The address parents use. Half of the pair, the token is the
            // other half, so it belongs on the same screen as the tokens.
            'resultLinkUrl' => $school->resultLinkUrl(),
            'tokens' => $tokens,
            'examinations' => $examinations = $school->examinations()->orderByDesc('exam_date')->get(),
            'statuses' => ResultCheckingPinStatus::cases(),

            // The picker is session, then term, then student, the order a
            // School Admin thinks in at the end of a term. The examinations
            // are filtered down from these in the browser, but every choice is
            // re-checked on the server when the form is submitted.
            //
            // THE SCHOOL'S OWN ACADEMIC YEAR IS ALWAYS OFFERED, whether or not
            // an examination has been recorded in it yet. The list used to be
            // the sessions found on examinations alone, so a school that had
            // just moved to 2026/2027 in Settings could not select it here
            // until it had already created an examination, and the form
            // defaulted to whatever year its newest examination happened to
            // be in, which is not the year the school is in.
            //
            // Historical sessions stay in the list, because generating tokens
            // for a past term is a thing schools legitimately need to do.
            'sessions' => $examinations->pluck('session')
                ->push($school->currentSession())
                ->filter()
                ->unique()
                ->sortDesc()
                ->values(),

            // Read from Settings on every request, so changing the academic
            // year there changes what this form defaults to immediately,
            // never hard-coded, and never inferred from the data.
            'currentSession' => $school->currentSession(),
            'terms' => ExamTerm::cases(),
            'students' => $school->students()->where('is_active', true)->orderBy('last_name')->get(),

            // The school's own classes, so the admin picks one rather than
            // typing it.
            'classNames' => $school->configuredClassNames(),

            // How many active students sit in each class, so the form can say
            // "30 students, one token each" before anything is generated.
            'classCounts' => $school->students()
                ->where('is_active', true)
                ->selectRaw('class_name, COUNT(*) as total')
                ->groupBy('class_name')
                ->pluck('total', 'class_name'),

            // Everything the brief asks a School Admin to be able to see about
            // one class's batch: how many were issued, how many are used, who
            // has used theirs, who has not, and when.
            'classTracking' => $this->classTracking($school, $request),

            // Whether each token's result is currently being withheld, and why.
            // Shown against the token because "the parent says the token does
            // not work" is nearly always this.
            'lockedTokens' => $this->lockedTokens($school, $tokens->getCollection()),

            // Shown once, immediately after generation. This is the only moment
            // the plain tokens appear on screen in bulk.
            'issuedTokens' => session('issued_tokens'),

            // The school's own view of who has been trying to read results.
            'recentAccess' => ResultTokenAccessLog::query()
                ->forSchool($school)
                ->with(['student', 'examination'])
                ->latest('occurred_at')
                ->limit(15)
                ->get(),
        ]);
    }

    /**
     * Issue a token for one student and one examination.
     */
    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            // Scoped to this school in the rule itself, so a swapped
            // identifier fails validation rather than reaching the issuer.
            'student_id' => ['required', Rule::exists('students', 'id')->where('school_id', $school->id)],

            // The examination is no longer asked for: the year, the term and
            // the student's own class name it completely. See
            // App\Services\ExaminationResolver.
            'session' => ['required', 'string', 'max:20'],
            'term' => ['required', Rule::enum(ExamTerm::class)],
        ]);

        $student = Student::findOrFail($validated['student_id']);

        // The CLASS COMES FROM THE STUDENT RECORD, never from the request. A
        // class submitted alongside the student could disagree with the one
        // they are actually in, and the token would bind to an examination
        // that is not theirs.
        $examination = $this->examinations->forTokens(
            $school,
            $validated['session'],
            ExamTerm::from($validated['term']),
            (string) $student->class_name,
        );

        try {
            $issued = $this->issuer->issue($school, $student, $examination, $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['student_id' => $exception->getMessage()])->withInput();
        }

        AuditLog::record(
            'result-token.issued',
            "Issued a result token to {$student->fullName()} for {$examination->name} ({$examination->term->label()}, {$examination->session}).",
            $issued['token'],
        );

        $status = "A result token was issued for {$student->fullName()}.";

        if ($this->resultsArePending($examination)) {
            $status .= " No marks have been entered for {$examination->name} yet, so this token will not open anything until you publish the result.";
        }

        return back()
            ->with('status', $status)
            ->with('issued_tokens', [[
                'student' => $student->fullName(),
                'admission_number' => $student->admission_number,
                'examination' => $examination->name,
                'term' => $examination->term->label(),
                'session' => $examination->session,
                'token' => $issued['plain'],
            ]]);
    }

    /**
     * Issue tokens for every student in an examination's class who does not
     * already hold one.
     */
    public function storeBulk(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        // The class is validated against the school's own configured classes,
        // not accepted as text. Typing it would let a token batch be issued
        // against "SSS1 Science" while every student is filed under "SSS 1
        // Science", producing a batch of nothing and no obvious reason why.
        $validated = $request->validate([
            'class_name' => ['required', 'string', Rule::in($school->configuredClassNames())],

            // The examination is no longer asked for, the three fields below
            // name it. See App\Services\ExaminationResolver.
            'session' => ['required', 'string', 'max:20'],
            'term' => ['required', Rule::enum(ExamTerm::class)],
        ], [
            'class_name.in' => 'Choose a class from your school\'s own class list.',
        ]);

        // Resolved from the year, term and class the admin chose, so the
        // examination can no longer disagree with the class, it is built from
        // it. The check that used to be needed here is gone with the mismatch
        // it guarded against.
        $examination = $this->examinations->forTokens(
            $school,
            $validated['session'],
            ExamTerm::from($validated['term']),
            $validated['class_name'],
        );

        $issued = $this->issuer->issueForExamination($school, $examination, $request->user());

        // NOBODY TO ISSUE TO is a different thing from everybody already
        // holding one, and they were reported as one. A school with an empty
        // class was told "every student already has a token" while holding
        // none at all, which is untrue, and it sent them looking for tokens
        // that were never generated.
        $roll = $school->students()
            ->where('is_active', true)
            ->where('class_name', $examination->class_name)
            ->count();

        if ($roll === 0) {
            return back()->with('status', "No active students in {$examination->class_name} yet, so there was nobody to issue a token to. Add students to the class first.");
        }

        // The tokens this class already holds, which is what made "every
        // student already has a token" a dead end: true, and useless. The
        // plain values are shown once at issue, so an admin who missed that
        // moment, or who is issuing for a class a colleague did earlier, was
        // told the work was done and given no way to reach it. Their only
        // route was to reveal each student's token one at a time, which
        // nobody does for thirty students.
        //
        // They are shown here instead. It grants no new authority: the same
        // admin can already reveal any one of these on this page, and doing
        // it in a batch is that same action repeated. It is audited as a
        // reveal, separately from the issue, so the record still says which
        // tokens were created and which were merely read.
        $existing = $this->existingTokensFor($school, $examination, $issued->pluck('token.id')->all());

        if ($issued->isNotEmpty()) {
            AuditLog::record(
                'result-token.issued-bulk',
                "Issued {$issued->count()} result tokens for {$examination->name} ({$examination->term->label()}, {$examination->session}).",
                $examination,
            );
        }

        if ($existing->isNotEmpty()) {
            AuditLog::record(
                'result-token.revealed-bulk',
                "Displayed {$existing->count()} existing result token(s) for {$examination->name} ({$examination->term->label()}, {$examination->session}).",
                $examination,
            );
        }

        $row = fn (Student $student, ?string $plain): array => [
            'student' => $student->fullName(),
            'admission_number' => $student->admission_number,
            'examination' => $examination->name,
            'term' => $examination->term->label(),
            'session' => $examination->session,
            'token' => $plain,
        ];

        $tokens = $issued
            ->map(fn (array $issuedRow): array => $row($issuedRow['student'], $issuedRow['plain']))
            ->concat($existing->map(fn (ResultCheckingPin $pin): array => $row($pin->boundStudent, $pin->plainToken())))
            ->sortBy('student')
            ->values()
            ->all();

        $status = $this->bulkStatus($examination->class_name, $issued->count(), $existing->count());

        if ($this->resultsArePending($examination)) {
            $status .= " No marks have been entered for {$examination->name} yet, so these tokens will not open anything until you publish the results.";
        }

        return back()
            ->with('status', $status)
            ->with('issued_tokens', $tokens);
    }

    /**
     * Have any marks been entered for this examination at all?
     *
     * THE ANSWER A SCHOOL NEEDS BEFORE IT HANDS TOKENS OUT. A token is bound
     * to an examination, and the verifier refuses it while that examination
     * carries no score for the pupil, so tokens issued before the marks are
     * entered are tokens that do not work yet. The school sees a live token
     * and the parent is turned away, and neither can explain the other.
     *
     * Worse where the examination was created for them: issuing for a term a
     * school has not recorded yet creates the examination, which is empty by
     * definition, so the first batch a new school issues is exactly the batch
     * that cannot open anything.
     *
     * Saying so at the moment of issue costs one query and removes the whole
     * confusion.
     */
    private function resultsArePending(Examination $examination): bool
    {
        return ! $examination->subjects()->whereHas('scores')->exists();
    }

    /**
     * What the school is told after a batch, which depends on what happened.
     *
     * Three outcomes and three sentences. A single "N tokens were issued" hid
     * the case that matters most: a class where some students were skipped,
     * where the count on screen did not match the class the admin had just
     * chosen and nothing said why.
     */
    private function bulkStatus(string $className, int $issued, int $existing): string
    {
        if ($issued === 0) {
            return "Every student in {$className} already had a token for this result. Their tokens are shown below.";
        }

        if ($existing === 0) {
            return "{$issued} result token(s) were issued for {$className}.";
        }

        return "{$issued} result token(s) were issued for {$className}. The other {$existing} student(s) already had one, shown below with them.";
    }

    /**
     * The usable tokens this examination's class already holds.
     *
     * Active and suspended only, matching what the issuer treats as "already
     * holding one", so the list shown back is exactly the set that was
     * skipped. A revoked or expired token is not reissued by showing it, and
     * would only mislead.
     *
     * @param  array<int, int>  $exclude  Tokens created a moment ago, already in hand.
     * @return Collection<int, ResultCheckingPin>
     */
    private function existingTokensFor(School $school, Examination $examination, array $exclude): Collection
    {
        $studentIds = $school->students()
            ->where('is_active', true)
            ->where('class_name', $examination->class_name)
            ->pluck('id');

        return ResultCheckingPin::query()
            ->forSchool($school)
            ->where('examination_id', $examination->id)
            ->whereIn('bound_student_id', $studentIds)
            ->whereIn('status', [ResultCheckingPinStatus::Active, ResultCheckingPinStatus::Suspended])
            ->whereNotIn('id', $exclude)
            ->with('boundStudent')
            ->get()
            ->filter(fn (ResultCheckingPin $pin): bool => $pin->boundStudent !== null)
            ->values();
    }

    /**
     * Replace a lost token with a fresh one for the same student and result.
     */
    public function reissue(Request $request, ResultCheckingPin $pin): RedirectResponse
    {
        $this->authorizeSchoolOwnership($pin);

        try {
            $issued = $this->issuer->reissue($pin, $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['token' => $exception->getMessage()]);
        }

        AuditLog::record(
            'result-token.reissued',
            "Reissued the result token for {$pin->boundStudent?->fullName()}. The previous token was revoked.",
            $issued['token'],
        );

        return back()
            ->with('status', 'A replacement token was issued and the old one revoked.')
            ->with('issued_tokens', [[
                'student' => $pin->boundStudent?->fullName(),
                'admission_number' => $pin->boundStudent?->admission_number,
                'examination' => $pin->examination?->name,
                'term' => $pin->examination?->term?->label(),
                'session' => $pin->examination?->session,
                'token' => $issued['plain'],
            ]]);
    }

    /**
     * Show a token again, for a school that has to hand it over a second time.
     *
     * Deliberately audited: reading a token back out is exactly the action
     * worth being able to review afterwards.
     */
    public function reveal(Request $request, ResultCheckingPin $pin): RedirectResponse
    {
        $this->authorizeSchoolOwnership($pin);

        AuditLog::record(
            'result-token.revealed',
            "Displayed the result token for {$pin->boundStudent?->fullName()}.",
            $pin,
        );

        return back()->with('issued_tokens', [[
            'student' => $pin->boundStudent?->fullName(),
            'admission_number' => $pin->boundStudent?->admission_number,
            'examination' => $pin->examination?->name,
            'term' => $pin->examination?->term?->label(),
            'session' => $pin->examination?->session,
            'token' => $pin->plainToken(),
        ]]);
    }

    /**
     * Send a token to the pupil and to every guardian linked to them.
     *
     * The school no longer has to hand a slip to each family. One action puts
     * the token in the pupil's portal and in each linked guardian's, at the
     * same moment, and emails all of them to say it is waiting there.
     *
     * WHO GETS IT IS THE LINK, not a list typed here: the guardians are the
     * ones attached to this pupil, so a parent with two children at the school
     * receives each child's token separately and nobody receives a token for a
     * child who is not theirs.
     *
     * The token is delivered to the portal and not into the email itself. See
     * App\Notifications\ResultTokenIssuedNotification for why.
     */
    public function send(Request $request, ResultCheckingPin $pin): RedirectResponse
    {
        $this->authorizeSchoolOwnership($pin);

        $student = $pin->boundStudent;
        $examination = $pin->examination;
        $plain = $pin->plainToken();

        // Nothing to send, and each of these is a different mistake, so none
        // of them is reported as "sent".
        if (! $student || ! $examination || ! $plain) {
            return back()->withErrors(['send' => 'That token is not bound to a pupil and a result yet, so there is nothing to send.']);
        }

        if ($pin->accessRefusalReason() !== null) {
            return back()->withErrors(['send' => 'That token cannot be used at the moment, so sending it would only mislead. Reissue it first.']);
        }

        $notification = new ResultTokenIssuedNotification($examination, $student, $plain);

        $guardians = $student->guardians()->get();

        $student->notify($notification);
        $guardians->each(fn ($guardian) => $guardian->notify(new ResultTokenIssuedNotification($examination, $student, $plain)));

        AuditLog::record(
            'result-token.sent',
            "Sent {$student->fullName()}'s result token to the pupil and {$guardians->count()} linked guardian(s).",
            $pin,
        );

        // The guardian count is in the message on purpose. Zero is the case a
        // school needs to see: it means nobody is linked to that pupil yet,
        // and silence would look like success.
        return back()->with('status', $guardians->isEmpty()
            ? "Sent to {$student->fullName()}'s portal. No guardian is linked to this pupil yet, so nobody else received it."
            : "Sent to {$student->fullName()}'s portal and {$guardians->count()} linked guardian(s).");
    }

    /**
     * Delete a token outright.
     *
     * DIFFERENT FROM REVOKING, and both are wanted. Revoking keeps the row:
     * the record that a token was issued, to whom, and when it stopped working
     * survives, which is what an audit of "who could see this result" needs.
     * Deleting removes it, for the token issued by mistake, against the wrong
     * pupil or the wrong term, which should not sit in a school's list for
     * ever explaining itself.
     *
     * The audit entry is written BEFORE the row goes, and names what it was,
     * so deleting a token is not a way to erase that it existed.
     */
    public function destroy(Request $request, ResultCheckingPin $pin): RedirectResponse
    {
        $this->authorizeSchoolOwnership($pin);

        $student = $pin->boundStudent?->fullName() ?? 'an unbound pupil';
        $examination = $pin->examination;

        AuditLog::record(
            'result-token.deleted',
            $examination
                ? "Deleted the result token for {$student} ({$examination->name}, {$examination->term->label()}, {$examination->session})."
                : 'Deleted an unissued result token.',
            $pin,
        );

        $pin->delete();

        return back()->with('status', "That token was deleted. Issue a new one if {$student} still needs it.");
    }

    public function revoke(Request $request, ResultCheckingPin $pin): RedirectResponse
    {
        $this->authorizeSchoolOwnership($pin);

        $pin->update(['status' => ResultCheckingPinStatus::Revoked]);

        AuditLog::record('result-token.revoked', "Revoked the result token for {$pin->boundStudent?->fullName()}.", $pin);

        return back()->with('status', 'That token was revoked and can no longer be used.');
    }

    /**
     * Withdraw a token temporarily, and put it back.
     *
     * Distinct from revoking, which is permanent: a school that merely wants to
     * pause access while it checks something would otherwise have to destroy a
     * token a parent legitimately holds and reissue it.
     */
    public function toggleSuspension(Request $request, ResultCheckingPin $pin): RedirectResponse
    {
        $this->authorizeSchoolOwnership($pin);

        if ($pin->status === ResultCheckingPinStatus::Suspended) {
            $pin->update(['status' => ResultCheckingPinStatus::Active]);

            AuditLog::record('result-token.unsuspended', "Restored the result token for {$pin->boundStudent?->fullName()}.", $pin);

            return back()->with('status', 'That token is active again.');
        }

        abort_unless($pin->status === ResultCheckingPinStatus::Active, 422, 'Only an active token can be suspended.');

        $pin->update(['status' => ResultCheckingPinStatus::Suspended]);

        AuditLog::record('result-token.suspended', "Suspended the result token for {$pin->boundStudent?->fullName()}.", $pin);

        return back()->with('status', 'That token was suspended. You can restore it at any time.');
    }

    /**
     * Issue the school a fresh result-checking address.
     *
     * For a link that has spread further than the school meant it to. The old
     * address is retired permanently rather than released, so it can never be
     * handed to another school, a parent still holding it gets nothing, not
     * somebody else's token prompt.
     *
     * Every token the school has already issued keeps working: tokens are tied
     * to the school, not to the address, so only the URL changes.
     */
    public function regenerateLink(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $previous = $school->result_link_slug;

        $school->regenerateResultLink();

        AuditLog::record(
            'result-link.regenerated',
            "Replaced the result-checking link /{$previous}/result with /{$school->result_link_slug}/result.",
            $school,
        );

        return back()->with('status', 'A new result-checking link was generated. The old link no longer works, so please share the new one.');
    }

    /**
     * Switch the school's result-checking address off, and back on.
     *
     * Distinct from regenerating: the address stays reserved to this school and
     * keeps working the moment it is switched back on, which is what a school
     * wants between terms rather than a URL its parents have to relearn.
     */
    public function toggleLink(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $school->update(['result_link_enabled' => ! $school->resultLinkIsLive()]);

        AuditLog::record(
            $school->resultLinkIsLive() ? 'result-link.enabled' : 'result-link.disabled',
            ($school->resultLinkIsLive() ? 'Enabled' : 'Disabled')." the result-checking link /{$school->result_link_slug}/result.",
            $school,
        );

        return back()->with('status', $school->resultLinkIsLive()
            ? 'The result-checking link is live again.'
            : 'The result-checking link was switched off. Parents opening it will see nothing until you turn it back on.');
    }

    /**
     * Release, or re-withhold, one student's results for one term.
     *
     * The school's own decision, and recorded as one: who released it, when,
     * and why. This is the "appropriate process" a withheld result waits for,
     * a bursary being processed, a payment plan, a balance the office knows is
     * wrong, and it must not be a switch nobody can account for afterwards.
     *
     * Scoped to a term rather than an examination because that is the unit a
     * bursar clears: a student cleared for Second Term is cleared for every
     * examination in it.
     */
    public function toggleFeeRelease(Request $request, Student $student): RedirectResponse
    {
        $school = $request->user()->school;

        abort_unless($student->school_id === $school->id, 404);

        $validated = $request->validate([
            'session' => ['required', 'string', 'max:20'],
            'term' => ['required', Rule::enum(ExamTerm::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $term = ExamTerm::from($validated['term']);
        $released = $this->access->isReleased($student, $validated['session'], $term);

        if ($released) {
            $this->access->withdrawRelease($student, $validated['session'], $term);

            AuditLog::record(
                'result-token.fee-release.withdrawn',
                "Withheld {$student->fullName()}'s results for {$term->label()}, {$validated['session']} pending fees.",
                $student,
            );

            return back()->with('status', "{$student->fullName()}'s results for {$term->label()} are withheld again.");
        }

        $this->access->release($student, $validated['session'], $term, $request->user(), $validated['reason'] ?? null);

        AuditLog::record(
            'result-token.fee-release.granted',
            "Released {$student->fullName()}'s results for {$term->label()}, {$validated['session']} despite an outstanding balance.",
            $student,
        );

        return back()->with('status', "{$student->fullName()}'s results for {$term->label()} have been released.");
    }

    /**
     * One class's token batch, counted and named.
     *
     * Built from the students rather than from the tokens, so a student who
     * has no token yet appears as a gap rather than as an absence. "28 of 30
     * issued" is the fact a School Admin is looking for at the end of term,
     * and it cannot be seen in a list that only contains tokens.
     *
     * @return array{
     *     examination: Examination,
     *     rows: Collection<int, array<string, mixed>>,
     *     totals: array{students: int, issued: int, used: int, unused: int, missing: int},
     * }|null
     */
    private function classTracking(School $school, Request $request): ?array
    {
        if (! $request->filled('track_examination_id')) {
            return null;
        }

        $examination = $school->examinations()
            ->whereKey($request->integer('track_examination_id'))
            ->first();

        if ($examination === null) {
            return null;
        }

        $tokens = $school->resultCheckingPins()
            ->where('examination_id', $examination->id)
            ->with(['boundStudent', 'usages' => fn ($query) => $query->with('redeemedBy')->latest('used_at')])
            ->get()
            ->keyBy('bound_student_id');

        $rows = $school->students()
            ->where('is_active', true)
            ->where('class_name', $examination->class_name)
            ->orderBy('last_name')
            ->get()
            ->map(function (Student $student) use ($tokens) {
                $token = $tokens->get($student->id);
                $lastUse = $token?->usages->first();

                return [
                    'student' => $student,
                    'token' => $token,
                    'used' => $token !== null && $token->uses_count > 0,
                    'used_at' => $lastUse?->used_at,
                    'used_by' => $lastUse?->redeemedByLabel(),
                    'uses_count' => $token?->uses_count ?? 0,
                ];
            });

        return [
            'examination' => $examination,
            'rows' => $rows,
            'totals' => [
                'students' => $rows->count(),
                'issued' => $rows->filter(fn (array $row) => $row['token'] !== null)->count(),
                'used' => $rows->filter(fn (array $row) => $row['used'])->count(),
                'unused' => $rows->filter(fn (array $row) => $row['token'] !== null && ! $row['used'])->count(),

                // Students in the class with no token at all, the ones a
                // "generate for this class" run would pick up next.
                'missing' => $rows->filter(fn (array $row) => $row['token'] === null)->count(),
            ],
        ];
    }

    /**
     * Which tokens on this page point at a withheld result.
     *
     * Asked of the policy in bulk, two queries for the page, rather than
     * once per token, which is how a screen listing a class ends up running
     * sixty queries it does not need.
     *
     * @param  Collection<int, ResultCheckingPin>  $tokens
     * @return Collection<int, bool>
     */
    private function lockedTokens(School $school, Collection $tokens): Collection
    {
        $usable = $tokens->filter(fn (ResultCheckingPin $token) => $token->boundStudent && $token->examination);

        return $usable
            ->groupBy(fn (ResultCheckingPin $token) => $token->examination_id)
            ->flatMap(function ($group) use ($school) {
                $examination = $group->first()->examination;
                $locked = $this->access->lockedFor(
                    $group->pluck('bound_student_id')->all(),
                    $examination,
                    $school->id,
                );

                return $group->mapWithKeys(fn (ResultCheckingPin $token) => [
                    $token->id => $locked[$token->bound_student_id] ?? false,
                ]);
            });
    }
}
