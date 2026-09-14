<?php

namespace App\Jobs;

use App\Models\CustomDomain;
use App\Services\CustomDomainVerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Wraps CustomDomainVerificationService::verify() for background,
 * scheduler-driven re-verification, the exact same check the "Verify Now"
 * button runs synchronously, just dispatched automatically so DNS changes a
 * school makes are picked up without them needing to return to the wizard.
 */
class VerifyCustomDomainJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public CustomDomain $domain) {}

    public function handle(CustomDomainVerificationService $verifier): void
    {
        $verifier->verify($this->domain);
    }
}
