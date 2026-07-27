<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        AuditLog::record(
            action: 'login',
            description: "{$event->user->name} logged in.",
            subject: $event->user,
        );
    }
}
