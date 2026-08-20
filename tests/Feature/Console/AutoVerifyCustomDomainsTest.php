<?php

use App\Enums\CustomDomainStatus;
use App\Jobs\VerifyCustomDomainJob;
use App\Models\CustomDomain;
use Illuminate\Support\Facades\Queue;

test('it dispatches a verification job for every pending or failed domain, and skips already-verified ones', function () {
    Queue::fake();

    $pending = CustomDomain::factory()->create(['status' => CustomDomainStatus::PendingVerification]);
    $failed = CustomDomain::factory()->create(['status' => CustomDomainStatus::Failed]);
    $verified = CustomDomain::factory()->create(['status' => CustomDomainStatus::Verified]);

    $this->artisan('custom-domains:auto-verify')->assertSuccessful();

    Queue::assertPushed(VerifyCustomDomainJob::class, 2);
    Queue::assertPushed(fn (VerifyCustomDomainJob $job) => $job->domain->is($pending));
    Queue::assertPushed(fn (VerifyCustomDomainJob $job) => $job->domain->is($failed));
    Queue::assertNotPushed(fn (VerifyCustomDomainJob $job) => $job->domain->is($verified));
});

test('it dispatches nothing when there are no unverified domains', function () {
    Queue::fake();

    CustomDomain::factory()->create(['status' => CustomDomainStatus::Verified]);

    $this->artisan('custom-domains:auto-verify')->assertSuccessful();

    Queue::assertNotPushed(VerifyCustomDomainJob::class);
});
