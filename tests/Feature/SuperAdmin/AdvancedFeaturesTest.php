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
use App\Notifications\SubscriptionApprovedNotification;
use App\Notifications\SubscriptionRejectedNotification;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

function pendingSubscriptionForSchool(School $school): Subscription
{
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();

    $subscription = Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'billing_cycle' => 'per_student_per_term',
        'status' => SubscriptionStatus::PendingVerification,
    ]);

    Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'status' => PaymentStatus::Pending,
    ]);

    return $subscription;
}

// Search

test('search finds matching schools, users, and subscriptions', function () {
    $school = School::factory()->create(['name' => 'Unique Findable Academy']);
    $subscription = pendingSubscriptionForSchool($school);

    $response = $this->actingAs($this->superAdmin)
        ->getJson(route('super-admin.search', ['q' => 'Unique Findable']));

    $response->assertOk();
    $response->assertJsonFragment(['title' => 'Unique Findable Academy']);
    $response->assertJsonFragment(['title' => $subscription->reference]);
});

test('search requires super admin', function () {
    $this->actingAs(makeSchoolAdmin())
        ->get(route('super-admin.search', ['q' => 'test']))
        ->assertForbidden();
});

test('search returns empty results for short queries', function () {
    $response = $this->actingAs($this->superAdmin)
        ->getJson(route('super-admin.search', ['q' => 'a']));

    $response->assertOk()->assertJson(['schools' => [], 'users' => [], 'subscriptions' => []]);
});

// School show page

test('super admin can view a school profile', function () {
    $school = School::factory()->create(['name' => 'Profile Test School']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.schools.show', $school))
        ->assertStatus(200)
        ->assertSee('Profile Test School');
});

// Bulk actions

test('super admin can bulk approve pending subscriptions', function () {
    Notification::fake();

    $schoolA = School::factory()->create();
    User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $schoolA->id]);
    $subscriptionA = pendingSubscriptionForSchool($schoolA);

    $schoolB = School::factory()->create();
    User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $schoolB->id]);
    $subscriptionB = pendingSubscriptionForSchool($schoolB);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.subscriptions.bulk-approve'), [
            'subscription_ids' => [$subscriptionA->id, $subscriptionB->id],
        ])
        ->assertRedirect();

    expect($subscriptionA->fresh()->status)->toBe(SubscriptionStatus::Active);
    expect($subscriptionB->fresh()->status)->toBe(SubscriptionStatus::Active);
    Notification::assertSentTimes(SubscriptionApprovedNotification::class, 2);
});

test('super admin can bulk reject pending subscriptions with a reason', function () {
    Notification::fake();

    $school = School::factory()->create();
    User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $subscription = pendingSubscriptionForSchool($school);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.subscriptions.bulk-reject'), [
            'subscription_ids' => [$subscription->id],
            'reason' => 'Bulk rejection reason',
        ])
        ->assertRedirect();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Rejected);
    expect($subscription->latestPayment->notes)->toBe('Bulk rejection reason');
    Notification::assertSentTimes(SubscriptionRejectedNotification::class, 1);
});

test('bulk approve ignores subscriptions that are not pending', function () {
    $school = School::factory()->create();
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();
    $activeSubscription = Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.subscriptions.bulk-approve'), [
            'subscription_ids' => [$activeSubscription->id],
        ])
        ->assertRedirect();

    expect($activeSubscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

// Export

test('super admin can export subscriptions as csv', function () {
    $school = School::factory()->create(['name' => 'Export Test School']);
    pendingSubscriptionForSchool($school);

    $response = $this->actingAs($this->superAdmin)
        ->get(route('super-admin.subscriptions.export', ['tab' => 'all']));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Export Test School');
});

// Notifications

test('a user can mark their own notification as read', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $subscription = pendingSubscriptionForSchool($school);

    $schoolAdmin->notify(new SubscriptionApprovedNotification($subscription));
    $notification = $schoolAdmin->notifications()->first();

    expect($notification->read_at)->toBeNull();

    $this->actingAs($schoolAdmin)
        ->get(route('notifications.read', $notification))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot mark another users notification as read', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $subscription = pendingSubscriptionForSchool($school);

    $schoolAdmin->notify(new SubscriptionApprovedNotification($subscription));
    $notification = $schoolAdmin->notifications()->first();

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->get(route('notifications.read', $notification))
        ->assertForbidden();
});

test('a user can mark all notifications as read', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $subscription = pendingSubscriptionForSchool($school);

    $schoolAdmin->notify(new SubscriptionApprovedNotification($subscription));
    $schoolAdmin->notify(new SubscriptionRejectedNotification($subscription));

    expect($schoolAdmin->unreadNotifications()->count())->toBe(2);

    $this->actingAs($schoolAdmin)
        ->post(route('notifications.read-all'))
        ->assertRedirect();

    expect($schoolAdmin->fresh()->unreadNotifications()->count())->toBe(0);
});
