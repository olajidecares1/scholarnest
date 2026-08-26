<?php

use App\Enums\PaymentStatus;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Super Admin activation, end to end.
 *
 * The question these answer is whether approval is AUTHORITATIVE: once the
 * Super Admin approves, does every part of the app immediately agree that the
 * school is active, without a re-login, a cache clear, or a second approval?
 *
 * The status is never cached anywhere - the dashboard re-reads it from the
 * database on every request - so the risk is not staleness but disagreement:
 * two places deciding "active" by different rules. Each test below checks one
 * of those places against the same approval.
 */
beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Notification::fake();
});

function pendingBasicSchool(int $students = 100): array
{
    $school = School::factory()->create(['is_active' => true]);

    $admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $school->id,
    ]);

    $subscription = Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => Plan::where('key', PlanKey::Basic)->firstOrFail()->id,
        'status' => SubscriptionStatus::PendingVerification,
        'students_count' => $students,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'status' => PaymentStatus::Pending,
    ]);

    return [$school, $admin, $subscription];
}

function platformAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
}

// -----------------------------------------------------------------------------
// Before approval, the school is correctly told it is waiting
// -----------------------------------------------------------------------------

test('a submitted but unapproved school is shown as awaiting activation', function () {
    [$school, $admin] = pendingBasicSchool();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Awaiting activation');

    expect($school->fresh()->hasActiveSubscription())->toBeFalse();
});

// -----------------------------------------------------------------------------
// Approval flips everything, in one action
// -----------------------------------------------------------------------------

test('super admin approval writes Active to the database', function () {
    [$school, , $subscription] = pendingBasicSchool();

    $this->actingAs(platformAdmin())
        ->post(route('super-admin.subscriptions.approve', $subscription))
        ->assertRedirect();

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        // Dated on approval, so the term the school paid for starts when it
        // was actually granted rather than when the form was submitted.
        ->and($subscription->starts_at)->not->toBeNull()
        ->and($subscription->ends_at)->not->toBeNull();
});

test('approval also marks the payment verified, by whom and when', function () {
    [, , $subscription] = pendingBasicSchool();
    $superAdmin = platformAdmin();

    $this->actingAs($superAdmin)->post(route('super-admin.subscriptions.approve', $subscription));

    $payment = $subscription->fresh()->latestPayment;

    expect($payment->status)->toBe(PaymentStatus::Verified)
        ->and($payment->verified_by)->toBe($superAdmin->id)
        ->and($payment->verified_at)->not->toBeNull();
});

test('the awaiting-activation message is gone on the very next page load', function () {
    [$school, $admin, $subscription] = pendingBasicSchool();

    $this->actingAs(platformAdmin())->post(route('super-admin.subscriptions.approve', $subscription));

    // The same admin session as before - no re-login, no cache clear. The
    // dashboard re-reads the status from the database on every request, so the
    // next load is enough.
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Awaiting activation')
        ->assertDontSee('awaiting payment confirmation');
});

test('every gate in the app agrees the school is active straight away', function () {
    [$school, , $subscription] = pendingBasicSchool();

    $this->actingAs(platformAdmin())->post(route('super-admin.subscriptions.approve', $subscription));

    $school = $school->fresh();

    // The three checks the rest of the app is built on. They must not be able
    // to disagree with each other about the same subscription.
    expect($school->hasActiveSubscription())->toBeTrue()
        ->and($school->hasPlanAccess(PlanKey::Basic))->toBeTrue()
        ->and($school->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive))->toBeFalse();
});

test('activation opens the Basic features the school paid for', function () {
    [$school, $admin, $subscription] = pendingBasicSchool();

    // Turned away before approval...
    $this->actingAs($admin)->get(route('result-pins.index'))->assertRedirect();

    $this->actingAs(platformAdmin())->post(route('super-admin.subscriptions.approve', $subscription));

    // ...and admitted after it, with nothing else done in between.
    //
    // The user is re-fetched because actingAs() pins one in-memory instance
    // for the whole test, and that instance still holds the school relation it
    // loaded on the first request, from before approval. A real request never
    // does this: the session guard resolves the user from the database afresh
    // every time, so its relations are always re-read. Re-fetching here is what
    // makes this test model an actual second request rather than a rerun of the
    // first one's object graph.
    $this->actingAs(User::findOrFail($admin->id))
        ->get(route('result-pins.index'))
        ->assertOk();
});

test('the approved student allocation survives activation untouched', function () {
    [$school, , $subscription] = pendingBasicSchool(students: 250);

    $this->actingAs(platformAdmin())->post(route('super-admin.subscriptions.approve', $subscription));

    // The licence count is what the Super Admin approved, not a default the
    // activation step overwrote on its way past.
    expect($school->fresh()->studentSlotLimit())->toBe(250);
});

// -----------------------------------------------------------------------------
// The states stay distinguishable
// -----------------------------------------------------------------------------

test('a suspended school is not shown as awaiting activation', function () {
    [$school, $admin, $subscription] = pendingBasicSchool();

    $this->actingAs(platformAdmin())->post(route('super-admin.subscriptions.approve', $subscription));

    // Suspension is the school being switched off, which is a different thing
    // from a subscription nobody has reviewed - and must read differently.
    $school->update(['is_active' => false]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('suspended')
        ->assertDontSee('Awaiting activation');

    expect($school->fresh()->hasActiveSubscription())->toBeFalse();
});

test('a rejected subscription reads as rejected, not as awaiting activation', function () {
    [, $admin, $subscription] = pendingBasicSchool();

    $this->actingAs(platformAdmin())
        ->post(route('super-admin.subscriptions.reject', $subscription), ['reason' => 'Receipt unreadable.']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Awaiting activation');

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Rejected);
});

test('approving twice is harmless', function () {
    [$school, , $subscription] = pendingBasicSchool();
    $superAdmin = platformAdmin();

    $this->actingAs($superAdmin)->post(route('super-admin.subscriptions.approve', $subscription));
    $firstStart = $subscription->fresh()->starts_at;

    $this->actingAs($superAdmin)->post(route('super-admin.subscriptions.approve', $subscription));

    // The second approval must not restart the term the school already has.
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->starts_at->toDateTimeString())->toBe($firstStart->toDateTimeString());
});
