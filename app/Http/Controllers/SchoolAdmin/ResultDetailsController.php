<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ExamTerm;
use App\Enums\ResultDeliveryChannel;
use App\Enums\ResultDeliveryStatus;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ResultCheckingPin;
use App\Models\ResultDetailDelivery;
use App\Models\ResultTokenBatch;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ExaminationResultCalculator;
use App\Services\ResultDetails\ResultDetailsSender;
use App\Services\ResultRepository;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Support\WhatsAppNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Send students' result details (result link, token and admission number) to
 * their parents/guardians by email, WhatsApp or both.
 *
 * The students can be chosen four ways, and every one ends in the same send:
 *   - tokens[]                    ticked tokens (Issued Tokens, a batch page)
 *   - batch [+ class_name]        a whole bulk-token batch, or one class of it
 *   - session + term [+ class]    every valid token for a term, or a class's
 *   - examination + students[]    students on the Results page, or all=1 for
 *                                 the whole class; a student with no token
 *                                 for that result is issued one at sending
 *
 * One student is sent straight away. More than one goes through a
 * confirmation page first, which says how many students, on which channels,
 * how many messages, and who has no contact details.
 *
 * School Admins only: these routes live in the School Admin route group.
 * Every token and student is looked up within the admin's own school.
 */
class ResultDetailsController extends Controller
{
    use AuthorizesSchoolOwnership;

    private const MAX = 3000;

    public function __construct(private readonly ResultDetailsSender $sender) {}

    /**
     * The confirmation summary before a bulk send.
     */
    public function preview(Request $request): View|RedirectResponse
    {
        $school = $request->user()->school;
        $channels = $this->channels($request);
        $selection = $this->select($request, $school, issueAs: null);

        if ($selection['ready']->isEmpty() && $selection['toIssue']->isEmpty()) {
            return back()->withErrors(['send' => $this->nothingToSend($selection)]);
        }

        $summary = $this->sender->summary($selection['ready'], $channels, $selection['toIssue']);

        // Students who will be issued a token at sending are shown too, with
        // their contacts, so the count on the page is the count that is sent.
        return view('school-admin.result-details.confirm', [
            'notReady' => $this->resultsNotReady($school, collect($summary['rows']), $request->filled('examination')
                ? Examination::query()->where('school_id', $school->id)->where('uuid', $request->string('examination')->toString())->first()
                : null),
            'channelChoice' => $request->string('channel')->toString(),
            'channels' => $channels,
            'summary' => $summary,
            'toIssue' => $selection['toIssue'],
            'unusable' => $selection['unusable'],
            'inputs' => $this->carried($request),
            'whatsAppReady' => app(WhatsAppGateway::class)->forSchool($request->user()->school)->isConfigured(),
            'back' => url()->previous(),
        ]);
    }

    /**
     * The pupils in this send whose result a parent could not see yet: no
     * marks recorded, or, on a school whose parents are shown the published
     * card, not pushed to the Result Repository yet. A parent sent a token
     * for one of these finds an empty or "not ready" result, so the School
     * Admin is told by name before sending, not after.
     *
     * @param  Collection<int, array<string, mixed>>  $rows  from ResultDetailsSender::summary()
     * @return Collection<int, string>
     */
    private function resultsNotReady(School $school, Collection $rows, ?Examination $fromResultsPage): Collection
    {
        $repository = app(ResultRepository::class);

        return $rows->filter(function (array $row) {
            return $row['student'] !== null;
        })->filter(function (array $row) use ($school, $repository, $fromResultsPage) {
            $student = $row['student'];
            $examination = $row['token']?->examination ?? $fromResultsPage;

            if ($examination === null) {
                return false;
            }

            $hasMarks = ExaminationScore::query()
                ->where('student_id', $student->id)
                ->whereNotNull('score')
                ->whereIn('examination_subject_id', $examination->subjects()->select('id'))
                ->exists();

            if (! $hasMarks) {
                return true;
            }

            return $school->resultsComeFromRepository() && $repository->find($school, $student, $examination) === null;
        })->map(fn (array $row) => $row['student']->fullName())->values();
    }

    /**
     * Send one parent's WhatsApp message by hand: marks it sent and opens
     * WhatsApp at their chat with the message already typed (wa.me). For when
     * WhatsApp sending is not set up on this server, or one did not go.
     */
    public function sendOnWhatsApp(Request $request, ResultDetailDelivery $delivery): RedirectResponse
    {
        $this->authorizeSchoolOwnership($delivery);

        $whatsApp = $this->sender->whatsAppLink($delivery);

        if ($whatsApp['link'] === null) {
            return back()->withErrors(['whatsapp' => $whatsApp['problem']]);
        }

        $this->sender->markSentByHand($delivery);

        return redirect()->away($whatsApp['link']);
    }

