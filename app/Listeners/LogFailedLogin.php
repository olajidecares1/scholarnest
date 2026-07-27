<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'unknown';

        AuditLog::record(
            action: 'login.failed',
            description: "Failed login attempt for {$email}.",
        );
    }
}
