<?php

namespace App\Listeners;

use App\Notifications\PasswordChangedNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;

class LogPasswordReset
{
    /**
     * Handle the event.
     */
    public function handle(PasswordReset $event): void
    {
        Log::info('Password reset completed', [
            'user_id' => $event->user->getKey(),
            'email' => $event->user->email,
            'ip' => request()->ip(),
        ]);

        $event->user->notify(new PasswordChangedNotification(request()->ip()));
    }
}
