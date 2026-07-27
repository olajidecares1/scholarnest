<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSubscriptionSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

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
            ->subject('New Subscription Awaiting Approval')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->subscription->school->name.' submitted a '.$this->subscription->plan->name.' subscription for review.')
            ->line('Reference: '.$this->subscription->reference)
            ->action('Review Now', route('super-admin.subscriptions.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Subscription Pending Approval',
            'body' => $this->subscription->school->name.' submitted a '.$this->subscription->plan->name.' subscription.',
            'url' => route('super-admin.subscriptions.index'),
        ];
    }
}
