<?php

use App\Enums\CustomDomainSslStatus;
use App\Enums\CustomDomainStatus;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\CustomDomain;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CustomDomainVerificationService;

function customDomainSchoolAdmin(PlanKey $planKey = PlanKey::Exclusive, SubscriptionStatus $status = SubscriptionStatus::Active): User
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => $planKey], Plan::factory()->make(['key' => $planKey])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => $status]);

    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

test('a school admin on the standard plan cannot access custom domain management', function () {
    $admin = customDomainSchoolAdmin(PlanKey::Standard);

    $this->actingAs($admin)
        ->get(route('custom-domain.index'))
        ->assertForbidden();
});

test('a school admin on the basic plan cannot access custom domain management', function () {
    $admin = customDomainSchoolAdmin(PlanKey::Basic);

    $this->actingAs($admin)
        ->get(route('custom-domain.index'))
        ->assertForbidden();
});

test('a school admin with no active subscription cannot access custom domain management', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    // Blocked earlier by the school_activated gate (no subscription at all) before
    // the custom-domain plan-tier check ever runs - redirected to the dashboard, not 403'd.
    $this->actingAs($admin)
        ->get(route('custom-domain.index'))
        ->assertRedirect(route('dashboard'));
});

test('a school admin on the exclusive plan can access custom domain management', function () {
    $admin = customDomainSchoolAdmin();

    $this->actingAs($admin)
        ->get(route('custom-domain.index'))
        ->assertStatus(200);
});

test('a school admin can add the first custom domain and it becomes primary', function () {
    $admin = customDomainSchoolAdmin();

    $this->actingAs($admin)
        ->post(route('custom-domain.store'), ['domain' => 'www.myschool.com'])
        ->assertRedirect();

    $domain = CustomDomain::where('domain', 'www.myschool.com')->firstOrFail();
    expect($domain->school_id)->toBe($admin->school_id);
    expect($domain->is_primary)->toBeTrue();
    expect($domain->status)->toBe(CustomDomainStatus::PendingVerification);
});

test('adding a domain rejects an invalid format', function () {
    $admin = customDomainSchoolAdmin();

    $this->actingAs($admin)
        ->post(route('custom-domain.store'), ['domain' => 'http://not a domain'])
        ->assertSessionHasErrors('domain');
});

test('adding a domain rejects one already in use by another school', function () {
    $admin = customDomainSchoolAdmin();
    CustomDomain::factory()->create(['domain' => 'www.taken.com']);

    $this->actingAs($admin)
        ->post(route('custom-domain.store'), ['domain' => 'www.taken.com'])
        ->assertSessionHasErrors('domain');
});

test('verifying a domain marks it verified when the service succeeds', function () {
    $admin = customDomainSchoolAdmin();
    $domain = CustomDomain::factory()->create(['school_id' => $admin->school_id, 'status' => CustomDomainStatus::PendingVerification]);

    $this->mock(CustomDomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturnUsing(function (CustomDomain $domain) {
            $domain->update(['status' => CustomDomainStatus::Verified, 'verified_at' => now()]);

            return true;
        });
    });

    $this->actingAs($admin)
        ->post(route('custom-domain.verify', $domain))
        ->assertRedirect();

    expect($domain->refresh()->status)->toBe(CustomDomainStatus::Verified);
});

test('verifying a domain records a failure when the service cannot confirm ownership', function () {
    $admin = customDomainSchoolAdmin();
    $domain = CustomDomain::factory()->create(['school_id' => $admin->school_id, 'status' => CustomDomainStatus::PendingVerification]);

    $this->mock(CustomDomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturnUsing(function (CustomDomain $domain) {
            $domain->update(['status' => CustomDomainStatus::Failed, 'last_check_error' => 'No TXT record found.']);

            return false;
        });
    });

    $this->actingAs($admin)
        ->post(route('custom-domain.verify', $domain))
        ->assertRedirect();

    expect($domain->refresh()->status)->toBe(CustomDomainStatus::Failed);
});

test('a school admin cannot verify another school\'s domain', function () {
    $admin = customDomainSchoolAdmin();
    $otherSchool = School::factory()->create();
    $domain = CustomDomain::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($admin)
        ->post(route('custom-domain.verify', $domain))
        ->assertForbidden();
});

test('the status endpoint returns the domain\'s current verification and SSL state as JSON', function () {
    $admin = customDomainSchoolAdmin();
    $domain = CustomDomain::factory()->create([
        'school_id' => $admin->school_id,
        'status' => CustomDomainStatus::Verified,
        'ssl_status' => CustomDomainSslStatus::Issuing,
    ]);

    $this->actingAs($admin)
        ->getJson(route('custom-domain.status', $domain))
        ->assertOk()
        ->assertJson([
            'status' => 'verified',
            'ssl_status' => 'issuing',
            'is_live' => false,
        ]);
});

