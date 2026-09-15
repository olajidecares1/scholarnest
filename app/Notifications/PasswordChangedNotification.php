<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $ipAddress) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your AkademicNest Password Was Changed')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('This is a confirmation that the password for your AkademicNest account was just changed.')
            ->line('IP address: '.$this->ipAddress)
            ->line('Time: '.now()->toDayDateTimeString())
            ->line('If you made this change, no further action is needed.')
            ->line('If you did not change your password, please reset it immediately and contact support.')
            ->action('Reset Password Again', rtrim((string) config('app.url'), '/').route('password.request', absolute: false));
    }
}
