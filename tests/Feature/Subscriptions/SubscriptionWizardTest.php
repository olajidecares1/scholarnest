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
use App\Notifications\NewSubscriptionSubmittedNotification;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Storage::fake('local');
});

function schoolAdmin(): User
{
    $school = School::factory()->create(['name' => 'Greenfield Academy']);

    return User::factory()->create([
        'school_id' => $school->id,
        'role' => UserRole::SchoolAdmin,
    ]);
}

/**
 * Pick Basic and answer the quantity question, which are two steps of their own.
 */
function chooseBasicPlanFor(User $user, int $students): Plan
{
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();

    test()->actingAs($user)->post(route('subscriptions.choose-plan.store'), [
        'plan_id' => $plan->id,
    ]);

    test()->actingAs($user)->post(route('subscriptions.students.store'), [
        'students_count' => $students,
    ]);

    return $plan;
}

test('guests cannot access the subscription wizard', function () {
    $this->get(route('subscriptions.choose-plan'))->assertRedirect(route('portal.show'));
});

test('choose plan screen lists all seeded plans', function () {
    $response = $this->actingAs(schoolAdmin())->get(route('subscriptions.choose-plan'));

    $response->assertStatus(200);
    $response->assertSee('Basic Plan');
    $response->assertSee('Standard Plan');
    $response->assertSee('Exclusive Plan');
});

test('choosing the basic plan sends the school to the student quantity step', function () {
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();

    // The quantity is a step of its own now, so the plan cards no longer ask
    // for it and choosing a plan cannot fail for want of it.
    $response = $this->actingAs(schoolAdmin())->post(route('subscriptions.choose-plan.store'), [
        'plan_id' => $plan->id,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('subscriptions.students'));
    expect(session('subscription_wizard.plan_id'))->toBe($plan->id);
});

test('the student quantity step requires a count of at least one', function () {
    $user = schoolAdmin();
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();

    $this->actingAs($user)->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id]);

    $this->actingAs($user)
        ->post(route('subscriptions.students.store'), [])
        ->assertSessionHasErrors('students_count');

    $this->actingAs($user)
        ->post(route('subscriptions.students.store'), ['students_count' => 0])
        ->assertSessionHasErrors('students_count');
});

test('the student quantity step prices the subscription from the plan and moves on', function () {
    $user = schoolAdmin();
    $plan = chooseBasicPlanFor($user, 100);

    expect(session('subscription_wizard.students_count'))->toBe(100);
    expect(session('subscription_wizard.amount'))->toEqual(100 * (float) $plan->price_per_student_per_term);
    expect(session('subscription_wizard.amount'))->toEqual(50000.0);
});

test('the amount ignores anything the browser tries to price for itself', function () {
    $user = schoolAdmin();
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();

    $this->actingAs($user)->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id]);

    // The total on screen is a quote. The charge is worked out here, from the
    // plan record, whatever the form was made to send.
    $this->actingAs($user)->post(route('subscriptions.students.store'), [
        'students_count' => 10,
        'amount' => 1,
        'price_per_student_per_term' => 1,
    ]);

    expect(session('subscription_wizard.amount'))->toEqual(10 * (float) $plan->price_per_student_per_term);
});

test('the student quantity step shows the configured price rather than a fixed one', function () {
    $user = schoolAdmin();
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();
    $plan->update(['price_per_student_per_term' => 750]);

    $this->actingAs($user)->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id]);

    $this->actingAs($user)
        ->get(route('subscriptions.students'))
        ->assertOk()
        ->assertSee('Student Capacity')
        ->assertSee(number_format(750, 2));
});

test('the quantity step cannot be skipped on a per student plan', function () {
    $user = schoolAdmin();
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();

    $this->actingAs($user)->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id]);

    $this->actingAs($user)
        ->get(route('subscriptions.billing-details'))
        ->assertRedirect(route('subscriptions.students'));

    $this->actingAs($user)->post(route('subscriptions.billing-details.store'), [
        'billing_contact_name' => 'Jane Doe',
        'billing_email' => 'billing@greenfield.example',
        'billing_phone' => '08012345678',
        'billing_address' => '1 School Road',
    ])->assertRedirect(route('subscriptions.students'));
});

