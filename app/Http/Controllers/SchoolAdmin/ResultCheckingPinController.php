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
use App\Models\ResultTokenBatch;
use App\Models\School;
use App\Models\Student;
use App\Notifications\ResultTokenIssuedNotification;
use App\Services\ExaminationResolver;
use App\Services\ResultAccessPolicy;
use App\Services\ResultTokenIssuer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        $filters = $this->issuedTokenFilters($request);

        $tokens = $this->issuedTokensQuery($school, $filters)
            ->with(['examination', 'boundStudent'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('school-admin.result-pins.index', [
            'school' => $school,

            // The address parents use. Half of the pair, the token is the
            // other half, so it belongs on the same screen as the tokens.
            'resultLinkUrl' => $school->resultLinkUrl(),
            'tokens' => $tokens,

            // The Issued tokens filters, and a plain description of them for
            // the "delete every matching token" confirmation, so the admin
            // reads back exactly which category is about to go.
            'tokenFilters' => $filters,
            'tokenFilterQuery' => $activeFilters = array_filter($filters, fn ($value) => $value !== null && $value !== ''),
            'hasTokenFilter' => $activeFilters !== [],
            'tokenFilterSummary' => $this->describeFilters($filters, $school),
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
            'students' => $school->students()->where('is_active', true)->alphabetical()->get(),

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

            // Bulk runs, newest first, each one printable again from its page.
            'recentBatches' => ResultTokenBatch::query()
                ->forSchool($school)
                ->with('generatedBy')
                ->latest()
                ->limit(8)
                ->get(),

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

    /**
     * Delete the tokens the School Admin ticked in the Issued tokens list.
     *
     * Tokens arrive by UUID and are looked up WITHIN THIS SCHOOL ONLY, so a
     * UUID from another school is simply not found: it can never be deleted
     * from here, and it does not even reveal that it exists.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'tokens' => ['required', 'array', 'min:1', 'max:500'],
            'tokens.*' => ['required', 'string', 'uuid'],
        ], [
            'tokens.required' => 'Tick at least one token to delete.',
            'tokens.max' => 'You can delete up to 500 ticked tokens at a time. To clear a whole class or result, use "Delete all matching" instead.',
            'tokens.*.uuid' => 'One of the ticked tokens was not recognised. Reload the page and try again.',
        ]);

        $pins = ResultCheckingPin::query()
            ->forSchool($school)
            ->whereIn('uuid', array_unique($validated['tokens']))
            ->with(['boundStudent', 'examination'])
            ->get();

        if ($pins->isEmpty()) {
            return $this->backToTokens($request)
                ->withErrors(['tokens' => 'None of the ticked tokens could be found. They may already have been deleted.']);
        }

        $this->deleteTokens($pins, $school, 'the ticked tokens');

        $count = $pins->count();

        return $this->backToTokens($request)->with('status', sprintf(
            '%s %s deleted. Issue new ones for any student who still needs a token.',
            number_format($count),
            $count === 1 ? 'token was' : 'tokens were',
        ));
    }

    /**
     * Delete EVERY token in a category: a class, a result, a session, a term,
     * a status, a student, or any combination of them, across every page.
     *
     * Two guards, because this is the one action on the screen that can remove
     * hundreds of rows with one click:
     *
     *  - At least one category must be chosen. "Delete all matching" with no
     *    filter would be "delete every token this school has ever issued",
     *    and nobody should be one mis-click away from that.
     *  - The count the admin confirmed must still be the count now. If a
     *    colleague issued tokens into the same class in between, the set has
     *    changed under them, and nothing is deleted until they look again.
     */
    public function destroyMatching(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $filters = $this->issuedTokenFilters($request);

        $request->validate([
            'expected_count' => ['required', 'integer', 'min:1'],
        ]);

        if (! $this->hasAnyFilter($filters)) {
            return $this->backToTokens($request)->withErrors([
                'tokens' => 'Choose a category first (a result, class, session, term, status or student) to delete every matching token. To delete a few, tick them instead.',
            ]);
        }

        $query = $this->issuedTokensQuery($school, $filters);
        $count = (clone $query)->count();

        if ($count === 0) {
            return $this->backToTokens($request)->withErrors(['tokens' => 'No tokens match that category, so nothing was deleted.']);
        }

        if ($count !== $request->integer('expected_count')) {
            return $this->backToTokens($request)->withErrors([
                'tokens' => sprintf(
                    'The number of matching tokens changed from %s to %s since the page was loaded, so nothing was deleted. Check the list and try again.',
                    number_format($request->integer('expected_count')),
                    number_format($count),
                ),
            ]);
        }

        $pins = $query->with(['boundStudent', 'examination'])->get();

        $this->deleteTokens($pins, $school, implode(', ', $this->describeFilters($filters, $school)));

        return redirect()
            ->route('result-pins.index')
            ->with('status', sprintf(
                '%s %s deleted (%s). Issue new ones for any student who still needs a token.',
                number_format($count),
                $count === 1 ? 'token was' : 'tokens were',
                implode(', ', $this->describeFilters($filters, $school)),
            ));
    }

    /**
     * Delete a set of tokens in one transaction and leave one audit entry.
     *
     * The audit entry is written FIRST and names every student and result,
     * exactly as the single delete does, so a bulk delete is not a quieter way
     * to erase that tokens existed. A token's usage rows go with it (cascade);
     * the access log keeps its entries with the token reference cleared, so
     * the history of who tried to open a result survives.
     *
     * @param  Collection<int, ResultCheckingPin>  $pins
     */
    private function deleteTokens(Collection $pins, School $school, string $scope): void
    {
        $lines = $pins->map(function (ResultCheckingPin $pin): string {
            $student = $pin->boundStudent
                ? "{$pin->boundStudent->fullName()} ({$pin->boundStudent->admission_number})"
                : 'an unbound pupil';

            return $pin->examination
                ? "{$student}, {$pin->examination->name} {$pin->examination->term->label()} {$pin->examination->session}"
                : $student;
        });

        // Kept readable in the audit screen: the first hundred are named, the
        // rest are counted.
        $named = $lines->take(100)->implode('; ');
        $more = $lines->count() > 100 ? sprintf('; and %s more', number_format($lines->count() - 100)) : '';

        DB::transaction(function () use ($pins, $school, $scope, $named, $more): void {
            AuditLog::record(
                'result-token.bulk-deleted',
                sprintf('Deleted %s result token(s) [%s]: %s%s.', number_format($pins->count()), $scope, $named, $more),
                $school,
            );

            $pins->pluck('id')->chunk(500)->each(
                fn ($ids) => ResultCheckingPin::query()->forSchool($school)->whereIn('id', $ids->all())->delete()
            );
        });
    }

    /**
     * The Issued tokens filters, cleaned.
     *
     * Read from the query string on the page and from the form body on the
     * "delete all matching" POST, so the list the admin looked at and the set
     * that gets deleted are built from exactly the same values by exactly the
     * same query. Anything unrecognised is dropped rather than trusted.
     *
     * @return array{search: string, examination_id: ?int, class_name: string, session: string, term: string, status: string}
     */
    private function issuedTokenFilters(Request $request): array
    {
        $status = (string) $request->input('status', '');
        $term = (string) $request->input('term', '');
        $examinationId = (int) $request->input('examination_id', 0);

        return [
            'search' => mb_substr(trim((string) $request->input('search', '')), 0, 100),
            'examination_id' => $examinationId > 0 ? $examinationId : null,
            'class_name' => mb_substr(trim((string) $request->input('class_name', '')), 0, 100),
            'session' => mb_substr(trim((string) $request->input('session', '')), 0, 20),
            'term' => ExamTerm::tryFrom($term)?->value ?? '',
            'status' => ResultCheckingPinStatus::tryFrom($status)?->value ?? '',
        ];
    }

    /**
     * @param  array{search: string, examination_id: ?int, class_name: string, session: string, term: string, status: string}  $filters
     */
    private function hasAnyFilter(array $filters): bool
    {
        return collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
    }

    /**
     * Every token of this school matching the filters. Always scoped to the
     * school first, so no filter value can reach another school's tokens.
     *
     * @param  array{search: string, examination_id: ?int, class_name: string, session: string, term: string, status: string}  $filters
     */
    private function issuedTokensQuery(School $school, array $filters): Builder
    {
        return ResultCheckingPin::query()
            ->forSchool($school)
            ->when($filters['examination_id'], fn (Builder $query, int $id) => $query->where('examination_id', $id))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            // Class, session and term belong to the RESULT the token opens, so
            // "JSS 1" means tokens for JSS 1's results, even for a student who
            // has since moved up to JSS 2.
            ->when(
                $filters['class_name'] !== '' || $filters['session'] !== '' || $filters['term'] !== '',
                fn (Builder $query) => $query->whereHas('examination', function (Builder $examination) use ($filters): void {
                    $examination
                        ->when($filters['class_name'], fn (Builder $q, string $class) => $q->where('class_name', $class))
                        ->when($filters['session'], fn (Builder $q, string $session) => $q->where('session', $session))
                        ->when($filters['term'], fn (Builder $q, string $term) => $q->where('term', $term));
                }),
            )
            // Every word must match the first name, last name or admission
            // number, so "John Doe" finds John Doe without a database-specific
            // CONCAT.
            ->when($filters['search'], function (Builder $query, string $search): void {
                foreach (preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                    $like = '%'.addcslashes($word, '%_\\').'%';

                    $query->whereHas('boundStudent', fn (Builder $student) => $student->where(
                        fn (Builder $w) => $w->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('admission_number', 'like', $like)
                    ));
                }
            });
    }

    /**
     * The filters in words, for the confirmation box and the audit log.
     *
     * @param  array{search: string, examination_id: ?int, class_name: string, session: string, term: string, status: string}  $filters
     * @return list<string>
     */
    private function describeFilters(array $filters, School $school): array
    {
        $parts = [];

        if ($filters['examination_id']) {
            $examination = $school->examinations()->find($filters['examination_id']);
            $parts[] = $examination
                ? "result: {$examination->name}, {$examination->class_name} ({$examination->term->label()}, {$examination->session})"
                : 'result: unknown';
        }

        if ($filters['class_name'] !== '') {
            $parts[] = "class: {$filters['class_name']}";
        }

        if ($filters['session'] !== '') {
            $parts[] = "session: {$filters['session']}";
        }

        if ($filters['term'] !== '') {
            $parts[] = 'term: '.ExamTerm::from($filters['term'])->label();
        }

        if ($filters['status'] !== '') {
            $parts[] = 'status: '.ResultCheckingPinStatus::from($filters['status'])->label();
        }

        if ($filters['search'] !== '') {
            $parts[] = "student: \"{$filters['search']}\"";
        }

        return $parts;
    }

    /**
     * Back to the token list with the admin's filters still applied, so after
     * deleting from page 3 of JSS 1 they are not dropped on page 1 of
     * everything.
     */
    private function backToTokens(Request $request): RedirectResponse
    {
        return redirect()->route('result-pins.index', array_filter(
            $request->only('search', 'examination_id', 'class_name', 'session', 'term', 'status', 'page'),
            fn ($value) => $value !== null && $value !== '',
        ));
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
            ->alphabetical()
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
