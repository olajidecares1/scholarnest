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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    expect($message)->toContain('Student/Pupil Capacity Reached')
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

test('standard schools ARE capped now, like basic', function () {
    // This test used to assert the opposite. Standard's flat ₦200,000 term fee
    // was replaced by ₦1,000 a pupil, so it is sold per student and capped per
    // student - the same rule, at its own price. Only its BILLING moved;
    // its features are untouched.
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'students_count' => 5,
    ]);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    fillSchoolToCapacity($school, 5);

    $this->actingAs($admin)->post(route('students.store'), newStudentPayload());

    // The sixth is refused against an allocation of five.
    expect($school->students()->count())->toBe(5);
});

test('exclusive schools are still uncapped', function () {
    // Exclusive is not sold per student, so it has no such cap.
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Exclusive], Plan::factory()->make(['key' => PlanKey::Exclusive])->toArray());
    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'students_count' => 1,
    ]);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    fillSchoolToCapacity($school, 5);

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
        'receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
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
    Storage::fake('local');

    [$school, $admin, $subscription] = basicSchoolWithLicences(100);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 10,
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
    ]);

    $topUp = SubscriptionTopUp::latest('id')->first();

    // Verifying a payment is impossible if the receipt cannot be seen.
    $this->actingAs($superAdmin)
        ->get(route('super-admin.subscriptions.top-ups.receipt', $topUp))
        ->assertOk();
});