test('standard now HAS a quantity step, like basic', function () {
    // It used to skip straight to billing on a flat term fee. It is priced per
    // pupil now, so it asks the same question Basic does.
    $user = schoolAdmin();
    $plan = Plan::where('key', PlanKey::Standard)->firstOrFail();

    $this->actingAs($user)->post(route('subscriptions.choose-plan.store'), [
        'plan_id' => $plan->id,
    ])->assertRedirect(route('subscriptions.students'));

    $this->actingAs($user)
        ->get(route('subscriptions.students'))
        ->assertOk();
});

test('choosing the exclusive plan is refused - it is coming soon', function () {
    // It used to go to contact sales. Exclusive cannot be subscribed to at
    // all for now, and the refusal is here rather than only on the page.
    $plan = Plan::where('key', PlanKey::Exclusive)->firstOrFail();

    $this->actingAs(schoolAdmin())
        ->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id])
        ->assertSessionHasErrors('plan_id');
});

test('billing details cannot be accessed before a plan is chosen', function () {
    $this->actingAs(schoolAdmin())
        ->get(route('subscriptions.billing-details'))
        ->assertRedirect(route('subscriptions.choose-plan'));
});

test('billing details step saves details to the school and advances the wizard', function () {
    $user = schoolAdmin();
    chooseBasicPlanFor($user, 100);

    $response = $this->actingAs($user)->post(route('subscriptions.billing-details.store'), [
        'billing_contact_name' => 'Jane Doe',
        'billing_email' => 'billing@greenfield.example',
        'billing_phone' => '08012345678',
        'billing_address' => '1 School Road',
    ]);

    $response->assertRedirect(route('subscriptions.payment-method'));

    expect($user->school->fresh()->billing_contact_name)->toBe('Jane Doe');
});

test('the full wizard creates a subscription and payment on confirmation', function () {
    $user = schoolAdmin();
    chooseBasicPlanFor($user, 100);

    $this->actingAs($user)->post(route('subscriptions.billing-details.store'), [
        'billing_contact_name' => 'Jane Doe',
        'billing_email' => 'billing@greenfield.example',
        'billing_phone' => '08012345678',
        'billing_address' => '1 School Road',
    ]);

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertRedirect(route('subscriptions.review'));

    $response = $this->actingAs($user)->get(route('subscriptions.review'));
    $response->assertStatus(200)->assertSee('Jane Doe');

    $response = $this->actingAs($user)->post(route('subscriptions.review.store'));

    $subscription = Subscription::first();
    expect($subscription)->not->toBeNull();
    expect($subscription->school_id)->toBe($user->school_id);
    expect($subscription->status)->toBe(SubscriptionStatus::PendingVerification);
    expect((float) $subscription->amount)->toEqual(50000.0);

    $payment = Payment::first();
    expect($payment->subscription_id)->toBe($subscription->id);
    expect($payment->status)->toBe(PaymentStatus::Pending);
    Storage::disk('local')->assertExists($payment->receipt_path);

    $response->assertRedirect(route('subscriptions.confirmation', $subscription));

    expect(session('subscription_wizard'))->toBeNull();
});

test('a school cannot view another schools subscription confirmation', function () {
    $owner = schoolAdmin();
    $intruder = schoolAdmin();

    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();
    $subscription = Subscription::factory()->create([
        'school_id' => $owner->school_id,
        'plan_id' => $plan->id,
        'billing_cycle' => 'per_student_per_term',
        'amount' => 50000,
        'reference' => 'TEST-REF-1',
    ]);

    $this->actingAs($intruder)
        ->get(route('subscriptions.confirmation', $subscription))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('subscriptions.confirmation', $subscription))
        ->assertStatus(200);
});

