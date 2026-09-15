<?php

namespace App\Listeners;

use App\Notifications\PasswordChangedNotification;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;

class LogPasswordReset
{
    public function __construct(private readonly TransactionalMailer $mailer) {}

    /**
     * Log the reset and tell the account holder it happened.
     *
     * The "your password was changed" email goes through the recorded mailer:
     * the password has already been changed, so a mail problem must not turn
     * the success page into an error. It is logged and recorded instead.
     */
    public function handle(PasswordReset $event): void
    {
        Log::info('Password reset completed', [
            'user_id' => $event->user->getKey(),
            'email' => $event->user->email,
            'ip' => request()->ip(),
        ]);

        $this->mailer->send($event->user, new PasswordChangedNotification((string) request()->ip()), 'password-changed', $event->user);
    }
}
