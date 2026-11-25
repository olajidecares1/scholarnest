<?php

namespace App\Services\ResultDetails;

use App\Enums\ResultDeliveryChannel;
use App\Enums\ResultDeliveryStatus;
use App\Enums\ResultTokenAccessOutcome;
use App\Jobs\SendResultDetails;
use App\Models\EmailDelivery;
use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\ResultDetailDelivery;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ResultDetailsNotification;
use App\Services\Mail\TransactionalMailer;
use App\Services\QueueWorkerHealth;
use App\Services\ResultTokenIssuer;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Support\DirectResultLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends students' result details (result link, token, admission number) to
 * their parents/guardians by email, WhatsApp or both, and keeps the record.
 *
 *   1. QUEUE. One row per student, per channel, per parent address, in
 *      result_detail_deliveries, all sharing a batch id. A student with no
 *      valid address for a channel gets one "Missing contact" row for it, so
 *      the gap is visible rather than silently skipped.
 *   2. SEND. Each pending row is sent on its own, through the existing
 *      TransactionalMailer for email and the existing WhatsApp gateway. On a
 *      server with a queue worker that happens on the queue; without one,
 *      straight after the page has been sent back, and any left over are
 *      picked up when the sending history is opened.
 *   3. RECORD. A row becomes Sent only when the email server or WhatsApp has
 *      accepted it, Failed with the reason otherwise. WhatsApp's own delivery
 *      receipts later move it to Delivered, or to Failed if it never arrived.
 *   4. RETRY. Only Failed and Missing-contact rows are sent again; anything
 *      already Sent or Delivered is left alone.
 *
 * Every row is re-checked at the moment it is sent: the token must still
 * belong to that student and that school and still work, and the address
 * must still be on that student's record. So a message can only ever carry a
 * student's details to that student's own parent.
 */
class ResultDetailsSender
{
    public function __construct(
        private readonly GuardianContacts $contacts,
        private readonly TransactionalMailer $mailer,
        private readonly WhatsAppGateway $whatsApp,
        private readonly ResultTokenIssuer $issuer,
        private readonly QueueWorkerHealth $queue,
    ) {}

    // -------------------------------------------------------------------------
    // Which tokens can be sent
    // -------------------------------------------------------------------------

    /**
     * Split tokens into those that can be sent and those that cannot, with the
     * reason. Only this school's tokens, bound to a student and a result, and
     * currently usable, are sendable: sending a revoked or used-up token
     * would only send a parent to a result that will not open.
     *
     * @param  Collection<int, ResultCheckingPin>  $tokens
     * @return array{ready: Collection<int, ResultCheckingPin>, unusable: Collection<int, array{name: string, reason: string}>}
     */
    public function sendable(School $school, Collection $tokens): array
    {
        $ready = collect();
        $unusable = collect();

        foreach ($tokens as $token) {
            $student = $token->boundStudent;

            if ($token->school_id !== $school->id || $student === null || $token->examination === null || $student->school_id !== $school->id) {
                $unusable->push(['name' => $student?->fullName() ?? 'An unbound token', 'reason' => 'not bound to a student and a result']);

                continue;
            }

            if (($reason = $token->accessRefusalReason()) !== null) {
                $unusable->push(['name' => $student->fullName(), 'reason' => 'token '.$this->describe($reason).', reissue it first']);

                continue;
            }

            $ready->push($token);
        }

        return ['ready' => $ready, 'unusable' => $unusable];
    }

