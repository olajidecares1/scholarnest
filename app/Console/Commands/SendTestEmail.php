<?php

namespace App\Console\Commands;

use App\Notifications\MailDeliveryTestNotification;
use App\Services\Mail\MailReadiness;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Console\Command;

/**
 * Prove this server can deliver email, from the command line.
 *
 * Prints the mail settings in use (never the password) and the mail server's
 * exact reason when the message is refused.
 */
class SendTestEmail extends Command
{
    protected $signature = 'mail:test {email : Where to send the test message}';

    protected $description = 'Send a test email and report exactly whether it was delivered to the mail server';

    public function handle(TransactionalMailer $mailer, MailReadiness $readiness): int
    {
        $this->components->twoColumnDetail('<fg=gray>Setting</>', '<fg=gray>Value</>');

        foreach ($readiness->summary() as $label => $value) {
            $this->components->twoColumnDetail($label, $value);
        }

        $delivery = $mailer->send((string) $this->argument('email'), new MailDeliveryTestNotification('the command line'), 'test');

        if (! $delivery->wasSent()) {
            $this->components->error('Not sent: '.$delivery->error);

            return self::FAILURE;
        }

        $this->components->info("Sent to {$delivery->recipient}. The mail server accepted it; check that inbox (and its spam folder).");

        return self::SUCCESS;
    }
}
