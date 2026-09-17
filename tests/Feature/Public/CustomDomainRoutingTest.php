<?php

use App\Enums\CustomDomainStatus;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\CustomDomain;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;

beforeEach(function () {
    // Pinned rather than left to whatever APP_URL happens to be in the local
    // .env, so the redirect test's https-with-no-port expectation is
    // deterministic and doesn't depend on the developer's own setup.
    config(['app.url' => 'https://akademicanest.com']);
});

function exclusiveSchoolWithCustomDomain(string $domain, CustomDomainStatus $status = CustomDomainStatus::Verified): School
{
    $school = School::factory()->create(['is_active' => true]);
    $plan = Plan::firstOrCreate(['key' => PlanKey::Exclusive], Plan::factory()->make(['key' => PlanKey::Exclusive])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    $school->website()->create(['hero_title' => 'Welcome to '.$school->name, 'is_published' => true]);

    CustomDomain::factory()->create([
        'school_id' => $school->id,
        'domain' => $domain,
        'is_primary' => true,
        'status' => $status,
    ]);

    return $school;
}

test('a verified custom domain serves that school\'s public website', function () {
    $school = exclusiveSchoolWithCustomDomain('www.testschool.com');

    $this->get('http://www.testschool.com/')
        ->assertOk()
        ->assertSee($school->name);
});

test('the app\'s own default host is unaffected by the tenant domain wildcard', function () {
    $this->get('/')->assertRedirect();
});

test('a domain that has not been verified does not resolve any school', function () {
    exclusiveSchoolWithCustomDomain('www.unverified.com', CustomDomainStatus::PendingVerification);

    $this->get('http://www.unverified.com/')->assertNotFound();
});

test('an unknown domain returns 404', function () {
    $this->get('http://www.totally-unknown-domain.com/')->assertNotFound();
});

test('a verified custom domain stops resolving once the school is no longer on the exclusive plan', function () {
    $school = exclusiveSchoolWithCustomDomain('www.downgraded.com');
    $school->activeSubscription->update([
        'plan_id' => Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray())->id,
    ]);

    $this->get('http://www.downgraded.com/')->assertNotFound();
});

test('a verified custom domain stops resolving once the school\'s subscription is no longer active', function () {
    $school = exclusiveSchoolWithCustomDomain('www.lapsed.com');
    $school->activeSubscription->update(['status' => SubscriptionStatus::Expired]);

    // Paused rather than gone: a status page, 503 so search engines treat it
    // as temporary, and none of the school's content.
    $this->get('http://www.lapsed.com/')
        ->assertStatus(503)
        ->assertSee('temporarily unavailable')
        ->assertDontSee($school->name);
});

test('visiting the default path redirects to the verified primary custom domain', function () {
    $school = exclusiveSchoolWithCustomDomain('www.redirecttest.com');

    $this->get(route('public.school-website', $school))
        ->assertRedirect('https://www.redirecttest.com/');
});

test('the default path does not redirect when the domain is not yet verified', function () {
    $school = exclusiveSchoolWithCustomDomain('www.notyet.com', CustomDomainStatus::PendingVerification);

    $this->get(route('public.school-website', $school))
        ->assertOk();
});

test('the default path does not redirect when redirect_default_domain is turned off', function () {
    $school = exclusiveSchoolWithCustomDomain('www.noredirect.com');
    $school->primaryCustomDomain->update(['redirect_default_domain' => false]);

    $this->get(route('public.school-website', $school))
        ->assertOk();
});