test('submitting a subscription notifies all super admins', function () {
    Notification::fake();

    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    $user = schoolAdmin();
    chooseBasicPlanFor($user, 100);

    $this->actingAs($user)->post(route('subscriptions.billing-details.store'), [
        'billing_contact_name' => 'Jane Doe',
        'billing_email' => 'billing@greenfield.example',
        'billing_phone' => '08012345678',
        'billing_address' => '1 School Road',
    ])->assertRedirect(route('subscriptions.payment-method'));

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertRedirect(route('subscriptions.review'));

    $this->actingAs($user)->post(route('subscriptions.review.store'))
        ->assertRedirect();

    expect(Subscription::count())->toBe(1);

    Notification::assertSentTo($superAdmin, NewSubscriptionSubmittedNotification::class);
});

test('a super admin can open a school subscription without a school of their own', function () {
    $owner = schoolAdmin();
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();
    $subscription = Subscription::factory()->create([
        'school_id' => $owner->school_id,
        'plan_id' => $plan->id,
        'billing_cycle' => 'per_student_per_term',
        'amount' => 50000,
        'reference' => 'TEST-REF-SA',
    ]);

    // The policy lets a Super Admin open any subscription, but this page is the
    // last step of the SCHOOL's signup wizard and is no place to review one -
    // it addresses the reader as the school that just paid. They are sent to
    // the review screen in their own panel instead, which is where the decision
    // is actually made. See SuperAdmin\SubscriptionReviewTest.
    $this->actingAs($superAdmin)
        ->get(route('subscriptions.confirmation', $subscription))
        ->assertRedirect(route('super-admin.subscriptions.show', $subscription));
});

test('a school admin still sees the school chrome on the confirmation page', function () {
    $owner = schoolAdmin();

    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();
    $subscription = Subscription::factory()->create([
        'school_id' => $owner->school_id,
        'plan_id' => $plan->id,
        'billing_cycle' => 'per_student_per_term',
        'amount' => 50000,
        'reference' => 'TEST-REF-SCHOOL',
    ]);

    // The layout is chosen per audience, so the school must keep getting its
    // own - the school name in the sidebar is the giveaway.
    $this->actingAs($owner)
        ->get(route('subscriptions.confirmation', $subscription))
        ->assertOk()
        ->assertSee('Greenfield Academy')
        ->assertSee('Payment Confirmed');
});

// -----------------------------------------------------------------------------
// The school knows what it is paying for, and what happens next, before it pays
// -----------------------------------------------------------------------------

/**
 * Walk a Basic school to the review step with the given student quantity.
 */
function reviewStepFor(int $students): array
{
    $user = schoolAdmin();
    $plan = chooseBasicPlanFor($user, $students);

    test()->actingAs($user)->post(route('subscriptions.billing-details.store'), [
        'billing_contact_name' => 'Jane Doe',
        'billing_email' => 'billing@greenfield.example',
        'billing_phone' => '08012345678',
        'billing_address' => '1 School Road',
    ]);

    test()->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ]);

    return [$user, $plan];
}

test('the review step shows the unit price, the quantity and the total', function () {
    [$user, $plan] = reviewStepFor(50);

    // Every figure the brief asks for, before any payment is submitted - and
    // the unit price so the school can check the arithmetic rather than being
    // asked to trust one number.
    $this->actingAs($user)
        ->get(route('subscriptions.review'))
        ->assertOk()
        ->assertSee('Price per Student')
        ->assertSee(number_format($plan->price_per_student_per_term, 2))
        ->assertSee('Students Requested')
        ->assertSee('50')
        ->assertSee('Total Amount')
        ->assertSee(number_format(50 * $plan->price_per_student_per_term, 2));
});

test('the review step says activation waits on the super admin', function () {
    [$user] = reviewStepFor(50);

    // Said before paying, not after. A school that learns this only from the
    // confirmation screen has already been surprised.
    $this->actingAs($user)
        ->get(route('subscriptions.review'))
        ->assertOk()
        ->assertSee('Awaiting ScholarNest Team Approval');
});

