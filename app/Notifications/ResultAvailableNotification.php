<?php

namespace App\Notifications;

use App\Models\Examination;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a parent, guardian or student that a result is ready - and nothing
 * more than that.
 *
 * Deliberately carries no scores, no grades, no average and no position. A
 * notification travels through mail servers and sits in inboxes that get
 * forwarded, shared and left open; anything in it is effectively public. So it
 * announces that a result exists and stops there. Seeing the result itself
 * costs a token, every time.
 *
 * The link goes to the token prompt rather than to a result, so following it
 * from a forwarded email gets the same empty form as anybody else.
 */
class ResultAvailableNotification extends Notification
{
    use Queueable;

    public function __construct(public Examination $examination, public Student $student) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = "{$this->student->fullName()}'s {$this->examination->name} result is ready";

        return (new MailMessage)
            ->subject($title)
            ->greeting('Hello '.$this->recipientName($notifiable).',')
            ->line("{$this->student->fullName()}'s result for {$this->examination->name} ({$this->examination->term->label()}, {$this->examination->session}) is now available.")
            ->line('Please use the Result Token provided by the school to access and view the result.')
            ->action('Enter Result Token', $this->tokenEntryUrl())
            // Spelling out the scope here saves a parent with several children
            // from trying one token on all of them and assuming it is broken.
            ->line('Your token opens this result only. Each student, and each term, has its own token.')
            ->salutation('Regards, '.$this->examination->school->name);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->student->fullName()}'s result is ready",
            'body' => "{$this->examination->name} ({$this->examination->term->label()}, {$this->examination->session}) has been shared with you. Enter the Result Token your school provided to view it.",
            'action_url' => $this->tokenEntryUrl(),
            'action_label' => 'Enter Result Token',
        ];
    }

    /**
     * What to call the recipient.
     *
     * A guardian has a single name column; a student is stored as first and
     * last name with no `name` at all, so reading one blindly greeted every
     * student with "Hello ,".
     */
    private function recipientName(object $notifiable): string
    {
        if ($notifiable instanceof Student) {
            return $notifiable->first_name;
        }

        return $notifiable->name ?? 'there';
    }

    /**
     * The token prompt for this examination's school.
     *
     * Not the portal: a Basic school has no portal, and result tokens work on
     * every plan, so this is the one route that is right for all of them.
     */
    private function tokenEntryUrl(): string
    {
        return $this->examination->school->resultLinkUrl();
    }
}
