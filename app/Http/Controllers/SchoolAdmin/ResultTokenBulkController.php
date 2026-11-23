<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ExamTerm;
use App\Enums\ResultCheckingPinStatus;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ResultCheckingPin;
use App\Models\ResultTokenBatch;
use App\Models\School;
use App\Models\Student;
use App\Services\BulkResultTokenGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bulk exam tokens: choose the students, review them, generate, print.
 *
 *   1. CHOOSE. Year and term, then any number of classes. Each class lists
 *      its students with a tick box, a Select All, and a "number of students"
 *      box that stays in step with the ticks. Students who already hold a
 *      valid token for that term are shown but cannot be ticked.
 *   2. REVIEW. Everyone chosen, by class, with the totals, and a way to take
 *      anyone off before anything is created.
 *   3. GENERATE. Tokens for exactly those students, as one batch.
 *   4. PRINT. The batch on A4, all of it, one class, or chosen students, as a
 *      PDF to print or download.
 *
 * Every step re-checks the selection on the server. What the browser sends is
 * a list of student ids and a count per class; whose students they are, which
 * class each is in and which examination their token binds to all come from
 * the database.
 */
class ResultTokenBulkController extends Controller
{
    use AuthorizesSchoolOwnership;

    /** The most students one run will take. Comfortably a whole school. */
    private const MAX_STUDENTS = 3000;

    /**
     * Rows on one A4 page of the printout. The brief asks for at least 15;
     * 25 still leaves every name and token in large, clear type.
     */
    public const ROWS_PER_PAGE = 25;

    public function __construct(private readonly BulkResultTokenGenerator $generator) {}

    // -------------------------------------------------------------------------
    // 1. Choose
    // -------------------------------------------------------------------------

    public function create(Request $request): View
    {
        $school = $request->user()->school;

        $session = $this->sessionFrom($request, $school);
        $term = ExamTerm::tryFrom((string) $request->query('term', old('term', ''))) ?? ExamTerm::First;
        $classNames = $school->configuredClassNames();

        $roster = $this->generator->roster($school, $session, $term, $classNames);

        // Coming back from the review to change something: the same students
        // ticked and the same classes open.
        $selected = collect($request->query('selected', old('student_ids', [])))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        return view('school-admin.result-pins.bulk.create', [
            'school' => $school,
            'session' => $session,
            'term' => $term,
            'sessions' => $this->sessions($school),
            'terms' => ExamTerm::cases(),
            'classes' => $roster->map(fn (Collection $rows, string $className): array => [
                'name' => $className,
                'students' => $rows->map(fn (array $row): array => [
                    'id' => $row['student']->id,
                    'name' => $row['student']->fullName(),
                    'admission_number' => $row['student']->admission_number,
                    'has_token' => $row['has_token'],
                ])->all(),
            ])->values(),
            'selected' => $selected,
        ]);
    }

    // -------------------------------------------------------------------------
    // 2. Review
    // -------------------------------------------------------------------------

    public function review(Request $request): View
    {
        $school = $request->user()->school;
        [$session, $term, $students] = $this->validatedSelection($request, $school);

        $holders = $this->generator->holders($school, $session, $term, $students);
        $order = array_flip($school->configuredClassNames());

        $sortByClass = fn (Collection $list) => $list
            ->groupBy('class_name')
            ->sortBy(fn ($group, $className) => $order[$className] ?? PHP_INT_MAX)
            ->map(fn (Collection $group) => $group->sortBy(fn (Student $s) => [$s->first_name, $s->last_name, $s->id])->values());

        return view('school-admin.result-pins.bulk.review', [
            'school' => $school,
            'session' => $session,
            'term' => $term,
            'groups' => $sortByClass($students->reject(fn (Student $s) => isset($holders[$s->id]))),
            'alreadyHolding' => $sortByClass($students->filter(fn (Student $s) => isset($holders[$s->id])))->flatten(1),
        ]);
    }

    // -------------------------------------------------------------------------
    // 3. Generate
    // -------------------------------------------------------------------------

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        [$session, $term, $students] = $this->validatedSelection($request, $school);

        $result = $this->generator->generate($school, $session, $term, $students, $request->user());

        if ($result['batch'] === null) {
            return redirect()
                ->route('result-pins.bulk.create', ['session' => $session, 'term' => $term->value])
                ->with('status', 'No tokens were generated: every student you selected already holds a valid token for '
                    ."{$term->label()}, {$session}. You can print their tokens from the batch they were issued in.");
        }

