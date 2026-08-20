<?php

namespace App\Notifications;

use App\Models\SubscriptionTopUp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionTopUpRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public SubscriptionTopUp $topUp, public ?string $reason = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Update on Your Student Slot Top-Up')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We were unable to verify the payment for your request for '.$this->topUp->additional_students_count.' additional student slots.')
            ->line('Reference: '.$this->topUp->reference);

        if ($this->reason) {
            $message->line('Reason: '.$this->reason);
        }

        return $message
            ->line('Please review your payment details and submit a new top-up request, or contact support if you believe this is a mistake.')
            ->action('Contact Support', route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Student Slot Top-Up Rejected',
            'body' => 'Your request for '.$this->topUp->additional_students_count.' additional student slots could not be verified.',
            'url' => route('dashboard'),
        ];
    }
}
