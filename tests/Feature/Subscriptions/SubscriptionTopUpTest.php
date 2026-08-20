<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTopUpStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionTopUp;
use App\Models\User;
use App\Notifications\NewSubscriptionTopUpSubmittedNotification;
use App\Notifications\SubscriptionTopUpApprovedNotification;
use App\Notifications\SubscriptionTopUpRejectedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function basicSchoolAdminWithSubscription(int $studentsCount = 100): array
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Basic], Plan::factory()->make(['key' => PlanKey::Basic, 'price_per_student_per_term' => 500])->toArray());
    $subscription = Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'students_count' => $studentsCount,
        'amount' => $studentsCount * 500,
    ]);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return [$admin, $subscription];
}

test('a non-basic school cannot reach the top-up page', function () {
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)->get(route('subscription-top-up.create'))->assertForbidden();
});

test('a basic-plan school can submit a top-up request and it notifies super admins', function () {
    Storage::fake('local');
    Notification::fake();

    [$admin] = basicSchoolAdminWithSubscription(100);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $response = $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 50,
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
    ]);

    $response->assertRedirect(route('students.index'));

    $topUp = SubscriptionTopUp::first();
    expect($topUp)->not->toBeNull();
    expect($topUp->additional_students_count)->toBe(50);
    expect((float) $topUp->additional_amount)->toBe(25000.0);
    expect($topUp->status)->toBe(SubscriptionTopUpStatus::PendingVerification);

    Notification::assertSentTo($superAdmin, NewSubscriptionTopUpSubmittedNotification::class);
});

test('approving a top-up increases the subscription\'s student limit in place and notifies the school', function () {
    Notification::fake();

    [$admin, $subscription] = basicSchoolAdminWithSubscription(100);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    $topUp = SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 50,
        'additional_amount' => 25000,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('super-admin.subscriptions.top-ups.approve', $topUp))
        ->assertRedirect();

    $subscription->refresh();
    $topUp->refresh();

    expect($subscription->students_count)->toBe(150);
    expect((float) $subscription->amount)->toBe(75000.0);
    expect($topUp->status)->toBe(SubscriptionTopUpStatus::Approved);
    expect($topUp->verified_by)->toBe($superAdmin->id);

    Notification::assertSentTo($admin, SubscriptionTopUpApprovedNotification::class);
});

test('rejecting a top-up leaves the subscription\'s student limit unchanged', function () {
    Notification::fake();

    [$admin, $subscription] = basicSchoolAdminWithSubscription(100);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    $topUp = SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 50,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('super-admin.subscriptions.top-ups.reject', $topUp), ['reason' => 'Receipt amount does not match.'])
        ->assertRedirect();

    $subscription->refresh();
    $topUp->refresh();

    expect($subscription->students_count)->toBe(100);
    expect($topUp->status)->toBe(SubscriptionTopUpStatus::Rejected);
    expect($topUp->notes)->toBe('Receipt amount does not match.');

    Notification::assertSentTo($admin, SubscriptionTopUpRejectedNotification::class);
});

test('a school admin cannot approve or reject top-ups', function () {
    [$admin, $subscription] = basicSchoolAdminWithSubscription();
    $topUp = SubscriptionTopUp::factory()->create(['subscription_id' => $subscription->id]);

    $this->actingAs($admin)
        ->post(route('super-admin.subscriptions.top-ups.approve', $topUp))
        ->assertForbidden();
});
