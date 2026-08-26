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
 * Reviewing a subscription from the Super Admin panel.
 *
 * The Super Admin's only "view" for a subscription used to be
 * subscriptions.confirmation - the final step of the SCHOOL's signup wizard,
 * complete with a progress bar and "Thank You! Your Payment Has Been
 * Received". Clicking View on a pending subscription therefore threw the
 * reviewer out of their own workflow and addressed them as the school that had
 * just paid.
 *
 * These tests pin the boundary: Super Admin surfaces stay in the Super Admin
 * panel, and the school's wizard belongs to the school.
 */
beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Notification::fake();
});

function reviewableSubscription(SubscriptionStatus $status = SubscriptionStatus::PendingVerification): Subscription
{
    $school = School::factory()->create(['name' => 'Greenfield College']);

    $subscription = Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => Plan::where('key', PlanKey::Basic)->firstOrFail()->id,
        'status' => $status,
        'students_count' => 120,
        'reference' => 'REVIEW-REF-1',
    ]);

    Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'status' => PaymentStatus::Pending,
    ]);

    return $subscription;
}

function reviewingAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
}

// -----------------------------------------------------------------------------
// The review screen belongs to the Super Admin
// -----------------------------------------------------------------------------

test('a super admin reviews a subscription without leaving their panel', function () {
    $subscription = reviewableSubscription();

    $this->actingAs(reviewingAdmin())
        ->get(route('super-admin.subscriptions.show', $subscription))
        ->assertOk()
        ->assertSee('Greenfield College')
        ->assertSee('REVIEW-REF-1')
        ->assertSee('Activate subscription')
        // None of the school's signup wizard: no progress bar, no thank-you.
        ->assertDontSee('Thank You!')
        ->assertDontSee('Your Payment Has Been Received');
});

test('the subscriptions list links to the review screen, not the school wizard', function () {
    $subscription = reviewableSubscription();

    $this->actingAs(reviewingAdmin())
        ->get(route('super-admin.subscriptions.index'))
        ->assertOk()
        ->assertSee(route('super-admin.subscriptions.show', $subscription), false)
        ->assertDontSee(route('subscriptions.confirmation', $subscription), false);
});

test('a super admin opening the school wizard is sent to the review screen', function () {
    $subscription = reviewableSubscription();

    // Covers stale links, bookmarks and anything else still pointing at the
    // school's page - the redirect is what makes the boundary hold everywhere
    // rather than only on the screens that were relinked.
    $this->actingAs(reviewingAdmin())
        ->get(route('subscriptions.confirmation', $subscription))
        ->assertRedirect(route('super-admin.subscriptions.show', $subscription));
});

test('the school still sees its own confirmation page', function () {
    $subscription = reviewableSubscription();

    $schoolAdmin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $subscription->school_id,
    ]);

    // The wizard is the school's, and stays that way.
    $this->actingAs($schoolAdmin)
        ->get(route('subscriptions.confirmation', $subscription))
        ->assertOk()
        ->assertSee('Your Payment Has Been Received');
});

// -----------------------------------------------------------------------------
// Activating from the review screen
// -----------------------------------------------------------------------------

test('activating from the review screen returns to the review screen', function () {
    $subscription = reviewableSubscription();

    $this->actingAs(reviewingAdmin())
        ->from(route('super-admin.subscriptions.show', $subscription))
        ->post(route('super-admin.subscriptions.approve', $subscription))
        ->assertRedirect(route('super-admin.subscriptions.show', $subscription));

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->school->fresh()->hasActiveSubscription())->toBeTrue();
});

test('an approved subscription no longer offers the activate button', function () {
    $subscription = reviewableSubscription(SubscriptionStatus::Active);

    $this->actingAs(reviewingAdmin())
        ->get(route('super-admin.subscriptions.show', $subscription))
        ->assertOk()
        ->assertSee('Active')
        ->assertDontSee('Activate subscription');
});

// -----------------------------------------------------------------------------
// The receipt is reachable, and only by a Super Admin
// -----------------------------------------------------------------------------

test('a subscription with no receipt says so rather than erroring', function () {
    $subscription = reviewableSubscription();
    $subscription->latestPayment->update(['receipt_path' => null]);

    $this->actingAs(reviewingAdmin())
        ->get(route('super-admin.subscriptions.show', $subscription))
        ->assertOk()
        ->assertSee('No receipt was uploaded');
});

test('the receipt route is closed to a school admin', function () {
    $subscription = reviewableSubscription();

    $schoolAdmin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $subscription->school_id,
    ]);

    // Receipts are financial documents on a private disk; the Super Admin
    // panel is the only way to them.
    $this->actingAs($schoolAdmin)
        ->get(route('super-admin.subscriptions.receipt', $subscription))
        ->assertForbidden();
});

test('a school admin cannot open the super admin review screen', function () {
    $subscription = reviewableSubscription();

    $schoolAdmin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $subscription->school_id,
    ]);

    $this->actingAs($schoolAdmin)
        ->get(route('super-admin.subscriptions.show', $subscription))
        ->assertForbidden();
});