    /**
     * Each student's usable token for an examination, from the Results page.
     *
     * A student with no token at all for it is listed in 'toIssue'; when
     * $issue is true one is issued for them through the existing token
     * system, so the School Admin does not have to go and issue it first.
     * A student whose token has run out (used up, or past its expiry date) is
     * given a fresh one the same way, before anything is sent. One whose token
     * was revoked or suspended is NOT: that is usually the School Admin's own
     * decision, so they are reported instead, to reissue deliberately if they
     * mean to.
     *
     * @param  Collection<int, Student>  $students
     * @return array{ready: Collection<int, ResultCheckingPin>, unusable: Collection<int, array{name: string, reason: string}>, toIssue: Collection<int, Student>}
     */
    public function tokensForResult(School $school, Examination $examination, Collection $students, ?User $issuer = null): array
    {
        $tokens = ResultCheckingPin::query()
            ->forSchool($school)
            ->where('examination_id', $examination->id)
            ->whereIn('bound_student_id', $students->pluck('id'))
            ->with(['boundStudent', 'examination'])
            ->latest('id')
            ->get()
            ->groupBy('bound_student_id');

        $ready = collect();
        $unusable = collect();
        $toIssue = collect();

        foreach ($students as $student) {
            $held = $tokens->get($student->id, collect());
            $usable = $held->first(fn (ResultCheckingPin $pin) => $pin->accessRefusalReason() === null);

            // Used up or past its date: nobody decided this token should stop
            // working, it simply ran out. Sending it would point a parent at a
            // result that will not open, so a fresh one is made first and that
            // is what goes out. A REVOKED or SUSPENDED token is different, it
            // was stopped on purpose, so it is reported below instead.
            //
            // Judged by the LATEST token only. Renewing revokes the one it
            // replaces, so older revoked tokens in a pupil's history say
            // nothing about whether the current one was stopped on purpose.
            $latest = $held->first();
            $ranOut = $latest !== null
                && in_array($latest->accessRefusalReason(), [ResultTokenAccessOutcome::Exhausted, ResultTokenAccessOutcome::Expired], true);

            if ($usable) {
                $ready->push($usable);
            } elseif ($ranOut && $issuer !== null) {
                $fresh = $this->issuer->reissue($latest, $issuer)['token'];
                $ready->push($fresh->load(['boundStudent', 'examination']));
            } elseif ($ranOut) {
                $toIssue->push($student);
            } elseif ($held->isNotEmpty()) {
                $unusable->push(['name' => $student->fullName(), 'reason' => 'token '.$this->describe($held->first()->accessRefusalReason()).', reissue it first']);
            } elseif ($issuer !== null) {
                $issued = $this->issuer->issue($school, $student, $examination, $issuer)['token'];
                $ready->push($issued->load(['boundStudent', 'examination']));
            } else {
                $toIssue->push($student);
            }
        }

        return ['ready' => $ready, 'unusable' => $unusable, 'toIssue' => $toIssue];
    }

    // -------------------------------------------------------------------------
    // The confirmation summary
    // -------------------------------------------------------------------------