    /**
     * Send. Straight from a row for one student, or after the confirmation.
     */
    public function send(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $channels = $this->channels($request);
        $selection = $this->select($request, $school, issueAs: $request->user());

        if ($selection['ready']->isEmpty()) {
            return back()->withErrors(['send' => $this->nothingToSend($selection)]);
        }

        $batch = $this->sender->queue($school, $selection['ready'], $channels, $request->user());

        $students = $selection['ready']->count();
        $via = collect($channels)->map->label()->join(' and ');

        AuditLog::record(
            'result-details.sent',
            "Sent result details (result link, token, admission number) for {$students} student(s) to their parents/guardians by {$via}.",
            $school,
        );

        $status = "Sending result details for {$students} student(s) by {$via}. The status of each message is below.";

        if ($selection['unusable']->isNotEmpty()) {
            $status .= " Not sent for {$selection['unusable']->count()} student(s) whose token cannot be used: "
                .$selection['unusable']->map(fn ($u) => "{$u['name']} ({$u['reason']})")->join('; ').'.';
        }

        // WhatsApp that cannot go by itself goes from the School Admin's own
        // phone, one parent after another: straight there when it is the
        // only channel, else a button on the history (emails go anyway).
        if (in_array(ResultDeliveryChannel::WhatsApp, $channels, true) && ! app(WhatsAppGateway::class)->forSchool($school)->isConfigured()) {
            if (count($channels) === 1) {
                return redirect()->route('result-pins.details.whatsapp-queue', ['batch' => $batch]);
            }

            $status .= ' Emails go automatically. For WhatsApp, tap Send to all on WhatsApp.';
        }

        return redirect()->route('result-pins.details.history', ['batch' => $batch])->with('status', $status);
    }

    /**
     * Send on WhatsApp, one parent after another, from the School Admin's
     * own phone: free, from the school's own number, ordinary WhatsApp or
     * Business. Each tap opens the next parent's chat with the message
     * already typed (sendOnWhatsApp).
     */
    public function whatsAppQueue(Request $request): View
    {
        $school = $request->user()->school;
        $batch = $request->string('batch')->toString() ?: null;

        $deliveries = ResultDetailDelivery::query()
            ->forSchool($school)
            ->where('channel', ResultDeliveryChannel::WhatsApp)
            ->whereNotNull('recipient')
            ->where('status', '!=', ResultDeliveryStatus::MissingContact)
            ->when($batch, fn ($q, $b) => $q->where('batch', $b), fn ($q) => $q->whereNotIn('status', [ResultDeliveryStatus::Sent, ResultDeliveryStatus::Delivered]))
            ->with(['student.school', 'examination', 'token.examination'])
            ->orderBy('id')
            ->limit(500)
            ->get();

        $rows = $deliveries->map(function (ResultDetailDelivery $delivery) {
            $done = $delivery->status->wentOut();

            return [
                'id' => $delivery->uuid,
                'action' => route('result-pins.details.whatsapp', $delivery),
                'parent' => (string) ($delivery->recipient_name ?: 'Parent/Guardian'),
                'student' => (string) ($delivery->student?->fullName() ?? 'Removed student'),
                'admission' => (string) ($delivery->student?->admission_number ?? ''),
                'phone' => WhatsAppNumber::display((string) $delivery->recipient),
                'done' => $done,
                'problem' => $done ? null : $this->sender->whatsAppLink($delivery)['problem'],
            ];
        })->values();

        $exam = $deliveries->first()?->examination;

        return view('school-admin.result-details.whatsapp-queue', [
            'rows' => $rows,
            'batch' => $batch,
            'title' => $exam ? "{$exam->class_name} · {$exam->term->label()} {$exam->session}" : null,
        ]);
    }

    /**
     * The sending history: every message, newest first, with its status.
     */
    public function history(Request $request): View
    {
        $school = $request->user()->school;

        // Anything a missing queue worker left waiting goes now.
        $this->sender->catchUp($school);

        $filters = [
            'batch' => $request->string('batch')->toString() ?: null,
            'status' => ResultDeliveryStatus::tryFrom($request->string('status')->toString()),
            'channel' => ResultDeliveryChannel::tryFrom($request->string('channel')->toString()),
            'search' => trim($request->string('search')->toString()) ?: null,
        ];

        $query = ResultDetailDelivery::query()
            ->forSchool($school)
            ->when($filters['batch'], fn ($q, $batch) => $q->where('batch', $batch))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['channel'], fn ($q, $channel) => $q->where('channel', $channel))
            ->when($filters['search'], fn ($q, $search) => $q->whereHas('student', fn ($s) => $s
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('admission_number', 'like', "%{$search}%")));

