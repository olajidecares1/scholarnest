<?php

namespace App\Jobs;

use App\Services\ResultDetails\ResultDetailsSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends every pending message in one batch of result details.
 *
 * Carries only the batch id: the tokens are read from their own records at
 * the moment each message is sent, so no token sits in the jobs table.
 */
class SendResultDetails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public string $batch) {}

    public function handle(ResultDetailsSender $sender): void
    {
        $sender->process($this->batch);
    }
}
