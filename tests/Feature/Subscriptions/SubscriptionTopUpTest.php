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

test('a standard school CAN reach the top-up page now', function () {
    // It used to be refused. Standard is sold per student now, so it is capped
    // per student, so it must be able to buy more, a plan capped in one place
    // and unable to top up in another would be a trap.
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create([
        'school_id' => $school->id, 'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active, 'students_count' => 50,
    ]);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)->get(route('subscription-top-up.create'))->assertOk();
});

test('an exclusive school cannot - it is not sold per student', function () {
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Exclusive], Plan::factory()->make(['key' => PlanKey::Exclusive])->toArray());
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

    // The allocation is now entered by the Super Admin rather than taken from
    // the school's request, so approving means naming a number. Here it
    // matches what was asked for, which is the ordinary case.
    $this->actingAs($superAdmin)
        ->post(route('super-admin.subscriptions.top-ups.approve', $topUp), [
            'approved_students_count' => 50,
        ])
        ->assertRedirect();

    $subscription->refresh();
    $topUp->refresh();

    expect($subscription->students_count)->toBe(150);
    expect((float) $subscription->amount)->toBe(75000.0);
    expect($topUp->status)->toBe(SubscriptionTopUpStatus::Approved);
    expect($topUp->verified_by)->toBe($superAdmin->id);
    expect($topUp->approved_students_count)->toBe(50);

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

test('a subscription with no recorded student count says so instead of throwing', function () {
    // students_count is nullable, and a per-student subscription that never
    // recorded one used to take the whole page down: the capacity card was
    // handed nothing and read a figure out of it. The school met a server
    // error on the page whose entire purpose is to sell them more students.
    [$admin] = basicSchoolAdminWithSubscription();
    $admin->school->activeSubscription->update(['students_count' => null]);

    $this->actingAs($admin)
        ->get(route('subscription-top-up.create'))
        ->assertOk()
        ->assertSee('Your student/pupil capacity is not recorded yet')
        ->assertDontSee('Request Additional Student/Pupil Spaces');
});

test('the capacity card leaves itself out rather than throwing when there is no capacity', function () {
    [$admin] = basicSchoolAdminWithSubscription();
    $admin->school->activeSubscription->update(['students_count' => null]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Student/Pupil Capacity');
});
