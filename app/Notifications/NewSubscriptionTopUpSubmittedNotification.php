<?php

namespace App\Notifications;

use App\Models\SubscriptionTopUp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSubscriptionTopUpSubmittedNotification extends Notification
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
        $school = $this->topUp->subscription->school;

        return (new MailMessage)
            ->subject('New Student Slot Top-Up Awaiting Approval')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($school->name.' requested '.$this->topUp->additional_students_count.' additional student slots.')
            ->line('Reference: '.$this->topUp->reference)
            ->action('Review Now', route('super-admin.subscriptions.index', ['tab' => 'top-ups']));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $school = $this->topUp->subscription->school;

        return [
            'title' => 'New Top-Up Request Pending Approval',
            'body' => $school->name.' requested '.$this->topUp->additional_students_count.' additional student slots.',
            'url' => route('super-admin.subscriptions.index', ['tab' => 'top-ups']),
        ];
    }
}
