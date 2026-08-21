<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTopUpStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\SubscriptionTopUp;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Basic-plan student licences.
 *
 * A Basic school is billed per student, so its capacity is exactly the number
 * of licences the Super Admin has allocated after verifying a payment. Two
 * rules matter most, and both are asserted against the server rather than the
 * interface:
 *
 *   The Super Admin decides. A receipt is a request; the allocation is a
 *   decision, and only a Super Admin makes it.
 *
 *   The limit cannot be exceeded. Not by the form, not by a direct request,
 *   not by a double-click, and not by deactivating a student to make room.
 */
function basicSchoolWithLicences(int $allocated): array
{
    $school = School::factory()->create();

    $plan = Plan::firstOrCreate(
        ['key' => PlanKey::Basic],
        Plan::factory()->make(['key' => PlanKey::Basic, 'price_per_student_per_term' => 500])->toArray(),
    );

    $subscription = Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'students_count' => $allocated,
        'amount' => $allocated * 500,
    ]);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return [$school->fresh(), $admin, $subscription];
}

function newStudentPayload(): array
{
    return [
        'first_name' => 'Test',
        'last_name' => 'Student',
        'admission_number' => 'ADM-'.Str::random(8),
        'gender' => 'male',
        'class_name' => 'JSS 1',
    ];
}

function fillSchoolToCapacity(School $school, int $count): void
{
    Student::factory()->count($count)->create([
        'school_id' => $school->id,
        'is_active' => true,
    ]);
}

// -----------------------------------------------------------------------------
// The limit is enforced on the server
// -----------------------------------------------------------------------------

test('a school can admit students up to its allocation', function () {
    [$school, $admin] = basicSchoolWithLicences(3);
    fillSchoolToCapacity($school, 2);

    $this->actingAs($admin)
        ->post(route('students.store'), newStudentPayload())
        ->assertSessionDoesntHaveErrors();

    expect($school->students()->where('is_active', true)->count())->toBe(3);
});

test('the student after the last licence is refused', function () {
    [$school, $admin] = basicSchoolWithLicences(3);
    fillSchoolToCapacity($school, 3);

    $this->actingAs($admin)
        ->post(route('students.store'), newStudentPayload())
        ->assertSessionHasErrors('admission_number');

    // The important assertion: nothing was written.
    expect($school->students()->count())->toBe(3);
});

test('the refusal explains the limit and what to do about it', function () {
    [$school, $admin] = basicSchoolWithLicences(2);
    fillSchoolToCapacity($school, 2);

    $this->actingAs($admin)->post(route('students.store'), newStudentPayload());

    $message = session('errors')->first('admission_number');

    expect($message)->toContain('Student Limit Reached')
        ->and($message)->toContain('2 out of 2')
        ->and($message)->toContain('additional payment');
});

test('posting straight at the route does not get past the limit', function () {
    [$school, $admin] = basicSchoolWithLicences(1);
    fillSchoolToCapacity($school, 1);

    // No form, no interface - the same request a script or developer console
    // would send. The check lives in the controller, not the page.
    $this->actingAs($admin)
        ->post(route('students.store'), newStudentPayload())
        ->assertSessionHasErrors();

    expect($school->students()->count())->toBe(1);
});

test('a school cannot free a licence by deactivating and then reactivating', function () {
    [$school, $admin] = basicSchoolWithLicences(2);

    $existing = Student::factory()->count(2)->create([
        'school_id' => $school->id,
        'is_active' => true,
    ]);

    // Deactivate one, admit a replacement - both legitimate.
    $this->actingAs($admin)->post(route('students.toggle-active', $existing->first()));
    $this->actingAs($admin)->post(route('students.store'), newStudentPayload())->assertSessionDoesntHaveErrors();

    expect($school->students()->where('is_active', true)->count())->toBe(2);

    // Now reactivate the original. Without a check on this path the school
    // would be sitting at 3 active students against 2 licences.
    $this->actingAs($admin)
        ->post(route('students.toggle-active', $existing->first()))
        ->assertSessionHasErrors('student');

    expect($school->students()->where('is_active', true)->count())->toBe(2);
});

test('deactivating always works and releases a licence', function () {
    [$school, $admin] = basicSchoolWithLicences(2);
    $students = Student::factory()->count(2)->create(['school_id' => $school->id, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('students.toggle-active', $students->first()))
        ->assertSessionDoesntHaveErrors();

    expect($school->students()->where('is_active', true)->count())->toBe(1);

    // And the freed licence is genuinely usable again.
    $this->actingAs($admin)
        ->post(route('students.store'), newStudentPayload())
        ->assertSessionDoesntHaveErrors();

    expect($school->students()->where('is_active', true)->count())->toBe(2);
});

test('standard and exclusive schools are not capped', function () {
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'students_count' => 1,
    ]);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    fillSchoolToCapacity($school, 5);

    // The per-student cap belongs to Basic alone; flat-fee plans are uncapped.
    $this->actingAs($admin)
        ->post(route('students.store'), newStudentPayload())
        ->assertSessionDoesntHaveErrors();

    expect($school->students()->count())->toBe(6);
});

