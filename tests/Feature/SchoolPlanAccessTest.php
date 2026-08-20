<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;

test('hasPlanAccess is false with no subscription at all', function () {
    $school = School::factory()->create();

    expect($school->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive))->toBeFalse();
});

test('hasPlanAccess is false when the subscription is not active', function () {
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Exclusive], Plan::factory()->make(['key' => PlanKey::Exclusive])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::PendingPayment]);

    expect($school->hasPlanAccess(PlanKey::Exclusive))->toBeFalse();
});

test('hasPlanAccess is false when the active plan is not in the allowed list', function () {
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Basic], Plan::factory()->make(['key' => PlanKey::Basic])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    expect($school->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive))->toBeFalse();
});

test('hasPlanAccess is true when the active plan is in the allowed list', function () {
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    expect($school->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive))->toBeTrue();
});

test('hasActiveSubscription is false with no subscription at all', function () {
    $school = School::factory()->create();

    expect($school->hasActiveSubscription())->toBeFalse();
});

test('hasActiveSubscription is false when the subscription is only pending verification', function () {
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::PendingVerification]);

    expect($school->hasActiveSubscription())->toBeFalse();
});

test('hasActiveSubscription is false when the school is suspended even with an active subscription', function () {
    $school = School::factory()->create(['is_active' => false]);
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    expect($school->hasActiveSubscription())->toBeFalse();
});

test('hasActiveSubscription is true with an active subscription and an active school', function () {
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    expect($school->hasActiveSubscription())->toBeTrue();
});
