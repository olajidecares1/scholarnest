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

function pendingSubscriptionWithPayment(): Subscription
{
    $school = School::factory()->create();
    User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

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

test('the approvals list shows pending subscriptions by default', function () {
    $subscription = pendingSubscriptionWithPayment();

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.subscriptions.index'))
        ->assertStatus(200)
        ->assertSee($subscription->school->name);
});

test('super admin can approve a pending subscription', function () {
    Notification::fake();

    $subscription = pendingSubscriptionWithPayment();
    $schoolAdmin = $subscription->school->users->first();

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.subscriptions.approve', $subscription))
        ->assertRedirect();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active);
    expect($subscription->starts_at)->not->toBeNull();
    expect($subscription->ends_at)->not->toBeNull();

    $payment = $subscription->latestPayment;
    expect($payment->status)->toBe(PaymentStatus::Verified);
    expect($payment->verified_by)->toBe($this->superAdmin->id);

    Notification::assertSentTo($schoolAdmin, SubscriptionApprovedNotification::class);
});

test('super admin can reject a pending subscription with a reason', function () {
    Notification::fake();

    $subscription = pendingSubscriptionWithPayment();
    $schoolAdmin = $subscription->school->users->first();

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.subscriptions.reject', $subscription), [
            'reason' => 'Receipt amount does not match.',
        ])
        ->assertRedirect();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Rejected);

    $payment = $subscription->latestPayment;
    expect($payment->status)->toBe(PaymentStatus::Rejected);
    expect($payment->notes)->toBe('Receipt amount does not match.');

    Notification::assertSentTo($schoolAdmin, SubscriptionRejectedNotification::class);
});

test('a school admin cannot approve subscriptions', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $subscription = pendingSubscriptionWithPayment();

    $this->actingAs($schoolAdmin)
        ->post(route('super-admin.subscriptions.approve', $subscription))
        ->assertForbidden();
});