test('a school admin cannot read receipts through the super admin route', function () {
    Storage::fake('local');

    [$school, $admin, $subscription] = basicSchoolWithLicences(100);

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 10,
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
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
        'receipt' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
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
        'receipt' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
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
        'receipt' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
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

// -----------------------------------------------------------------------------
// One cumulative capacity, however many times it is topped up
// -----------------------------------------------------------------------------

/**
 * Request and approve one top-up, returning the school's capacity afterwards.
 */
function topUpAndApprove(School $school, Subscription $subscription, User $superAdmin, int $additional): int
{
    $topUp = SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => $additional,
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    test()->actingAs($superAdmin)->post(route('super-admin.subscriptions.top-ups.approve', $topUp), [
        'approved_students_count' => $additional,
    ]);

    return (int) $school->fresh()->studentSlotLimit();
}

test('three successive top-ups add up rather than replacing each other', function () {
    [$school, , $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    expect($school->fresh()->studentSlotLimit())->toBe(50);

    // The rule the brief is emphatic about: each approval ADDS. A school that
    // buys 20 more must end on 70, never on 20 - the new allocation replacing
    // the old one is the failure being guarded against here.
    expect(topUpAndApprove($school, $subscription, $superAdmin, 20))->toBe(70)
        ->and(topUpAndApprove($school, $subscription, $superAdmin, 30))->toBe(100);
});

test('a top-up never overwrites the existing allocation', function () {
    [$school, , $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $after = topUpAndApprove($school, $subscription, $superAdmin, 20);

    // 70, not 20. Stated separately from the test above because overwriting is
    // the specific mistake, and it should fail loudly and on its own.
    expect($after)->toBe(70)
        ->and($after)->not->toBe(20);
});

test('capacity is one number, not a set of batches', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    fillSchoolToCapacity($school, 50);
    topUpAndApprove($school, $subscription, $superAdmin, 20);

    // The 51st student is admitted out of the single pool of 70, not out of a
    // separate "second batch" that would have to be tracked and drawn down.
    $this->actingAs($admin)->post(route('students.store'), newStudentPayload())
        ->assertSessionHasNoErrors();

    expect($school->students()->where('is_active', true)->count())->toBe(51)
        ->and($school->fresh()->studentSlotLimit())->toBe(70);
});

test('the school can fill the topped-up capacity exactly and no further', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    fillSchoolToCapacity($school, 50);
    topUpAndApprove($school, $subscription, $superAdmin, 20);

    // Up to 70...
    fillSchoolToCapacity($school, 19);

    $this->actingAs($admin)->post(route('students.store'), newStudentPayload())
        ->assertSessionHasNoErrors();

    expect($school->students()->where('is_active', true)->count())->toBe(70);

    // ...and the 71st is refused, exactly as the 51st was before the top-up.
    $this->actingAs($admin)->post(route('students.store'), newStudentPayload())
        ->assertSessionHasErrors('admission_number');

    expect($school->students()->where('is_active', true)->count())->toBe(70);
});

test('a pending top-up leaves the ceiling where it was', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(50);

    fillSchoolToCapacity($school, 50);

    SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 20,
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    // Requesting is not buying. Until a Super Admin approves it the school is
    // still at 50, and student 51 is still refused.
    expect($school->fresh()->studentSlotLimit())->toBe(50);

    $this->actingAs($admin)->post(route('students.store'), newStudentPayload())
        ->assertSessionHasErrors('admission_number');

    expect($school->students()->where('is_active', true)->count())->toBe(50);
});

test('a rejected top-up never becomes capacity, even after later approvals', function () {
    [$school, , $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $rejected = SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 999,
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    $this->actingAs($superAdmin)->post(route('super-admin.subscriptions.top-ups.reject', $rejected), [
        'reason' => 'Receipt did not match the amount.',
    ]);

    expect($school->fresh()->studentSlotLimit())->toBe(50);

    // A later, genuine top-up adds only itself - the rejected 999 does not
    // reappear in the total.
    expect(topUpAndApprove($school, $subscription, $superAdmin, 20))->toBe(70);
});

test('every top-up is kept as history while capacity stays a single figure', function () {
    [$school, , $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    topUpAndApprove($school, $subscription, $superAdmin, 20);
    topUpAndApprove($school, $subscription, $superAdmin, 30);

    $history = SubscriptionTopUp::where('subscription_id', $subscription->id)->orderBy('id')->get();

    // The rows record what happened and what each one moved the figure from
    // and to - they are an audit trail, not three separate allowances.
    expect($history)->toHaveCount(2)
        ->and($history[0]->previous_students_count)->toBe(50)
        ->and($history[0]->new_students_count)->toBe(70)
        ->and($history[1]->previous_students_count)->toBe(70)
        ->and($history[1]->new_students_count)->toBe(100)
        ->and($school->fresh()->studentSlotLimit())->toBe(100);
});

// -----------------------------------------------------------------------------
// The Super Admin can see the whole picture
// -----------------------------------------------------------------------------

test('the super admin sees a school\'s cumulative capacity and how it got there', function () {
    [$school, , $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    fillSchoolToCapacity($school, 40);
    topUpAndApprove($school, $subscription, $superAdmin, 20);

    $response = $this->actingAs($superAdmin)
        ->get(route('super-admin.schools.show', $school))
        ->assertOk();

    // The figures the brief asks for: what they started with, what they have
    // now, what is used, what is left.
    $response->assertSee('Student Capacity')
        ->assertSee('Initial allocation')
        ->assertSee('Total approved')
        ->assertSee('Remaining');

    expect($response->viewData('capacity'))
        ->toMatchArray(['initial' => 50, 'allocated' => 70, 'used' => 40, 'remaining' => 30]);
});

test('the capacity history shows each top-up moving the single figure', function () {
    [$school, , $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    topUpAndApprove($school, $subscription, $superAdmin, 20);
    topUpAndApprove($school, $subscription, $superAdmin, 30);

    $response = $this->actingAs($superAdmin)
        ->get(route('super-admin.schools.show', $school))
        ->assertOk();

    // Two history rows, one cumulative total - not three separate allowances.
    expect($response->viewData('topUps'))->toHaveCount(2)
        ->and($response->viewData('capacity')['allocated'])->toBe(100);

    $response->assertSee('Capacity history');
});

test('a school with no top-ups still shows its initial allocation', function () {
    [$school] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $response = $this->actingAs($superAdmin)
        ->get(route('super-admin.schools.show', $school))
        ->assertOk()
        ->assertSee('No additional capacity has been requested');

    expect($response->viewData('capacity'))
        ->toMatchArray(['initial' => 50, 'allocated' => 50, 'used' => 0, 'remaining' => 50]);
});

test('the capacity panel is hidden for plans that are not sold per student', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Standard);

    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    // Standard and Exclusive are flat-fee and uncapped, so a capacity ceiling
    // would be a number that means nothing.
    $response = $this->actingAs($superAdmin)
        ->get(route('super-admin.schools.show', $school))
        ->assertOk()
        ->assertDontSee('Student Capacity');

    expect($response->viewData('capacity'))->toBeNull();
});

// -----------------------------------------------------------------------------
// The school sees its own capacity, on its own dashboard
// -----------------------------------------------------------------------------

test('the basic dashboard shows the school its total, registered and available capacity', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    fillSchoolToCapacity($school, 50);
    topUpAndApprove($school, $subscription, $superAdmin, 20);

    $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    // The three figures the brief names, and the button beside them.
    $response->assertSee('Student/Pupil Capacity')
        ->assertSee('Total Approved')
        ->assertSee('Registered')
        ->assertSee('Available')
        ->assertSee('Add More Students/Pupils');

    expect($response->viewData('capacity'))
        ->toMatchArray(['initial' => 50, 'allocated' => 70, 'used' => 50, 'remaining' => 20, 'pending' => 0]);
});

test('the dashboard says so plainly when capacity is exhausted', function () {
    [$school, $admin] = basicSchoolWithLicences(70);

    fillSchoolToCapacity($school, 70);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Student/Pupil Capacity Reached')
        ->assertSee('You have used all 70 approved student/pupil spaces.')
        ->assertSee('Add More Students/Pupils');
});

test('a pending request is shown on the dashboard but never counted as capacity', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(50);

    fillSchoolToCapacity($school, 50);

    SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 20,
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    // Told it is under review, and told in the same breath that the capacity
    // has not moved - a school that assumes otherwise finds out at the point
    // of registering a student.
    $response->assertSee('awaiting AkademicNest Team approval', false)
        ->assertSee('Student/Pupil Capacity Reached');

    expect($response->viewData('capacity'))
        ->toMatchArray(['allocated' => 50, 'used' => 50, 'remaining' => 0, 'pending' => 20]);
});

test('the capacity card is not drawn for a plan that is not sold per student', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Standard);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    // Standard is flat-fee and uncapped, so a ceiling would be a number that
    // means nothing.
    $response = $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Student/Pupil Capacity');

    expect($response->viewData('capacity'))->toBeNull();
});

