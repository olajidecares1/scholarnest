<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the School Admin that somebody changed their own contact details.
 *
 * Staff and guardians may keep their own phone, email and address current -
 * they are the ones who know when those change. But the school's records are
 * the school's responsibility, so a change made in a portal cannot happen
 * silently: the office needs to know a number moved, if only so a call that
 * bounces has an explanation.
 *
 * What changed is named, not just that something did. "Ada Nwosu updated their
 * profile" sends an administrator hunting; "changed their phone number" does
 * not.
 */
class PortalProfileUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $changedFields
     */
    public function __construct(
        public readonly Model $person,
        public readonly string $personName,
        public readonly string $personType,
        public readonly array $changedFields,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->personName} updated their details",
            'body' => $this->summary(),
            'person_type' => $this->personType,
            'person_id' => $this->person->getKey(),
            'changed' => $this->changedFields,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->personName} updated their details")
            ->line($this->summary())
            ->line('Their record in your dashboard already shows the new details.');
    }

    private function summary(): string
    {
        $fields = collect($this->changedFields)
            ->map(fn (string $field) => str_replace('_', ' ', $field))
            ->all();

        if ($fields === []) {
            return "{$this->personName} ({$this->personType}) saved their profile without changing anything.";
        }

        $last = array_pop($fields);
        $list = $fields === [] ? $last : implode(', ', $fields).' and '.$last;

        return "{$this->personName} ({$this->personType}) changed their {$list}.";
    }
}
