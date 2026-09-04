<?php

use App\Enums\PlanFeature;
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

describe('facilities is available on every plan', function () {
    test('a Basic school may use it, unlike the other website features', function () {
        $school = activateSchool(School::factory()->create(), PlanKey::Basic);

        // The contrast is the point. Facilities is a record of what the school
        // HAS, not a website feature, so Basic keeps it while News, Events and
        // the site itself stay behind the paid plans.
        expect($school->canUseFeature(PlanFeature::Facilities))->toBeTrue()
            ->and($school->canUseFeature(PlanFeature::News))->toBeFalse()
            ->and($school->canUseFeature(PlanFeature::Website))->toBeFalse();
    });

    test('and so may Standard and Exclusive', function () {
        foreach ([PlanKey::Standard, PlanKey::Exclusive] as $key) {
            $school = activateSchool(School::factory()->create(), $key);

            expect($school->canUseFeature(PlanFeature::Facilities))->toBeTrue();
        }
    });

    test('the sidebar link follows, so a Basic school can reach the page', function () {
        // The interface asks canAccessRoute before it draws the link and the
        // middleware asks the same question before serving it, so both follow
        // from requiredPlans and cannot disagree.
        $school = activateSchool(School::factory()->create(), PlanKey::Basic);

        expect($school->canAccessRoute('facilities.index'))->toBeTrue();
    });

    test('a school with no active subscription still gets nothing', function () {
        // "Every plan" is not "no plan". An unpaid school is not on a plan at
        // all, and opening this up must not have opened that.
        $school = School::factory()->create();

        expect($school->canUseFeature(PlanFeature::Facilities))->toBeFalse()
            ->and($school->canAccessRoute('facilities.index'))->toBeFalse();
    });
});
