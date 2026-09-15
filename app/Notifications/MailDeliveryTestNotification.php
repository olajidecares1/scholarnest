<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A plain test message, sent from the Super Admin settings page or
 * `php artisan mail:test`, to prove this server can deliver email.
 */
class MailDeliveryTestNotification extends Notification
{
    use Queueable;

    public function __construct(public string $sentFrom) {}

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
            ->subject('AkademicNest Email Test')
            ->greeting('Email delivery is working.')
            ->line('This test message was sent from '.$this->sentFrom.' at '.now()->toDayDateTimeString().'.')
            ->line('If you are reading it, welcome emails, invoices, top-up confirmations and password reset codes can reach this address.')
            ->salutation('Regards, AkademicNest Team');
    }
}