// -----------------------------------------------------------------------------
// Only the Super Admin decides the allocation
// -----------------------------------------------------------------------------

test('submitting a receipt does not change the allocation by itself', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(100);

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 50,
        'payment_method' => 'bank_transfer',
        'receipt' => Illuminate\Http\UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
    ]);

    // Still 100. The request is pending, not granted.
    expect($subscription->fresh()->students_count)->toBe(100)
        ->and(SubscriptionTopUp::latest('id')->first()->status)->toBe(SubscriptionTopUpStatus::PendingVerification);
});

test('the super admin allocation is what is applied, not the school request', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(100);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $topUp = SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 50,
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    // The school asked for 50; the receipt only covers 40.
    $this->actingAs($superAdmin)
        ->post(route('super-admin.subscriptions.top-ups.approve', $topUp), [
            'approved_students_count' => 40,
        ])
        ->assertRedirect();

    expect($subscription->fresh()->students_count)->toBe(140);

    $topUp->refresh();
    expect($topUp->approved_students_count)->toBe(40)
        ->and($topUp->additional_students_count)->toBe(50)   // the request is kept
        ->and($topUp->previous_students_count)->toBe(100)
        ->and($topUp->new_students_count)->toBe(140)
        ->and($topUp->verified_by)->toBe($superAdmin->id)
        ->and($topUp->verified_at)->not->toBeNull();
});

test('approving without entering a number is refused', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(100);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $topUp = SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 50,
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    // Approval must be a deliberate entry, never a click that silently accepts
    // whatever the school typed.
    $this->actingAs($superAdmin)
        ->post(route('super-admin.subscriptions.top-ups.approve', $topUp), [])
        ->assertSessionHasErrors('approved_students_count');

    expect($subscription->fresh()->students_count)->toBe(100)
        ->and($topUp->fresh()->status)->toBe(SubscriptionTopUpStatus::PendingVerification);
});

test('the same top-up cannot be approved twice', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(100);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $topUp = SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 50,
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    $this->actingAs($superAdmin)->post(route('super-admin.subscriptions.top-ups.approve', $topUp), [
        'approved_students_count' => 50,
    ]);

    // A refresh, a back button, or a double-click must not allocate again.
    $this->actingAs($superAdmin)
        ->post(route('super-admin.subscriptions.top-ups.approve', $topUp), [
            'approved_students_count' => 50,
        ])
        ->assertStatus(409);

    expect($subscription->fresh()->students_count)->toBe(150);
});

test('a school admin cannot approve their own top-up', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(100);

    $topUp = SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 50,
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    $this->actingAs($admin)
        ->post(route('super-admin.subscriptions.top-ups.approve', $topUp), [
            'approved_students_count' => 500,
        ])
        ->assertForbidden();

    expect($subscription->fresh()->students_count)->toBe(100);
});

test('an approved allocation immediately raises the ceiling', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(2);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    fillSchoolToCapacity($school, 2);

    // Blocked at 2/2.
    $this->actingAs($admin)->post(route('students.store'), newStudentPayload())->assertSessionHasErrors();

    $topUp = SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 1,
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('super-admin.subscriptions.top-ups.approve', $topUp), [
            'approved_students_count' => 1,
        ])
        ->assertSessionDoesntHaveErrors();

    expect($subscription->fresh()->students_count)->toBe(3);

    // actingAs() reuses this exact in-memory user between requests, so its
    // already-loaded school.activeSubscription relation still holds the old
    // allocation. Production reloads the user from the database on every
    // request, so re-fetch here to match that.
    $admin = $admin->fresh();

    // Now 2/3, so one more is allowed - and only one.
    $this->actingAs($admin)->post(route('students.store'), newStudentPayload())->assertSessionDoesntHaveErrors();
    $this->actingAs($admin)->post(route('students.store'), newStudentPayload())->assertSessionHasErrors();

    expect($school->students()->where('is_active', true)->count())->toBe(3);
});

// -----------------------------------------------------------------------------
// The receipt must be viewable, and only by the Super Admin
// -----------------------------------------------------------------------------