test('a school admin cannot poll the status of another school\'s domain', function () {
    $admin = customDomainSchoolAdmin();
    $otherSchool = School::factory()->create();
    $domain = CustomDomain::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($admin)
        ->getJson(route('custom-domain.status', $domain))
        ->assertForbidden();
});

test('a school admin can set a different domain as primary', function () {
    $admin = customDomainSchoolAdmin();
    $first = CustomDomain::factory()->create(['school_id' => $admin->school_id, 'is_primary' => true]);
    $second = CustomDomain::factory()->create(['school_id' => $admin->school_id, 'is_primary' => false]);

    $this->actingAs($admin)
        ->post(route('custom-domain.set-primary', $second))
        ->assertRedirect();

    expect($first->refresh()->is_primary)->toBeFalse();
    expect($second->refresh()->is_primary)->toBeTrue();
});

test('a school admin can toggle the default-domain redirect', function () {
    $admin = customDomainSchoolAdmin();
    $domain = CustomDomain::factory()->create(['school_id' => $admin->school_id, 'redirect_default_domain' => true]);

    $this->actingAs($admin)
        ->post(route('custom-domain.toggle-redirect', $domain))
        ->assertRedirect();

    expect($domain->refresh()->redirect_default_domain)->toBeFalse();
});

test('a school admin can replace a domain and it resets to pending verification', function () {
    $admin = customDomainSchoolAdmin();
    $domain = CustomDomain::factory()->create([
        'school_id' => $admin->school_id,
        'status' => CustomDomainStatus::Verified,
        'domain' => 'www.old-domain.com',
    ]);

    $this->actingAs($admin)
        ->put(route('custom-domain.update', $domain), ['domain' => 'www.new-domain.com'])
        ->assertRedirect();

    $domain->refresh();
    expect($domain->domain)->toBe('www.new-domain.com');
    expect($domain->status)->toBe(CustomDomainStatus::PendingVerification);
});

test('a school admin can remove a domain and primary status transfers to another domain', function () {
    $admin = customDomainSchoolAdmin();
    $primary = CustomDomain::factory()->create(['school_id' => $admin->school_id, 'is_primary' => true]);
    $secondary = CustomDomain::factory()->create(['school_id' => $admin->school_id, 'is_primary' => false]);

    $this->actingAs($admin)
        ->delete(route('custom-domain.destroy', $primary))
        ->assertRedirect();

    expect(CustomDomain::find($primary->id))->toBeNull();
    expect($secondary->refresh()->is_primary)->toBeTrue();
});

test('a school admin cannot modify another school\'s domain', function () {
    $admin = customDomainSchoolAdmin();
    $otherSchool = School::factory()->create();
    $domain = CustomDomain::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($admin)
        ->delete(route('custom-domain.destroy', $domain))
        ->assertForbidden();
});

test('recordsContainToken matches a TXT record containing the exact token', function () {
    $service = new CustomDomainVerificationService;

    expect($service->recordsContainToken([
        ['txt' => 'unrelated-value'],
        ['txt' => 'the-real-token'],
    ], 'the-real-token'))->toBeTrue();

    expect($service->recordsContainToken([
        ['txt' => 'unrelated-value'],
    ], 'the-real-token'))->toBeFalse();

    expect($service->recordsContainToken([], 'the-real-token'))->toBeFalse();
});

test('routingRecordsMatchTarget matches a CNAME record pointing at the configured target', function () {
    $service = new CustomDomainVerificationService;

    expect($service->routingRecordsMatchTarget(
        [['target' => 'edunest.example.com']],
        [],
        'edunest.example.com',
        null,
    ))->toBeTrue();

    // Trailing dots (a fully-qualified DNS name) shouldn't cause a false mismatch.
    expect($service->routingRecordsMatchTarget(
        [['target' => 'edunest.example.com.']],
        [],
        'edunest.example.com',
        null,
    ))->toBeTrue();

    expect($service->routingRecordsMatchTarget(
        [['target' => 'somewhere-else.com']],
        [],
        'edunest.example.com',
        null,
    ))->toBeFalse();

    expect($service->routingRecordsMatchTarget([], [], 'edunest.example.com', null))->toBeFalse();
});

test('routingRecordsMatchTarget falls back to an A record when a target IP is configured', function () {
    $service = new CustomDomainVerificationService;

    expect($service->routingRecordsMatchTarget(
        [],
        [['ip' => '203.0.113.10']],
        'edunest.example.com',
        '203.0.113.10',
    ))->toBeTrue();

    expect($service->routingRecordsMatchTarget(
        [],
        [['ip' => '203.0.113.99']],
        'edunest.example.com',
        '203.0.113.10',
    ))->toBeFalse();

    // No A record IP configured means A records are never consulted, even if present.
    expect($service->routingRecordsMatchTarget(
        [],
        [['ip' => '203.0.113.10']],
        'edunest.example.com',
        null,
    ))->toBeFalse();
});
