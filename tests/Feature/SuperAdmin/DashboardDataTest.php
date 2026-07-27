<?php

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('dashboard stat cards reflect real totals', function () {
    School::factory()->count(2)->create();

    $plan = Plan::factory()->create();
    $school = School::factory()->create();
    Subscription::factory()->for($school)->for($plan)->create([
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->addDays(90),
    ]);

    $response = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));

    $response->assertOk();

    $statCards = $response->viewData('statCards');

    expect($statCards['schools']['total'])->toBe(3);
    expect($statCards['activeSubscriptions']['total'])->toBe(1);
});

test('monthly revenue only counts verified payments from the current month', function () {
    $subscription = Subscription::factory()->create();

    Payment::factory()->for($subscription)->create([
        'status' => PaymentStatus::Verified,
        'amount' => 50000,
        'verified_at' => now(),
    ]);

    Payment::factory()->for($subscription)->create([
        'status' => PaymentStatus::Pending,
        'amount' => 99999,
        'verified_at' => null,
    ]);

    Payment::factory()->for($subscription)->create([
        'status' => PaymentStatus::Verified,
        'amount' => 20000,
        'verified_at' => now()->subMonths(2),
    ]);

    $response = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));

    $statCards = $response->viewData('statCards');

    expect($statCards['monthlyRevenue']['total'])->toBe(50000.0);
});

test('subscription overview groups active subscriptions by plan', function () {
    $basic = Plan::factory()->create(['name' => 'Basic Plan']);
    $exclusive = Plan::factory()->create(['name' => 'Exclusive Plan']);

    Subscription::factory()->count(2)->create(['plan_id' => $basic->id, 'status' => SubscriptionStatus::Active]);
    Subscription::factory()->create(['plan_id' => $exclusive->id, 'status' => SubscriptionStatus::Active]);
    Subscription::factory()->create(['plan_id' => $basic->id, 'status' => SubscriptionStatus::Expired]);

    $response = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));

    $overview = collect($response->viewData('subscriptionOverview'))->keyBy('label');

    expect($overview['Basic Plan']['count'])->toBe(2);
    expect($overview['Exclusive Plan']['count'])->toBe(1);
});

test('schools by status classifies suspended, active, expiring soon, and expired schools', function () {
    $plan = Plan::factory()->create();

    School::factory()->create(['is_active' => false]);

    $active = School::factory()->create(['is_active' => true]);
    Subscription::factory()->for($active)->for($plan)->create([
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->addDays(90),
    ]);

    $expiringSoon = School::factory()->create(['is_active' => true]);
    Subscription::factory()->for($expiringSoon)->for($plan)->create([
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->addDays(10),
    ]);

    School::factory()->create(['is_active' => true]);

    $response = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));

    expect($response->viewData('schoolsByStatus'))->toBe([
        'active' => 1,
        'expiring_soon' => 1,
        'expired' => 1,
        'suspended' => 1,
    ]);
});

test('expiry alerts bucket active subscriptions by days remaining', function () {
    $plan = Plan::factory()->create();

    Subscription::factory()->for($plan)->create(['status' => SubscriptionStatus::Active, 'ends_at' => now()->addDays(5)]);
    Subscription::factory()->for($plan)->create(['status' => SubscriptionStatus::Active, 'ends_at' => now()->addDays(12)]);
    Subscription::factory()->for($plan)->create(['status' => SubscriptionStatus::Active, 'ends_at' => now()->addDays(25)]);
    Subscription::factory()->for($plan)->create(['status' => SubscriptionStatus::Expired]);

    $response = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));

    $alerts = $response->viewData('expiryAlerts');

    expect($alerts['sevenDays'])->toBe(1);
    expect($alerts['fifteenDays'])->toBe(2);
    expect($alerts['thirtyDays'])->toBe(3);
    expect($alerts['expired'])->toBe(1);
});

test('recent schools include plan name, status, and student count', function () {
    $plan = Plan::factory()->create(['name' => 'Exclusive Plan']);
    $school = School::factory()->create(['name' => 'Bright Academy', 'is_active' => true]);
    Subscription::factory()->for($school)->for($plan)->create([
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->addDays(90),
        'students_count' => 250,
    ]);

    $response = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));

    $recentSchool = collect($response->viewData('recentSchools'))->firstWhere('name', 'Bright Academy');

    expect($recentSchool['plan'])->toBe('Exclusive Plan');
    expect($recentSchool['status'])->toBe('active');
    expect($recentSchool['studentsCount'])->toBe(250);
});