        $counts = (clone $query)->toBase()->reorder()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        // Why the ones that did not go did not: grouped, largest first, each
        // with what to do about it. Missing contacts group by channel (their
        // wording names each pupil's own bad number); failures by reason.
        $notSent = ResultDetailDelivery::query()
            ->forSchool($school)
            ->when($filters['batch'], fn ($q, $batch) => $q->where('batch', $batch))
            ->when($filters['channel'], fn ($q, $channel) => $q->where('channel', $channel))
            ->whereIn('status', [ResultDeliveryStatus::Failed, ResultDeliveryStatus::MissingContact])
            ->toBase()
            ->selectRaw('status, channel, error, COUNT(*) as total')
            ->groupBy('status', 'channel', 'error')
            ->get();

        $reasons = $notSent
            ->groupBy(fn ($r) => $r->status === ResultDeliveryStatus::MissingContact->value ? "missing:{$r->channel}" : "failed:{$r->channel}:{$r->error}")
            ->map(function ($group) {
                $first = $group->first();
                $channel = ResultDeliveryChannel::from($first->channel);
                $missing = $first->status === ResultDeliveryStatus::MissingContact->value;
                $error = (string) $first->error;

                return [
                    'count' => (int) $group->sum('total'),
                    'channel' => $channel,
                    'reason' => $missing
                        ? ($channel === ResultDeliveryChannel::Email
                            ? 'No parent/guardian email address (or not a valid one) on the pupil\'s record.'
                            : 'No parent/guardian WhatsApp number (or not a valid one) on the pupil\'s record.')
                        : (str_starts_with($error, 'Not sent automatically')
                            ? 'Waiting to be sent from your phone (automatic WhatsApp is not switched on).'
                            : ($error !== '' ? $error : 'The message could not be sent.')),
                    'fix' => match (true) {
                        $missing => 'Tap Add contact on the pupil below, enter the parent\'s '.($channel === ResultDeliveryChannel::Email ? 'email address' : 'phone number').', save, then Retry.',
                        str_starts_with($error, 'Not sent automatically') => 'Send them from your phone with Send to all on WhatsApp.',
                        str_contains($error, 'token') => 'Reissue the token under Exam Tokens, then Retry.',
                        str_contains($error, 'result-checking link') => 'Switch the result link back on under Exam Tokens, then Retry.',
                        default => 'Tap Retry to try again.',
                    },
                ];
            })
            ->sortByDesc('count')
            ->values();

        // WhatsApp messages still to go, for "Send to all on WhatsApp".
        $whatsAppToSend = ResultDetailDelivery::query()
            ->forSchool($school)
            ->when($filters['batch'], fn ($q, $batch) => $q->where('batch', $batch))
            ->where('channel', ResultDeliveryChannel::WhatsApp)
            ->whereNotNull('recipient')
            ->where('status', ResultDeliveryStatus::Failed)
            ->count();

        return view('school-admin.result-details.history', [
            'deliveries' => $query->with(['student', 'examination', 'sender'])->latest('id')->paginate(30)->withQueryString(),
            'counts' => collect(ResultDeliveryStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'filters' => $filters,
            'statuses' => ResultDeliveryStatus::cases(),
            'channels' => ResultDeliveryChannel::cases(),
            'whatsAppReady' => app(WhatsAppGateway::class)->forSchool($request->user()->school)->isConfigured(),
            'whatsAppToSend' => $whatsAppToSend,
            'reasons' => $reasons,
        ]);
    }

