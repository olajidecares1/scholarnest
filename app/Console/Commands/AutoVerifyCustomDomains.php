<?php

namespace App\Console\Commands;

use App\Enums\CustomDomainStatus;
use App\Jobs\VerifyCustomDomainJob;
use App\Models\CustomDomain;
use Illuminate\Console\Command;

class AutoVerifyCustomDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'custom-domains:auto-verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch background re-verification for every custom domain that is not yet verified, so DNS changes are picked up automatically.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dispatched = 0;

        CustomDomain::whereIn('status', [CustomDomainStatus::PendingVerification, CustomDomainStatus::Failed])
            ->chunkById(100, function ($domains) use (&$dispatched) {
                $domains->each(function (CustomDomain $domain) use (&$dispatched) {
                    VerifyCustomDomainJob::dispatch($domain);
                    $dispatched++;
                });
            });

        $this->info("Dispatched re-verification for {$dispatched} domain(s).");

        return self::SUCCESS;
    }
}