test('a school awaiting approval is shown no capacity at all', function () {
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(
        ['key' => PlanKey::Basic],
        Plan::factory()->make(['key' => PlanKey::Basic, 'price_per_student_per_term' => 500])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::PendingVerification,
        'students_count' => 50,
    ]);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    // Paying is a request. Until a Super Admin has approved it there is no
    // capacity to report, and the card must not imply there is.
    $response = $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Awaiting activation')
        ->assertDontSee('Student/Pupil Capacity');

    expect($response->viewData('capacity'))->toBeNull();
});

// -----------------------------------------------------------------------------
// The school can request more, and can see what it has requested before
// -----------------------------------------------------------------------------

test('the request page prices additional spaces from the configured plan price', function () {
    [$school, $admin] = basicSchoolWithLicences(50);

    Plan::where('key', PlanKey::Basic)->update(['price_per_student_per_term' => 750]);

    // Never a number written into the page: change the plan, and the quote
    // changes with it.
    $this->actingAs($admin)
        ->get(route('subscription-top-up.create'))
        ->assertOk()
        ->assertSee('Additional Spaces Requested')
        ->assertSee('Current Capacity')
        ->assertSee(number_format(750, 2));
});

test('the request page lists every past request with its status', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    topUpAndApprove($school, $subscription, $superAdmin, 20);

    $rejected = SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 30,
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    $this->actingAs($superAdmin)->post(route('super-admin.subscriptions.top-ups.reject', $rejected), [
        'reason' => 'Receipt did not match the amount.',
    ]);

    SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 15,
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('subscription-top-up.create'))
        ->assertOk();

    // History, including the initial allocation, with each outcome named -
    // and a capacity that still counts only the approved one.
    $response->assertSee('Capacity Request History')
        ->assertSee('Initial')
        ->assertSee('Approved')
        ->assertSee('Rejected')
        ->assertSee('Pending Verification');

    expect($response->viewData('history'))->toHaveCount(3)
        ->and($response->viewData('capacity'))
        ->toMatchArray(['initial' => 50, 'allocated' => 70, 'pending' => 15]);
});

