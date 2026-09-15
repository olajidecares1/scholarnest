<?php

namespace App\Notifications;

use App\Services\Auth\PasswordResetCodes;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The password-reset email: a 6-digit code, and nothing else that unlocks the
 * account.
 *
 * There is no link. The code is typed on the page the person is already on, so
 * a copy of this email forwarded, left open or read over a shoulder is only
 * useful for EXPIRES_MINUTES, and only in the browser that asked for it.
 *
 * It never contains a password, old or new.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] public string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = PasswordResetCodes::EXPIRES_MINUTES;

        return (new MailMessage)
            ->subject('Your AkademicNest Password Reset Code')
            ->greeting('Hello '.($notifiable->name ?: 'there').',')
            ->line('We received a request to reset the password for your AkademicNest account. Enter this verification code on the password reset page:')
            ->line('# '.$this->code)
            ->line("The code expires in {$minutes} minutes and can only be used once.")
            ->line('Keep this code to yourself. AkademicNest staff will never ask you for it.')
            ->line('If you did not ask to reset your password, you can safely ignore this email. Your password will not be changed.')
            ->salutation('Regards, AkademicNest Team');
    }
}