test('a super admin can view an uploaded receipt', function () {
    Illuminate\Support\Facades\Storage::fake('local');

    [$school, $admin, $subscription] = basicSchoolWithLicences(100);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 10,
        'payment_method' => 'bank_transfer',
        'receipt' => Illuminate\Http\UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
    ]);

    $topUp = SubscriptionTopUp::latest('id')->first();

    // Verifying a payment is impossible if the receipt cannot be seen.
    $this->actingAs($superAdmin)
        ->get(route('super-admin.subscriptions.top-ups.receipt', $topUp))
        ->assertOk();
});

test('a school admin cannot read receipts through the super admin route', function () {
    Illuminate\Support\Facades\Storage::fake('local');

    [$school, $admin, $subscription] = basicSchoolWithLicences(100);

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 10,
        'payment_method' => 'bank_transfer',
        'receipt' => Illuminate\Http\UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
    ]);

    $topUp = SubscriptionTopUp::latest('id')->first();

    $this->actingAs($admin)
        ->get(route('super-admin.subscriptions.top-ups.receipt', $topUp))
        ->assertForbidden();
});

// -----------------------------------------------------------------------------
// Pricing comes from the plan, and is snapshotted
// -----------------------------------------------------------------------------

test('the amount owed is calculated from the configured per-student price', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(100);

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 50,
        'payment_method' => 'bank_transfer',
        'receipt' => Illuminate\Http\UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
    ]);

    $topUp = SubscriptionTopUp::latest('id')->first();

    // 50 students at the plan's NGN 500 = NGN 25,000, exactly the worked
    // example in the brief.
    expect((float) $topUp->additional_amount)->toBe(25000.0)
        ->and((float) $topUp->price_per_student)->toBe(500.0);
});

test('changing the plan price later does not rewrite an existing request', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(100);

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 10,
        'payment_method' => 'bank_transfer',
        'receipt' => Illuminate\Http\UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
    ]);

    $topUp = SubscriptionTopUp::latest('id')->first();

    // The Super Admin raises the price after the school has already paid.
    $subscription->plan->update(['price_per_student_per_term' => 800]);

    // The submitted request keeps the price it was quoted at.
    expect((float) $topUp->fresh()->price_per_student)->toBe(500.0)
        ->and((float) $topUp->fresh()->additional_amount)->toBe(5000.0);
});

// -----------------------------------------------------------------------------
// The per-student price is configurable, not hard-coded
// -----------------------------------------------------------------------------

test('the plan pricing page renders for a super admin', function () {
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    Plan::firstOrCreate(
        ['key' => PlanKey::Basic],
        Plan::factory()->make(['key' => PlanKey::Basic, 'price_per_student_per_term' => 500])->toArray(),
    );

    $this->actingAs($superAdmin)
        ->get(route('super-admin.plans.pricing.edit'))
        ->assertOk()
        ->assertSee('Price per student, per term', false);
});

test('a school admin cannot open the plan pricing page', function () {
    [$school, $admin] = basicSchoolWithLicences(10);

    $this->actingAs($admin)
        ->get(route('super-admin.plans.pricing.edit'))
        ->assertForbidden();
});

test('a super admin can change the basic per-student price', function () {
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    $plan = Plan::firstOrCreate(
        ['key' => PlanKey::Basic],
        Plan::factory()->make(['key' => PlanKey::Basic, 'price_per_student_per_term' => 500])->toArray(),
    );

    $this->actingAs($superAdmin)
        ->put(route('super-admin.plans.pricing.update', $plan), [
            'price_per_student_per_term' => 750,
        ])
        ->assertSessionDoesntHaveErrors();

    expect((float) $plan->fresh()->price_per_student_per_term)->toBe(750.0);
});

test('a new price applies to the next request a school makes', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(100);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($superAdmin)->put(route('super-admin.plans.pricing.update', $subscription->plan), [
        'price_per_student_per_term' => 750,
    ]);

    $this->actingAs($admin->fresh())->post(route('subscription-top-up.store'), [
        'additional_students_count' => 10,
        'payment_method' => 'bank_transfer',
        'receipt' => Illuminate\Http\UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
    ]);

    $topUp = SubscriptionTopUp::latest('id')->first();

    expect((float) $topUp->price_per_student)->toBe(750.0)
        ->and((float) $topUp->additional_amount)->toBe(7500.0);
});

test('a school admin cannot change plan pricing', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(100);

    $this->actingAs($admin)
        ->put(route('super-admin.plans.pricing.update', $subscription->plan), [
            'price_per_student_per_term' => 1,
        ])
        ->assertForbidden();

    expect((float) $subscription->plan->fresh()->price_per_student_per_term)->toBe(500.0);
});
