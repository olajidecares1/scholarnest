<?php

namespace App\Notifications;

use App\Models\SubscriptionTopUp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionTopUpApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public SubscriptionTopUp $topUp) {}

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
            ->subject('Your Student Slot Top-Up Was Approved')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your request for '.$this->topUp->additional_students_count.' additional student slots has been approved.')
            ->line('You can now admit up to '.$this->topUp->subscription->students_count.' students for this term.')
            ->action('Go to Dashboard', route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Student Slot Top-Up Approved',
            'body' => 'Your request for '.$this->topUp->additional_students_count.' additional student slots has been approved.',
            'url' => route('dashboard'),
        ];
    }
}