test('the initial payment on the history is what was charged, not today\'s price', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    topUpAndApprove($school, $subscription, $superAdmin, 20);

    // Raising the price must not retroactively rewrite what the school paid
    // at signup.
    Plan::where('key', PlanKey::Basic)->update(['price_per_student_per_term' => 900]);

    $response = $this->actingAs($admin)->get(route('subscription-top-up.create'))->assertOk();

    expect($response->viewData('initialAmount'))->toEqual(25000.0);
});

test('a school on a plan not sold per student cannot open the request page', function () {
    // Standard used to be the example here. It is sold per student now, so
    // Exclusive is the only plan left that has nothing to top up.
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Exclusive);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)
        ->get(route('subscription-top-up.create'))
        ->assertForbidden();
});

// -----------------------------------------------------------------------------
// One card, one vocabulary, wherever capacity is shown
// -----------------------------------------------------------------------------

test('the students page shows the same capacity card as the dashboard', function () {
    [$school, $admin, $subscription] = basicSchoolWithLicences(50);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    fillSchoolToCapacity($school, 40);
    topUpAndApprove($school, $subscription, $superAdmin, 20);

    $response = $this->actingAs($admin)->get(route('students.index'))->assertOk();

    // The same component, so the page a school manages students from cannot
    // describe its capacity differently from the page it lands on.
    $response->assertSee('Student/Pupil Capacity')
        ->assertSee('Total Approved')
        ->assertSee('Registered')
        ->assertSee('Available')
        ->assertSee('Add More Students/Pupils');

    expect($response->viewData('capacity'))
        ->toMatchArray(['initial' => 50, 'allocated' => 70, 'used' => 40, 'remaining' => 30]);
});

test('the card warns before the wall, not only at it', function () {
    [$school, $admin] = basicSchoolWithLicences(50);

    fillSchoolToCapacity($school, 47);

    // Additional spaces need a payment and an approval, which takes longer
    // than the moment a school discovers it cannot admit the next student.
    $response = $this->actingAs($admin)->get(route('students.index'))->assertOk();

    $response->assertSee('Running low on student/pupil spaces')
        ->assertDontSee('Student/Pupil Capacity Reached');

    expect($response->viewData('capacity'))
        ->toMatchArray(['remaining' => 3, 'runningLow' => true]);
});

test('the blocked message and the card say the same thing', function () {
    [$school, $admin] = basicSchoolWithLicences(3);

    fillSchoolToCapacity($school, 3);

    $this->actingAs($admin)->post(route('students.store'), newStudentPayload());

    // A school meets the limit in two places - the card and the refusal - and
    // they used to be worded as though they were different rules.
    expect(session('errors')->first('admission_number'))
        ->toContain('Student/Pupil Capacity Reached');

    $this->actingAs($admin)
        ->get(route('students.index'))
        ->assertOk()
        ->assertSee('Student/Pupil Capacity Reached');
});