test('the price on the review step follows the configured plan price', function () {
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();
    $plan->update(['price_per_student_per_term' => 750]);

    [$user] = reviewStepFor(20);

    // Never a number written into the page: change the plan, and the summary
    // changes with it.
    $this->actingAs($user)
        ->get(route('subscriptions.review'))
        ->assertOk()
        ->assertSee(number_format(750, 2))
        ->assertSee(number_format(20 * 750, 2));
});

test('the progress bar carries the quantity as a step of its own, and names the wait after payment', function () {
    [$user] = reviewStepFor(50);

    $this->actingAs($user)
        ->get(route('subscriptions.review'))
        ->assertOk()
        // The quantity has a step of its own, before any payment detail is
        // entered, and the bar says so on every screen after it.
        ->assertSee('Students &amp; Amount', false)
        // And the last step is named for what actually happens there, rather
        // than implying the school is finished once it has paid.
        ->assertSee('Awaiting Approval')
        ->assertSee('In Progress');
});

test('the progress bar now shows the quantity step for standard too', function () {
    // The bar is drawn from the billing cycle, so Standard moving to
    // per-student billing put the step into its process as well as into its
    // price. A bar that hid it would misdescribe what the school just did.
    $user = schoolAdmin();
    $plan = Plan::where('key', PlanKey::Standard)->firstOrFail();

    $this->actingAs($user)->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id]);
    $this->actingAs($user)->post(route('subscriptions.students.store'), ['students_count' => 40]);

    $this->actingAs($user)
        ->get(route('subscriptions.billing-details'))
        ->assertOk()
        ->assertSee('Billing Details')
        ->assertSee('Students &amp; Amount', false);
});

test('the confirmation page keeps the quantity step the school walked through', function () {
    [$user] = reviewStepFor(50);

    $this->actingAs($user)->post(route('subscriptions.review.store'));

    $subscription = Subscription::firstOrFail();

    // The wizard session is cleared once the subscription exists, so the bar
    // is told from the subscription instead - otherwise the last screen would
    // quietly drop a step the school had just completed.
    $this->actingAs($user)
        ->get(route('subscriptions.confirmation', $subscription))
        ->assertOk()
        ->assertSee('Students &amp; Amount', false);
});

// -----------------------------------------------------------------------------
// The progress bar on a phone
// -----------------------------------------------------------------------------

test('the progress bar slides rather than cramming six steps onto a phone', function () {
    [$user] = reviewStepFor(50);

    $response = $this->actingAs($user)->get(route('subscriptions.review'))->assertOk();

    // Below "sm" each step keeps a real width and the row overflows; from "sm"
    // up they share the width evenly. Shrinking six labels until they collide
    // is the thing being avoided here.
    $response->assertSee('subscription-steps-rail')
        ->assertSee('overflow-x-auto', false)
        ->assertSee('sm:overflow-x-visible', false)
        ->assertSee('w-[108px] shrink-0', false)
        ->assertSee('sm:w-auto sm:min-w-0 sm:flex-1', false);
});

test('the progress bar says where you are in words, for the part that scrolls off', function () {
    [$user] = reviewStepFor(50);

    // On a narrow screen part of the rail is always out of view, so the one
    // fact that matters most is stated where it cannot scroll away.
    $this->actingAs($user)
        ->get(route('subscriptions.review'))
        ->assertOk()
        ->assertSee('Step 5 of 6');
});

test('the step counter counts the steps that plan actually has', function () {
    $user = schoolAdmin();
    $plan = Plan::where('key', PlanKey::Standard)->firstOrFail();

    $this->actingAs($user)->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id]);
    $this->actingAs($user)->post(route('subscriptions.students.store'), ['students_count' => 40]);

    // SIX now, not five: Standard gained the student-quantity step when it
    // moved to per-pupil pricing. The counter is derived from the same list
    // the bar draws, so it cannot promise a step that is not there - or miss
    // one that is.
    $this->actingAs($user)
        ->get(route('subscriptions.billing-details'))
        ->assertOk()
        ->assertSee('Step 3 of 6');
});
