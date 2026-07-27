<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription, public ?string $reason = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Update on Your EduNest Subscription')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We were unable to verify the payment for your '.$this->subscription->plan->name.' subscription.')
            ->line('Reference: '.$this->subscription->reference);

        if ($this->reason) {
            $message->line('Reason: '.$this->reason);
        }

        return $message
            ->line('Please review your payment details and submit a new subscription request, or contact support if you believe this is a mistake.')
            ->action('Contact Support', route('dashboard'));
    }
}
