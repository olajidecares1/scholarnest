<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A school has registered, and the ScholarNest Team should know once.
 *
 * Sent through TeamNotifier, which claims the event before anything goes out -
 * see that class for why a second registration notification cannot exist for
 * the same school.
 */
class SchoolRegisteredNotification extends Notification
{
    use Queueable;

    public function __construct(public School $school) {}

    /**
     * The event this notification is about.
     *
     * Keyed on the school rather than on the request, because "this school
     * registered" is the thing that happens once. A retried request, a
     * refreshed page or a re-delivered job all produce the same key and are
     * therefore the same event.
     */
    public static function eventKeyFor(School $school): string
    {
        return 'school.registered:'.$school->uuid;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New School Registration - '.$this->school->name)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->school->name.' has registered on ScholarNest and is awaiting a subscription.')
            ->line('School code: '.$this->school->school_code)
            ->line('Contact: '.$this->school->billing_email.($this->school->billing_phone ? ' · '.$this->school->billing_phone : ''))
            ->line('Registered: '.$this->school->created_at->format('j M Y, g:ia'))
            ->action('View School', route('super-admin.schools.index'));
    }

    /**
     * What the dashboard shows, and what the brief asked to be on it: the
     * school, how to reach them, when they registered, and where they are in
     * the process.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New School Registration',
            'body' => $this->school->name.' has registered and is awaiting a subscription.',
            'url' => route('super-admin.schools.index'),

            'event_key' => self::eventKeyFor($this->school),

            'school_uuid' => $this->school->uuid,
            'school_name' => $this->school->name,
            'school_code' => $this->school->school_code,
            'contact_email' => $this->school->billing_email,
            'contact_phone' => $this->school->billing_phone,
            'registered_at' => $this->school->created_at?->toIso8601String(),

            // Where the registration stands. A school exists before it has a
            // subscription, and that gap is the whole reason the team is being
            // told - somebody has to decide what happens next.
            'status' => 'Awaiting subscription',
        ];
    }
}