        $batch = $result['batch'];
        $classes = $students->pluck('class_name')->unique()->count();

        AuditLog::record(
            'result-token.issued-bulk',
            "Generated {$result['issued']} exam token(s) across {$classes} class(es) for {$term->label()}, {$session}.",
            $batch,
        );

        $status = "{$result['issued']} exam token(s) were generated.";

        if ($result['skipped']->isNotEmpty()) {
            $status .= " {$result['skipped']->count()} selected student(s) already held a valid token and were skipped.";
        }

        return redirect()->route('result-pins.batches.show', $batch)->with('status', $status);
    }

    // -------------------------------------------------------------------------
    // 4. The batch, and printing it
    // -------------------------------------------------------------------------

    public function show(Request $request, ResultTokenBatch $batch): View
    {
        $this->authorizeSchoolOwnership($batch);

        $school = $request->user()->school;
        $tokens = $this->ordered($school, $batch->tokens()->with(['boundStudent', 'examination'])->get());

        // The page shows the plain tokens, so opening it is a reveal, and
        // recorded as one.
        AuditLog::record(
            'result-token.revealed-bulk',
            "Viewed the {$batch->token_count} exam token(s) of a batch for {$batch->term->label()}, {$batch->session}.",
            $batch,
        );

        return view('school-admin.result-pins.batches.show', [
            'school' => $school,
            'batch' => $batch->loadMissing('generatedBy'),
            'groups' => $tokens->filter(fn (ResultCheckingPin $pin) => $this->printable($pin))->groupBy(fn ($pin) => $pin->boundStudent->class_name),
            'withdrawn' => $tokens->reject(fn (ResultCheckingPin $pin) => $this->printable($pin)),
        ]);
    }

    /**
     * The A4 printout, as a PDF to view and print or to download.
     *
     * Either one batch (whole, one class, or chosen students), or every valid
     * token the school holds for a term, optionally for one class. Only
     * tokens that still work are printed: a revoked token on a slip of paper
     * is a parent turned away.
     */
    public function print(Request $request): Response
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'batch' => ['nullable', 'uuid'],
            'session' => ['required_without:batch', 'nullable', 'string', 'max:20'],
            'term' => ['required_without:batch', 'nullable', Rule::enum(ExamTerm::class)],
            'class_name' => ['nullable', 'string', Rule::in($school->configuredClassNames())],
            'tokens' => ['nullable', 'array', 'max:'.self::MAX_STUDENTS],
            'tokens.*' => ['uuid'],
            'download' => ['nullable', 'boolean'],
        ], [
            'class_name.in' => 'Choose a class from your school\'s own class list.',
        ]);

        $query = ResultCheckingPin::query()
            ->forSchool($school)
            ->with(['boundStudent', 'examination']);

        if (filled($validated['batch'] ?? null)) {
            $batch = ResultTokenBatch::query()->forSchool($school)->where('uuid', $validated['batch'])->firstOrFail();
            $query->where('batch_id', $batch->id);
            $session = $batch->session;
            $term = $batch->term;
        } else {
            $batch = null;
            $session = $validated['session'];
            $term = ExamTerm::from($validated['term']);
            $query->whereHas('examination', fn ($exam) => $exam->where('session', $session)->where('term', $term));
        }

        if (filled($validated['tokens'] ?? null)) {
            $query->whereIn('uuid', $validated['tokens']);
        }

        $tokens = $this->ordered($school, $query->get())
            ->filter(fn (ResultCheckingPin $pin) => $this->printable($pin))
            ->when(filled($validated['class_name'] ?? null), fn (Collection $list) => $list
                ->filter(fn (ResultCheckingPin $pin) => $pin->boundStudent->class_name === $validated['class_name']))
            ->values();

        if ($tokens->isEmpty()) {
            return back()->withErrors(['print' => 'There are no valid tokens to print for that choice.']);
        }

        AuditLog::record(
            'result-token.printed',
            "Printed {$tokens->count()} exam token(s) for {$term->label()}, {$session}"
                .(filled($validated['class_name'] ?? null) ? " ({$validated['class_name']})" : '').'.',
            $batch,
        );

        $filename = 'exam-tokens-'.str($session)->replace('/', '-').'-'.$term->value
            .(filled($validated['class_name'] ?? null) ? '-'.str($validated['class_name'])->slug() : '')
            .'.pdf';

        $pdf = Pdf::loadView('school-admin.result-pins.pdf.tokens', [
            'school' => $school,
            'session' => $session,
            'term' => $term,
            'pages' => $tokens->chunk(self::ROWS_PER_PAGE)->values(),
            'total' => $tokens->count(),
            'resultLinkUrl' => $school->resultLinkUrl(),
            'classLabel' => $validated['class_name'] ?? null,
        ])->setPaper('a4', 'portrait');

        $response = $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);

        // Tokens on a PDF are credentials: never keep a copy in a cache.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    // -------------------------------------------------------------------------
    // Shared
    // -------------------------------------------------------------------------

    /**
     * The year, the term and the students, checked.
     *
     * @return array{0: string, 1: ExamTerm, 2: Collection<int, Student>}
     */
    private function validatedSelection(Request $request, School $school): array
    {
        $validated = $request->validate([
            'session' => ['required', 'string', 'max:20'],
            'term' => ['required', Rule::enum(ExamTerm::class)],
            'student_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_STUDENTS],
            'student_ids.*' => ['integer', 'distinct'],
            'quantities' => ['nullable', 'array'],
            'quantities.*.class' => ['required', 'string', 'max:100'],
            'quantities.*.count' => ['required', 'integer', 'min:0', 'max:'.self::MAX_STUDENTS],
        ], [
            'student_ids.required' => 'Select at least one student.',
            'student_ids.min' => 'Select at least one student.',
        ]);

        $ids = collect($validated['student_ids'])->map(fn ($id) => (int) $id)->unique();

        // Scoped to this school and to students still on roll, so an id from
        // another school, or of a pupil who has left, is refused here.
        $students = $school->students()
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->get();

        if ($students->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'student_ids' => 'Some of the selected students are not active students of your school. Please choose again.',
            ]);
        }

        $classNames = $school->configuredClassNames();

        if ($students->contains(fn (Student $s) => ! in_array($s->class_name, $classNames, true))) {
            throw ValidationException::withMessages([
                'student_ids' => 'Every selected student must be in one of your school\'s classes.',
            ]);
        }

        // The number of students asked for, class by class, must be the
        // number actually ticked. They are kept in step in the browser; this
        // is where a mismatch is caught if they are not.
        $selectedPerClass = $students->countBy('class_name');

        foreach ($validated['quantities'] ?? [] as $quantity) {
            $class = $quantity['class'];
            $asked = (int) $quantity['count'];
            $ticked = $selectedPerClass[$class] ?? 0;

            if ($asked !== $ticked) {
                throw ValidationException::withMessages([
                    'quantities' => "You asked for {$asked} token(s) for {$class} but selected {$ticked} student(s). "
                        .'Tick the students or change the number so they match.',
                ]);
            }
        }

        return [$validated['session'], ExamTerm::from($validated['term']), $students];
    }

    /**
     * In the school's class order, then alphabetically, the order the lists
     * on screen use, so the printout and the screen agree row for row.
     *
     * @param  Collection<int, ResultCheckingPin>  $tokens
     * @return Collection<int, ResultCheckingPin>
     */
    private function ordered(School $school, Collection $tokens): Collection
    {
        $order = array_flip($school->configuredClassNames());

        return $tokens
            ->filter(fn (ResultCheckingPin $pin) => $pin->boundStudent !== null)
            ->sortBy(fn (ResultCheckingPin $pin) => [
                $order[$pin->boundStudent->class_name] ?? PHP_INT_MAX,
                mb_strtolower((string) $pin->boundStudent->first_name),
                mb_strtolower((string) $pin->boundStudent->last_name),
                $pin->boundStudent->id,
            ])
            ->values();
    }

    private function printable(ResultCheckingPin $pin): bool
    {
        return in_array($pin->status, [ResultCheckingPinStatus::Active, ResultCheckingPinStatus::Suspended], true)
            && ! $pin->hasExpired()
            && $pin->boundStudent !== null
            && filled($pin->plainToken());
    }

    private function sessionFrom(Request $request, School $school): string
    {
        $session = (string) $request->query('session', old('session', $school->currentSession()));

        return $this->sessions($school)->contains($session) ? $session : $school->currentSession();
    }

    /**
     * @return Collection<int, string>
     */
    private function sessions(School $school): Collection
    {
        return $school->examinations()->pluck('session')
            ->push($school->currentSession())
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();
    }
}
