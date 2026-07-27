<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your EduNest Subscription Is Active')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Great news! Your payment has been verified and your '.$this->subscription->plan->name.' subscription is now active.')
            ->line('Reference: '.$this->subscription->reference)
            ->action('Go to Dashboard', route('dashboard'));
    }
}
