<?php

use App\Enums\CustomDomainSslStatus;
use App\Enums\CustomDomainStatus;
use App\Jobs\ProvisionCustomDomainSsl;
use App\Models\CustomDomain;
use App\Models\School;
use App\Models\User;
use App\Notifications\CustomDomainConnectedNotification;
use Illuminate\Support\Facades\Notification;

test('provisioning ssl activates the domain and notifies the school\'s admin users', function () {
    Notification::fake();

    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $school->id]);
    $domain = CustomDomain::factory()->create([
        'school_id' => $school->id,
        'status' => CustomDomainStatus::Verified,
        'ssl_status' => CustomDomainSslStatus::Pending,
    ]);

    (new ProvisionCustomDomainSsl($domain))->handle();

    $domain->refresh();
    expect($domain->ssl_status)->toBe(CustomDomainSslStatus::Active);
    expect($domain->ssl_issued_at)->not->toBeNull();

    Notification::assertSentTo($admin, CustomDomainConnectedNotification::class);
});