    /**
     * What a send would do, without doing it.
     *
     * @param  Collection<int, ResultCheckingPin>  $tokens
     * @param  list<ResultDeliveryChannel>  $channels
     * @param  Collection<int, Student>|null  $toIssue  students who will be issued a token at sending
     * @return array{rows: list<array<string, mixed>>, students: int, messages: array<string, int>, missing: array<string, int>}
     */
    public function summary(Collection $tokens, array $channels, ?Collection $toIssue = null): array
    {
        $rows = [];
        $messages = [];
        $missing = [];

        foreach ($channels as $channel) {
            $messages[$channel->value] = 0;
            $missing[$channel->value] = 0;
        }

        $targets = $tokens->map(fn (ResultCheckingPin $token) => ['token' => $token, 'student' => $token->boundStudent])
            ->concat(($toIssue ?? collect())->map(fn (Student $student) => ['token' => null, 'student' => $student]));

        foreach ($targets as $target) {
            $found = $this->contacts->for($target['student']);
            $row = [
                'token' => $target['token'],
                'student' => $target['student'],
                'contacts' => [],
                'invalid' => $found['invalid'],
            ];

            foreach ($channels as $channel) {
                $addresses = $found[$channel->value];
                $row['contacts'][$channel->value] = $addresses;
                $messages[$channel->value] += count($addresses);

                if ($addresses === []) {
                    $missing[$channel->value]++;
                }
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'students' => count($rows), 'messages' => $messages, 'missing' => $missing];
    }

    // -------------------------------------------------------------------------
    // Sending
    // -------------------------------------------------------------------------

    /**
     * Record every message this send means, then start sending them.
     *
     * @param  Collection<int, ResultCheckingPin>  $tokens  already passed through sendable()
     * @param  list<ResultDeliveryChannel>  $channels
     * @return string the batch id
     */
    public function queue(School $school, Collection $tokens, array $channels, User $sentBy): string
    {
        $batch = (string) Str::uuid();

        foreach ($tokens as $token) {
            $student = $token->boundStudent;
            $found = $this->contacts->for($student);

            foreach ($channels as $channel) {
                $base = [
                    'school_id' => $school->id,
                    'batch' => $batch,
                    'student_id' => $student->id,
                    'result_checking_pin_id' => $token->id,
                    'examination_id' => $token->examination_id,
                    'channel' => $channel,
                    'sent_by' => $sentBy->id,
                ];

                if ($found[$channel->value] === []) {
                    ResultDetailDelivery::create($base + [
                        'status' => ResultDeliveryStatus::MissingContact,
                        'error' => $this->missingMessage($channel, $found['invalid']),
                    ]);

                    continue;
                }

                foreach ($found[$channel->value] as $contact) {
                    ResultDetailDelivery::create($base + [
                        'recipient' => $contact['address'],
                        'recipient_name' => $contact['name'],
                        'status' => ResultDeliveryStatus::Pending,
                    ]);
                }
            }
        }

        $this->start($batch);

        return $batch;
    }

    /**
     * Send the failed and missing-contact messages again. Anything already
     * Sent or Delivered is not touched.
     *
     * @param  Collection<int, ResultDetailDelivery>  $rows
     * @return int how many are now waiting to be sent
     */
    public function retry(School $school, Collection $rows): int
    {
        $waiting = 0;
        $batches = [];

        foreach ($rows as $row) {
            if ($row->school_id !== $school->id || ! $row->status->isRetryable()) {
                continue;
            }

            if ($row->status === ResultDeliveryStatus::Failed) {
                $row->update(['status' => ResultDeliveryStatus::Pending, 'error' => null]);
                $waiting++;
                $batches[$row->batch] = true;

                continue;
            }

            // Missing contact: look again, the record may have been fixed.
            $student = $row->student;
            $found = $student ? $this->contacts->for($student) : ['email' => [], 'whatsapp' => [], 'invalid' => []];
            $addresses = $found[$row->channel->value];

            if ($addresses === []) {
                $row->update(['error' => $this->missingMessage($row->channel, $found['invalid'])]);

                continue;
            }

            foreach ($addresses as $index => $contact) {
                $attributes = [
                    'recipient' => $contact['address'],
                    'recipient_name' => $contact['name'],
                    'status' => ResultDeliveryStatus::Pending,
                    'error' => null,
                ];

                $index === 0
                    ? $row->update($attributes)
                    : ResultDetailDelivery::create($row->only(['school_id', 'batch', 'student_id', 'result_checking_pin_id', 'examination_id', 'channel', 'sent_by']) + $attributes);

                $waiting++;
            }

            $batches[$row->batch] = true;
        }

        foreach (array_keys($batches) as $batch) {
            $this->start($batch);
        }

        return $waiting;
    }

    /**
     * Send every pending message in a batch.
     */
    public function process(string $batch, int $limit = PHP_INT_MAX): int
    {
        $sent = 0;

        ResultDetailDelivery::query()
            ->where('batch', $batch)
            ->where('status', ResultDeliveryStatus::Pending)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (ResultDetailDelivery $row) use (&$sent): void {
                $this->deliver($row);
                $sent++;
            });

        return $sent;
    }

    /**
     * Send messages a missing worker left waiting. Called when the sending
     * history is opened; does nothing where a worker is running.
     */
    public function catchUp(School $school, int $limit = 25): int
    {
        if ($this->queue->hasLiveWorker()) {
            return 0;
        }

        $done = 0;

        ResultDetailDelivery::query()
            ->forSchool($school)
            ->where('status', ResultDeliveryStatus::Pending)
            ->where('updated_at', '<=', now()->subSeconds(30))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (ResultDetailDelivery $row) use (&$done): void {
                $this->deliver($row);
                $done++;
            });

