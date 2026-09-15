<?php

namespace App\Services\Mail;

use App\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationSender;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends the emails a school or an administrator must receive, and knows
 * whether they went.
 *
 * SENT NOW, NOT QUEUED. These emails are the result of somebody pressing a
 * button, and the page that answers needs to say whether they were sent. A
 * queued email can only ever be reported as "queued", and on a server with no
 * worker it is never sent at all.
 *
 * NEVER SILENT. Every send is recorded in email_deliveries. A failure, the
 * mail server refusing the login, a host that does not answer, a mailer left
 * on "log" in production, is logged, recorded with its reason, and handed back
 * to the caller so the page can say so and offer to send again.
 */
class TransactionalMailer
{
    public function __construct(private readonly MailReadiness $readiness) {}

    /**
     * Email one person.
     *
     * @param  User|AnonymousNotifiable|string  $to  a user, or an email address
     */
    public function send(
        User|AnonymousNotifiable|string $to,
        Notification $notification,
        string $kind,
        ?Model $about = null,
        ?int $schoolId = null,
    ): EmailDelivery {
        $notifiable = is_string($to) ? NotificationSender::route('mail', $to) : $to;
        $recipient = $this->recipientOf($notifiable);

        $record = fn (string $status, ?string $error = null) => EmailDelivery::create([
            'school_id' => $schoolId ?? ($notifiable instanceof User ? $notifiable->school_id : null),
            'kind' => $kind,
            'about_type' => $about?->getMorphClass(),
            'about_id' => $about?->getKey(),
            'recipient' => $recipient ?? '(no email address)',
            'status' => $status,
            'error' => $error,
            'sent_at' => $status === EmailDelivery::SENT ? now() : null,
        ]);

        if ($recipient === null) {
            return $record(EmailDelivery::FAILED, 'There is no email address to send to.');
        }

        if ($problem = $this->readiness->problem()) {
            Log::error('Email not sent: mail is not configured.', ['kind' => $kind, 'recipient' => $recipient, 'problem' => $problem]);

            return $record(EmailDelivery::FAILED, $problem);
        }

        try {
            // Every channel the notification declares, mail first, so the
            // in-app copy is only stored once the email has gone. An on-demand
            // recipient is an email address, so it only has mail.
            NotificationSender::sendNow(
                $notifiable,
                $notification,
                $notifiable instanceof User ? null : ['mail'],
            );
        } catch (Throwable $e) {
            Log::error('Email could not be sent.', [
                'kind' => $kind,
                'recipient' => $recipient,
                'exception' => $e,
            ]);

            return $record(EmailDelivery::FAILED, $this->explain($e));
        }

        return $record(EmailDelivery::SENT);
    }

    /**
     * Email several people the same kind of message, one delivery each.
     *
     * @param  iterable<User|string>  $recipients
     * @param  callable(User|string): Notification  $make
     * @return Collection<int, EmailDelivery>
     */
    public function sendToEach(iterable $recipients, callable $make, string $kind, ?Model $about = null, ?int $schoolId = null): Collection
    {
        $deliveries = collect();

        foreach ($recipients as $recipient) {
            $deliveries->push($this->send($recipient, $make($recipient), $kind, $about, $schoolId));
        }

        return $deliveries;
    }

    /**
     * One sentence for the page about a batch of deliveries.
     *
     * @param  Collection<int, EmailDelivery>  $deliveries
     */
    public static function failureSummary(Collection $deliveries): ?string
    {
        $failed = $deliveries->reject->wasSent();

        if ($failed->isEmpty()) {
            return null;
        }

        return sprintf(
            '%d email(s) could not be sent (%s). Reason: %s',
            $failed->count(),
            $failed->pluck('recipient')->unique()->implode(', '),
            $failed->first()->error,
        );
    }

    private function recipientOf(User|AnonymousNotifiable $notifiable): ?string
    {
        $address = $notifiable instanceof User
            ? $notifiable->email
            : $notifiable->routeNotificationFor('mail');

        if (is_array($address)) {
            $address = array_key_first($address) ?? null;
        }

        return filled($address) ? (string) $address : null;
    }

    /**
     * The mail server's reason, short enough for a page and free of anything
     * secret.
     */
    private function explain(Throwable $e): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '');

        return Str::limit($message !== '' ? $message : class_basename($e), 300);
    }
}