    /**
     * Retry failed and missing-contact messages: one, the ticked ones, or
     * every one in a batch. Messages already sent are never sent again.
     */
    public function retry(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'batch' => ['nullable', 'uuid'],
            'deliveries' => ['nullable', 'array', 'max:'.self::MAX],
            'deliveries.*' => ['uuid'],
        ]);

        abort_if(blank($validated['batch'] ?? null) && blank($validated['deliveries'] ?? null), 422, 'Choose what to retry.');

        $rows = ResultDetailDelivery::query()
            ->forSchool($school)
            ->whereIn('status', [ResultDeliveryStatus::Failed, ResultDeliveryStatus::MissingContact])
            ->when($validated['batch'] ?? null, fn ($q, $batch) => $q->where('batch', $batch))
            ->when($validated['deliveries'] ?? null, fn ($q, $ids) => $q->whereIn('uuid', $ids))
            ->get();

        $waiting = $this->sender->retry($school, $rows);
        $stillMissing = $rows->filter(fn ($row) => $row->fresh()->status === ResultDeliveryStatus::MissingContact)->count();

        $message = $waiting === 0
            ? 'Nothing was resent.'
            : "Resending {$waiting} message(s).";

        if ($stillMissing > 0) {
            $message .= " {$stillMissing} still have no valid contact details: add them to the student's record, then retry.";
        }

        return back()->with('status', $message);
    }

    // -------------------------------------------------------------------------

    /**
     * @return list<ResultDeliveryChannel>
     */
    private function channels(Request $request): array
    {
        $request->validate([
            'channel' => ['required', Rule::in(['email', 'whatsapp', 'both'])],
        ], ['channel.*' => 'Choose Email, WhatsApp or Both.']);

        return ResultDeliveryChannel::fromChoice($request->string('channel')->toString());
    }

    /**
     * The tokens a request names, all within this school.
     *
     * @return array{ready: Collection<int, ResultCheckingPin>, unusable: Collection<int, array{name: string, reason: string}>, toIssue: Collection<int, Student>}
     */
    private function select(Request $request, School $school, ?User $issueAs): array
    {
        $validated = $request->validate([
            'tokens' => ['nullable', 'array', 'max:'.self::MAX],
            'tokens.*' => ['uuid'],
            'batch' => ['nullable', 'uuid'],
            'session' => ['nullable', 'string', 'max:20'],
            'term' => ['nullable', Rule::enum(ExamTerm::class)],
            'class_name' => ['nullable', 'string', 'max:100'],
            'examination' => ['nullable', 'uuid'],
            'students' => ['nullable', 'array', 'max:'.self::MAX],
            'students.*' => ['uuid'],
            'all' => ['nullable', 'boolean'],
        ]);

        $tokens = ResultCheckingPin::query()->forSchool($school)->with(['boundStudent.school', 'examination']);

        // From the Results page: students of one examination.
        if (filled($validated['examination'] ?? null)) {
            $examination = Examination::query()->where('school_id', $school->id)->where('uuid', $validated['examination'])->firstOrFail();

            $students = $request->boolean('all')
                ? ExaminationResultCalculator::rosterFor($examination)
                : $school->students()->whereIn('uuid', $validated['students'] ?? [])->get();

            if ($students->isEmpty()) {
                throw ValidationException::withMessages(['send' => 'Select at least one student.']);
            }

            return $this->sender->tokensForResult($school, $examination, $students, $issueAs);
        }

        if (filled($validated['tokens'] ?? null)) {
            $tokens->whereIn('uuid', $validated['tokens']);
        } elseif (filled($validated['batch'] ?? null)) {
            $batch = ResultTokenBatch::query()->forSchool($school)->where('uuid', $validated['batch'])->firstOrFail();
            $tokens->where('batch_id', $batch->id);
        } elseif (filled($validated['session'] ?? null) && filled($validated['term'] ?? null)) {
            $tokens->whereHas('examination', fn ($exam) => $exam->where('session', $validated['session'])->where('term', $validated['term']));
        } else {
            throw ValidationException::withMessages(['send' => 'Select at least one student.']);
        }

        if (filled($validated['class_name'] ?? null)) {
            $tokens->whereHas('boundStudent', fn ($student) => $student->where('class_name', $validated['class_name']));
        }

        // One token per student and result, the newest, so a reissued token
        // replaces the old one rather than sending both.
        $found = $tokens->latest('id')->get()->unique(fn (ResultCheckingPin $pin) => $pin->bound_student_id.'-'.$pin->examination_id)->values();

        return $this->sender->sendable($school, $found) + ['toIssue' => collect()];
    }

    /**
     * The request's selection, carried from the confirmation page to the send.
     *
     * @return array<string, mixed>
     */
    private function carried(Request $request): array
    {
        return array_filter($request->only(['tokens', 'batch', 'session', 'term', 'class_name', 'examination', 'students', 'all']), fn ($v) => filled($v));
    }

    /**
     * @param  array{unusable: Collection<int, array{name: string, reason: string}>}  $selection
     */
    private function nothingToSend(array $selection): string
    {
        return $selection['unusable']->isEmpty()
            ? 'There is nothing to send: no usable tokens were found for that choice.'
            : 'There is nothing to send: '.$selection['unusable']->map(fn ($u) => "{$u['name']} ({$u['reason']})")->join('; ').'.';
    }
}
