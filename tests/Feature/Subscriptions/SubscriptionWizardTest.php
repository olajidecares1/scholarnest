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
use Illuminate\Http\UploadedFile;
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

test('guests cannot access the subscription wizard', function () {
    $this->get(route('subscriptions.choose-plan'))->assertRedirect(route('login'));
});

test('choose plan screen lists all seeded plans', function () {
    $response = $this->actingAs(schoolAdmin())->get(route('subscriptions.choose-plan'));

    $response->assertStatus(200);
    $response->assertSee('Basic Plan');
    $response->assertSee('Standard Plan');
    $response->assertSee('Exclusive Plan');
});

test('choosing the basic plan requires a student count', function () {
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();

    $response = $this->actingAs(schoolAdmin())->post(route('subscriptions.choose-plan.store'), [
        'plan_id' => $plan->id,
    ]);

    $response->assertSessionHasErrors('students_count');
});

test('choosing the basic plan stores wizard state and moves to billing details', function () {
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();

    $response = $this->actingAs(schoolAdmin())->post(route('subscriptions.choose-plan.store'), [
        'plan_id' => $plan->id,
        'students_count' => 100,
    ]);

    $response->assertRedirect(route('subscriptions.billing-details'));
    expect(session('subscription_wizard.amount'))->toEqual(50000.0);
});

test('choosing the exclusive plan redirects to contact sales', function () {
    $plan = Plan::where('key', PlanKey::Exclusive)->firstOrFail();

    $response = $this->actingAs(schoolAdmin())->post(route('subscriptions.choose-plan.store'), [
        'plan_id' => $plan->id,
    ]);

    $response->assertRedirect(route('subscriptions.contact-sales'));
});

test('billing details cannot be accessed before a plan is chosen', function () {
    $this->actingAs(schoolAdmin())
        ->get(route('subscriptions.billing-details'))
        ->assertRedirect(route('subscriptions.choose-plan'));
});

test('billing details step saves details to the school and advances the wizard', function () {
    $user = schoolAdmin();
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();

    $this->actingAs($user)->post(route('subscriptions.choose-plan.store'), [
        'plan_id' => $plan->id,
        'students_count' => 100,
    ]);

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
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();

    $this->actingAs($user)->post(route('subscriptions.choose-plan.store'), [
        'plan_id' => $plan->id,
        'students_count' => 100,
    ]);

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
