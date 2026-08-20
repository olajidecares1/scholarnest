<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPasswordResetRequested extends Notification
{
    use Queueable;

    public function __construct(
        public string $linkToken,
        public string $code,
        public CarbonInterface $expiresAt,
    ) {}

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
     *
     * The link token is a URL parameter by design (that's the whole point
     * of a reset link) - the 6-digit code is deliberately plain text in the
     * email body only, never appended to the link/URL, so the reset page
     * can require it to be typed in rather than trusting the URL alone.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your EduNest password')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We received a request to reset your EduNest password. Click the button below to continue.')
            ->action('Reset Password', route('admin.password-reset.show', $this->linkToken))
            ->line('Once there, you\'ll be asked for this verification code:')
            ->line('**'.$this->code.'**')
            ->line('This link and code expire at '.$this->expiresAt->format('g:i A \o\n M j, Y').' and can only be used once.')
            ->line('If you did not request a password reset, no action is required - your password will not be changed.');
    }
}