        return $done;
    }

    /**
     * Send one message, and record what happened.
     */
    public function deliver(ResultDetailDelivery $row): void
    {
        $row->refresh();

        if ($row->status !== ResultDeliveryStatus::Pending) {
            return;
        }

        $row->update(['attempts' => $row->attempts + 1]);

        try {
            $problem = $this->problemWith($row);

            if ($problem !== null) {
                $this->fail($row, $problem);

                return;
            }

            $token = $row->token;
            $student = $row->student;
            $school = $student->school;

            // From the school's own WhatsApp Business number when it has
            // connected one, else the platform's. Either way, the school's
            // verified WhatsApp number goes in the message so parents can
            // reach the school; not when the message already comes from it.
            $gateway = $this->whatsApp->forSchool($school);
            $isEmail = $row->channel === ResultDeliveryChannel::Email;

            $notification = new ResultDetailsNotification(
                $student,
                $token->examination,
                (string) $token->plainToken(),
                DirectResultLink::for($school, $student, (string) $token->plainToken()),
                (string) ($row->recipient_name ?: 'Parent/Guardian'),
                $isEmail || ! $gateway->fromSchoolNumber() ? $school->verifiedWhatsApp() : null,
            );

            if ($isEmail) {
                $email = $this->mailer->send($row->recipient, $notification, 'result-details', $token, $school->id);

                $email->status === EmailDelivery::SENT
                    ? $this->succeed($row)
                    : $this->fail($row, (string) ($email->error ?: 'The email could not be sent.'));

                return;
            }

            $message = $notification->forWhatsApp();
            $whatsApp = $gateway->send(
                (string) $row->recipient,
                $message['text'],
                $message['values'],
                $gateway->resultTemplate(),
            );

            $whatsApp->sent
                ? $this->succeed($row, $whatsApp->messageId)
                : $this->fail($row, (string) $whatsApp->error);
        } catch (Throwable $e) {
            report($e);
            $this->fail($row, 'Something went wrong while sending. Please try again.');
        }
    }

    /**
     * Record a WhatsApp delivery receipt against the message it is about.
     */
    public function receipt(string $messageId, string $status, ?string $error = null): void
    {
        $row = ResultDetailDelivery::query()->where('provider_message_id', $messageId)->first();

        if ($row === null) {
            return;
        }

        match ($status) {
            'delivered', 'read' => $row->status !== ResultDeliveryStatus::Failed
                ? $row->update(['status' => ResultDeliveryStatus::Delivered, 'delivered_at' => $row->delivered_at ?? now()])
                : null,
            'failed' => $row->update([
                'status' => ResultDeliveryStatus::Failed,
                'error' => $error ?: 'WhatsApp could not deliver the message.',
            ]),
            default => null,
        };
    }

    // -------------------------------------------------------------------------

    /**
     * Why this message must not go, or null if it may.
     */
    /**
     * The WhatsApp message for this parent, as a wa.me link that opens their
     * chat with it already typed, for a School Admin to send by hand when
     * WhatsApp sending is not set up on this server.
     *
     * The same checks as an automatic send (the token still works, the number
     * is still on the child's record), so a link is only ever offered for a
     * message that would be right to send. Null, with the reason, otherwise.
     *
     * @return array{link: ?string, problem: ?string}
     */
    public function whatsAppLink(ResultDetailDelivery $row): array
    {
        if ($row->channel !== ResultDeliveryChannel::WhatsApp || blank($row->recipient)) {
            return ['link' => null, 'problem' => 'This is not a WhatsApp message to a parent.'];
        }

        if (($problem = $this->problemWith($row, needsGateway: false)) !== null) {
            return ['link' => null, 'problem' => $problem];
        }

        $token = $row->token;
        $student = $row->student;

        $notification = new ResultDetailsNotification(
            $student,
            $token->examination,
            (string) $token->plainToken(),
            DirectResultLink::for($student->school, $student, (string) $token->plainToken()),
            (string) ($row->recipient_name ?: 'Parent/Guardian'),
        );

        $digits = preg_replace('/\D/', '', (string) $row->recipient);

        return ['link' => 'https://wa.me/'.$digits.'?text='.rawurlencode($notification->forWhatsApp()['text']), 'problem' => null];
    }

    /**
     * A WhatsApp message the School Admin has sent by hand through wa.me.
     */
    public function markSentByHand(ResultDetailDelivery $row): void
    {
        $row->update([
            'status' => ResultDeliveryStatus::Sent,
            'error' => 'Sent by hand on WhatsApp.',
            'attempts' => $row->attempts + 1,
            'sent_at' => now(),
        ]);
    }

    private function problemWith(ResultDetailDelivery $row, bool $needsGateway = true): ?string
    {
        $token = $row->token;
        $student = $row->student;

        // The strict match: the token, the student and the school all agree.
        if ($token === null || $student === null
            || $token->school_id !== $row->school_id
            || $student->school_id !== $row->school_id
            || $token->bound_student_id !== $student->id
            || $token->examination === null) {
            return 'This token no longer belongs to this student, so nothing was sent.';
        }

        if (($reason = $token->accessRefusalReason()) !== null) {
            return 'The token is '.$this->describe($reason).', so sending it would not open the result. Reissue it, then send again.';
        }

        if (! $student->school->resultLinkIsLive()) {
            return 'Your result-checking link is switched off. Switch it back on under Generate Exam Token, then retry.';
        }

        if ($needsGateway && $row->channel === ResultDeliveryChannel::WhatsApp && ! $this->whatsApp->forSchool($student->school)->isConfigured()) {
            return 'Not sent automatically. Send it from your phone: tap Send on WhatsApp.';
        }

        // The address must still be on this student's own record.
        $current = collect($this->contacts->forChannel($student, $row->channel))->pluck('address');

        if (! $current->contains($row->recipient)) {
            return 'This address is no longer on the student\'s record, so nothing was sent.';
        }

        return null;
    }

    private function describe(?ResultTokenAccessOutcome $reason): string
    {
        return match ($reason) {
            ResultTokenAccessOutcome::Revoked => 'revoked',
            ResultTokenAccessOutcome::Expired => 'expired',
            ResultTokenAccessOutcome::Suspended => 'suspended',
            ResultTokenAccessOutcome::Exhausted => 'used up',
            ResultTokenAccessOutcome::NotIssued => 'not issued',
            default => 'not usable',
        };
    }

    private function succeed(ResultDetailDelivery $row, ?string $messageId = null): void
    {
        $row->update([
            'status' => ResultDeliveryStatus::Sent,
            'error' => null,
            'provider_message_id' => $messageId,
            'sent_at' => now(),
        ]);
    }

    private function fail(ResultDetailDelivery $row, string $reason): void
    {
        $row->update(['status' => ResultDeliveryStatus::Failed, 'error' => $reason]);
    }

    /**
     * @param  list<string>  $invalid
     */
    private function missingMessage(ResultDeliveryChannel $channel, array $invalid): string
    {
        $what = $channel === ResultDeliveryChannel::Email ? 'parent/guardian email address' : 'parent/guardian WhatsApp number';
        $bad = array_values(array_filter($invalid, fn (string $note) => str_starts_with($note, $channel === ResultDeliveryChannel::Email ? 'Email' : 'Phone')));

        return $bad === []
            ? "No {$what} on this student's record."
            : "No valid {$what}: ".implode(' ', $bad);
    }

    /**
     * Start sending a batch: on the queue where a worker is running,
     * otherwise straight after this response.
     */
    private function start(string $batch): void
    {
        $job = new SendResultDetails($batch);

        if ($this->queue->hasLiveWorker()) {
            Bus::dispatch($job);

            return;
        }

        Bus::dispatchAfterResponse($job);
    }
}
