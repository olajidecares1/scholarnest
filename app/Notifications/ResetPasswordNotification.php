<?php

namespace App\Notifications;

use App\Support\PasswordResetCode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The password-reset email, in ScholarNest's own words rather than Laravel's.
 *
 * THE LINK IS BUILT FROM APP_URL, NOT FROM THE REQUEST. That is the one thing
 * in this class that is a security control rather than presentation. Laravel's
 * route() takes its host from the incoming request, so an attacker who sends a
 * forged Host header to the forgot-password endpoint would have the reset link
 * in the victim's email point at the attacker's domain - and the victim would
 * hand over their token by clicking it. Building the URL from configuration
 * makes the header irrelevant.
 *
 * The six-digit code is deliberately in the BODY and never in the URL. Putting
 * it in the link would defeat the point of having it: the whole reason it
 * exists is that possession of the URL should not be enough.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $email,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset Your ScholarNest Password')
            ->greeting('Hello '.($notifiable->name ?: 'there').',')
            ->line('We received a request to reset your ScholarNest account password. Click the button below to create a new password.')
            ->action('Reset Password', $this->resetUrl())
            ->line('You will be asked for this verification code:')
            ->line('**'.PasswordResetCode::for($this->token).'**')
            ->line("This link and code expire in {$minutes} minutes and can only be used once.")
            // Said plainly because the code is the second half of the reset:
            // anyone holding both it and the link can change the password.
            ->line('Keep this code to yourself. ScholarNest staff will never ask you for it.')
            ->line('If you did not request a password reset, you can safely ignore this email — your password will not be changed.')
            ->salutation('— ScholarNest Team');
    }

    /**
     * Always the official address, whatever host the request arrived at.
     */
    private function resetUrl(): string
    {
        $path = route('password.reset', ['token' => $this->token], absolute: false);

        return rtrim((string) config('app.url'), '/')
            .$path
            .'?email='.urlencode($this->email);
    }
}
