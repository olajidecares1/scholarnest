<?php

namespace App\Notifications;

use App\Models\Examination;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends a pupil's result token to the people entitled to use it: the pupil,
 * and every guardian linked to them.
 *
 * THE TOKEN GOES TO THE PORTAL, NOT INTO AN EMAIL. The database channel is
 * read by signing in, so the token sits behind the recipient's own password
 * and is seen by nobody else. The email says a token is waiting and links to
 * the portal; it deliberately carries no token, because mail is forwarded,
 * shared, left open on shared machines and delivered through servers this
 * application does not control. A token in an inbox is a result anybody
 * holding that inbox can open.
 *
 * That is the same reasoning as ResultAvailableNotification, which announces a
 * result without carrying one. This carries the key, so it is stricter about
 * where it puts it, not looser.
 */
class ResultTokenIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Examination $examination,
        public Student $student,
        public string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = "{$this->student->fullName()}'s result token is ready";

        return (new MailMessage)
            ->subject($title)
            ->greeting('Hello '.$this->recipientName($notifiable).',')
            ->line("{$this->student->school->name} has issued a result token for {$this->student->fullName()}'s {$this->examination->name} ({$this->examination->term->label()}, {$this->examination->session}).")
            // Said plainly, because somebody who expects a token in this email
            // and does not find one assumes the email is broken.
            ->line('For safety the token itself is not in this email. Sign in to your portal to see it.')
            ->action('Open my portal', $this->portalUrl())
            ->line('Your token opens this result only. Each pupil, and each term, has its own.')
            ->salutation('Regards, '.$this->student->school->name);
    }

    /**
     * What the portal shows. The token IS here: this is read by somebody who
     * has already signed in as the pupil or their guardian.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->student->fullName()}'s result token",
            'body' => "{$this->examination->name} ({$this->examination->term->label()}, {$this->examination->session}). Your token is {$this->token}. Enter it on your school's result page to view the result.",
            'token' => $this->token,
            'action_url' => $this->student->school->resultLinkUrl(),
            'action_label' => 'Check the result',
        ];
    }

    private function recipientName(object $notifiable): string
    {
        if ($notifiable instanceof Student) {
            return $notifiable->first_name;
        }

        return $notifiable->name ?? 'there';
    }

    /**
     * Where the recipient reads the token.
     *
     * The school's Portal hub rather than one role's sign-in, because the same
     * notification goes to a pupil and to their guardians, and the hub is the
     * door that is right for both.
     */
    private function portalUrl(): string
    {
        return route('portal.index', $this->student->school);
    }

    /**
     * The token this notification carries, for a test to assert on without
     * reaching into the array payload.
     */
    public function token(): string
    {
        return $this->token;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Guard against the token reaching a log or an exception report.
     *
     * A queued notification is serialised, and a failed job writes its payload
     * into the failed_jobs table and often into the log beside it.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'examination' => $this->examination->id,
            'student' => $this->student->id,
            'token' => '[redacted]',
        ];
    }
}
