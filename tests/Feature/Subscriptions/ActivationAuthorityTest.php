<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Who decides that a school is active, and what "active" means where it is
 * shown.
 *
 * The rule is that a Super Admin approving a subscription is the ONLY thing
 * that grants access, and that every screen agrees about it. Two ways that has
 * gone wrong:
 *
 *   A school looks active before anyone approved it, because the screen showing
 *   it is reading a different field from the one that grants access.
 *
 *   A school that IS approved looks pending, because a second subscription row
 *   - a renewal - outranks the approved one in whichever query happened to be
 *   used.
 */
beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Notification::fake();
});

function subFor(School $school, SubscriptionStatus $status, array $overrides = []): Subscription
{
    return Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => Plan::where('key', PlanKey::Basic)->firstOrFail()->id,
        'status' => $status,
        'students_count' => 50,
        ...$overrides,
    ]);
}

function superAdminUserForActivation(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
}

// -----------------------------------------------------------------------------
// Bug 1 - nothing but a Super Admin grants access
// -----------------------------------------------------------------------------

test('a newly registered school is not active', function () {
    $school = School::factory()->create();
    subFor($school, SubscriptionStatus::PendingVerification);

    // is_active defaults to true - it means "not suspended", not "approved" -
    // so the access check must not be satisfied by it alone.
    expect($school->fresh()->is_active)->toBeTrue()
        ->and($school->fresh()->hasActiveSubscription())->toBeFalse()
        ->and($school->fresh()->hasPlanAccess(PlanKey::Basic))->toBeFalse();
});

test('the schools list does not call an unapproved school active', function () {
    $school = School::factory()->create(['name' => 'Unapproved Academy']);
    subFor($school, SubscriptionStatus::PendingVerification);

    $response = $this->actingAs(superAdminUserForActivation())
        ->get(route('super-admin.schools.index'))
        ->assertOk();

    // The status column has to report the authoritative state. Reading
    // is_active there labelled every freshly registered school "Active"
    // before anyone had approved anything.
    $html = $response->getContent();
    $row = substr($html, strpos($html, 'Unapproved Academy'), 1200);

    expect($row)->toContain('Pending')
        ->and($row)->not->toContain('>Active<');
});

test('logging in does not activate a school', function () {
    $school = School::factory()->create();
    subFor($school, SubscriptionStatus::PendingVerification);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    // Nothing approved, so there is no active subscription at all - and the
    // pending application is still sitting there untouched.
    expect($school->fresh()->hasActiveSubscription())->toBeFalse()
        ->and($school->fresh()->activeSubscription)->toBeNull()
        ->and($school->fresh()->latestSubscription->status)->toBe(SubscriptionStatus::PendingVerification);
});

test('an unapproved school cannot reach plan features', function () {
    $school = School::factory()->create();
    subFor($school, SubscriptionStatus::PendingVerification);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)->get(route('result-pins.index'))->assertRedirect();
});

test('the suspension toggle does not approve a subscription', function () {
    $school = School::factory()->create(['is_active' => false]);
    subFor($school, SubscriptionStatus::PendingVerification);

    // "Activate" on the schools list lifts a suspension. It must not be
    // mistakable for plan approval, and must not grant access on its own.
    $this->actingAs(superAdminUserForActivation())
        ->post(route('super-admin.schools.activate', $school))
        ->assertRedirect();

    expect($school->fresh()->is_active)->toBeTrue()
        ->and($school->fresh()->hasActiveSubscription())->toBeFalse();
});

// -----------------------------------------------------------------------------
// Bug 2 - an approved school stays approved
// -----------------------------------------------------------------------------

test('an approved school keeps access when it submits a renewal', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    subFor($school, SubscriptionStatus::Active, [
        'starts_at' => now()->subMonths(2),
        'ends_at' => now()->addMonths(10),
    ]);

    expect($school->fresh()->hasActiveSubscription())->toBeTrue();

    // The school submits its next term. A newer row now exists, still pending.
    // The paid-for, approved subscription has not gone anywhere, so access
    // must not blink out because a second row was added beside it.
    subFor($school, SubscriptionStatus::PendingVerification);

    expect($school->fresh()->hasActiveSubscription())->toBeTrue()
        ->and($school->fresh()->hasPlanAccess(PlanKey::Basic))->toBeTrue();

    $this->actingAs($admin)->get(route('result-pins.index'))->assertOk();
});

test('the dashboard does not say awaiting activation while an approved subscription stands', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    subFor($school, SubscriptionStatus::Active, [
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonths(11),
    ]);
    subFor($school, SubscriptionStatus::PendingVerification);

    // The access check and the dashboard must not disagree about the same
    // school - one granting entry while the other prints "awaiting activation".
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Awaiting activation');
});

test('a rejected renewal does not revoke a standing subscription', function () {
    $school = School::factory()->create();

    subFor($school, SubscriptionStatus::Active, [
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonths(11),
    ]);
    subFor($school, SubscriptionStatus::Rejected);

    expect($school->fresh()->hasActiveSubscription())->toBeTrue();
});

test('approval is what flips a school, and it is recorded', function () {
    $school = School::factory()->create();
    $subscription = subFor($school, SubscriptionStatus::PendingVerification);
    $superAdmin = superAdminUserForActivation();

    expect($school->fresh()->hasActiveSubscription())->toBeFalse();

    $this->actingAs($superAdmin)
        ->post(route('super-admin.subscriptions.approve', $subscription))
        ->assertRedirect();

    expect($school->fresh()->hasActiveSubscription())->toBeTrue()
        ->and($subscription->fresh()->starts_at)->not->toBeNull();

    expect(AuditLog::where('action', 'subscription.approved')->exists())->toBeTrue();
});
