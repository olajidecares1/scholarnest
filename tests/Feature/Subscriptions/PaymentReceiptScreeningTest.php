<?php

use App\Enums\PaymentStatus;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTopUpStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionTopUp;
use App\Models\User;
use App\Services\PaymentReceiptScreening;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// The wizard reads real plan records to price the subscription, and the
// end-to-end tests below walk it. Harmless for the rest.
beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

/**
 * Fake one screening response and run a receipt through it.
 *
 * @param  array<string, mixed>  $assessment
 * @return array{passed: bool, reason: ?string, notes: ?string}
 */
function screenReceipt(array $assessment, float $required = 50000.0): array
{
    config(['services.anthropic.key' => 'test-key']);

    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [[
            'type' => 'tool_use',
            'name' => 'record_receipt_assessment',
            'input' => $assessment,
        ]],
    ], 200)]);

    return app(PaymentReceiptScreening::class)->screen(
        UploadedFile::fake()->image('receipt.jpg'),
        $required,
        'Greenfield College',
    );
}

// -----------------------------------------------------------------------------
// Turning away what is not a receipt.
// -----------------------------------------------------------------------------

test('a file that is not a payment document is refused', function () {
    $result = screenReceipt([
        'is_payment_receipt' => false,
        'what_it_looks_like' => 'a photograph of a building',
    ]);

    expect($result['passed'])->toBeFalse()
        ->and($result['reason'])->toContain('Payment Verification Failed')
        ->and($result['reason'])->toContain('a photograph of a building');
});

test('the refusal says what a good upload looks like', function () {
    $result = screenReceipt(['is_payment_receipt' => false]);

    expect($result['reason'])->toContain('bank transfer receipt')
        ->and($result['reason'])->toContain('transaction reference');
});

// -----------------------------------------------------------------------------
// Turning away a payment that is short.
// -----------------------------------------------------------------------------

test('a receipt for clearly less than the plan costs is refused', function () {
    $result = screenReceipt([
        'is_payment_receipt' => true,
        'amount' => 5000,
    ], required: 50000.0);

    expect($result['passed'])->toBeFalse()
        ->and($result['reason'])->toContain('Insufficient Payment')
        ->and($result['reason'])->toContain('₦5,000.00')
        ->and($result['reason'])->toContain('₦50,000.00');
});

test('a receipt for the exact amount passes', function () {
    $result = screenReceipt([
        'is_payment_receipt' => true,
        'amount' => 50000,
    ], required: 50000.0);

    expect($result['passed'])->toBeTrue();
});

test('a receipt a shade under the amount still reaches a person', function () {
    // A misread digit on a photographed receipt must not turn away a school
    // that actually paid.
    $result = screenReceipt([
        'is_payment_receipt' => true,
        'amount' => 49500,
    ], required: 50000.0);

    expect($result['passed'])->toBeTrue();
});

test('an unreadable amount is not treated as a shortfall', function () {
    // Plenty of genuine receipts photograph badly, and a person can read what
    // the screening could not.
    $result = screenReceipt([
        'is_payment_receipt' => true,
        'amount' => null,
    ], required: 50000.0);

    expect($result['passed'])->toBeTrue();
});

// -----------------------------------------------------------------------------
// It never approves.
// -----------------------------------------------------------------------------

test('a receipt that passes every check is still only sent for review', function () {
    // The whole point. A carefully edited receipt passes anything that can be
    // written here, so passing must never read as "verified".
    $result = screenReceipt([
        'is_payment_receipt' => true,
        'amount' => 50000,
        'reference' => 'TRF/2026/00912',
        'payer' => 'Greenfield College',
        'bank' => 'GTBank',
    ]);

    expect($result['passed'])->toBeTrue()
        ->and($result['notes'])->toContain('Not verified')
        ->and($result['notes'])->toContain('confirm against the bank record')
        ->and($result['notes'])->toContain('TRF/2026/00912');
});

test('concerns are passed to the reviewer rather than used to refuse', function () {
    $result = screenReceipt([
        'is_payment_receipt' => true,
        'amount' => 50000,
        'concerns' => ['The date appears to be from two years ago.'],
    ]);

    expect($result['passed'])->toBeTrue()
        ->and($result['notes'])->toContain('two years ago');
});

// -----------------------------------------------------------------------------
// Our outage is not the school's problem.
// -----------------------------------------------------------------------------

test('a school is not blocked when the screening service is down', function () {
    // A school that has paid must not be turned away because our own billing
    // lapsed. The upload goes through, flagged for manual attention.
    config(['services.anthropic.key' => 'test-key']);

    Http::fake(['api.anthropic.com/*' => Http::response([
        'error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low.'],
    ], 400)]);

    $result = app(PaymentReceiptScreening::class)->screen(
        UploadedFile::fake()->image('receipt.jpg'),
        50000.0,
        'Greenfield College',
    );

    expect($result['passed'])->toBeTrue()
        ->and($result['notes'])->toContain('Automatic screening did not run')
        ->and($result['notes'])->toContain('Review this receipt manually');
});

test('an unconfigured screening service does not block uploads either', function () {
    config(['services.anthropic.key' => null]);

    $result = app(PaymentReceiptScreening::class)->screen(
        UploadedFile::fake()->image('receipt.jpg'),
        50000.0,
        'Greenfield College',
    );

    expect($result['passed'])->toBeTrue()
        ->and($result['notes'])->toContain('did not run');
});

// -----------------------------------------------------------------------------
// Both doors, end to end.
// -----------------------------------------------------------------------------

/**
 * Make every screening call answer with this assessment.
 *
 * @param  array<string, mixed>  $assessment
 */
function fakeScreening(array $assessment): void
{
    config(['services.anthropic.key' => 'test-key']);

    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [[
            'type' => 'tool_use',
            'name' => 'record_receipt_assessment',
            'input' => $assessment,
        ]],
    ], 200)]);
}

test('registration refuses a file that is not a receipt, and stores nothing', function () {
    Storage::fake('local');

    $user = schoolAdmin();
    chooseBasicPlanFor($user, 100);

    $this->actingAs($user)->post(route('subscriptions.billing-details.store'), [
        'billing_contact_name' => 'Jane Doe',
        'billing_email' => 'billing@greenfield.example',
        'billing_phone' => '08012345678',
        'billing_address' => '1 School Road',
    ]);

    fakeScreening([
        'is_payment_receipt' => false,
        'what_it_looks_like' => 'a selfie',
    ]);

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->image('not-a-receipt.jpg'),
    ])->assertSessionHasErrors('receipt');

    // Refused at the door: nothing written to disk, and the wizard has not
    // advanced.
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('registration lets a plausible receipt through to review', function () {
    Storage::fake('local');

    $user = schoolAdmin();
    chooseBasicPlanFor($user, 100);

    $this->actingAs($user)->post(route('subscriptions.billing-details.store'), [
        'billing_contact_name' => 'Jane Doe',
        'billing_email' => 'billing@greenfield.example',
        'billing_phone' => '08012345678',
        'billing_address' => '1 School Road',
    ]);

    fakeScreening([
        'is_payment_receipt' => true,
        'amount' => 50000,
        'reference' => 'TRF/2026/00912',
    ]);

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertRedirect(route('subscriptions.review'));
});

test('screening never activates a subscription on its own', function () {
    // The rule the whole design turns on: passing screening moves a school to
    // "pending verification", never to active.
    Storage::fake('local');

    $user = schoolAdmin();
    chooseBasicPlanFor($user, 100);

    $this->actingAs($user)->post(route('subscriptions.billing-details.store'), [
        'billing_contact_name' => 'Jane Doe',
        'billing_email' => 'billing@greenfield.example',
        'billing_phone' => '08012345678',
        'billing_address' => '1 School Road',
    ]);

    fakeScreening(['is_payment_receipt' => true, 'amount' => 50000, 'reference' => 'TRF/1']);

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ]);

    $this->actingAs($user)->post(route('subscriptions.review.store'));

    $subscription = Subscription::where('school_id', $user->school_id)->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::PendingVerification)
        ->and($subscription->status)->not->toBe(SubscriptionStatus::Active);

    // And what screening read is on the payment as notes for the reviewer.
    $payment = Payment::where('subscription_id', $subscription->id)->firstOrFail();

    expect($payment->notes)->toContain('Not verified')
        ->and($payment->status)->toBe(PaymentStatus::Pending);
});

// -----------------------------------------------------------------------------
// The top-up door is not the way round.
// -----------------------------------------------------------------------------

/**
 * A Basic school with an active subscription and an admin.
 *
 * @return array{0: User, 1: School}
 */
function topUpSchoolAdmin(): array
{
    $school = School::factory()->create(['name' => 'Greenfield College']);

    $plan = Plan::firstOrCreate(
        ['key' => PlanKey::Basic],
        Plan::factory()->make(['key' => PlanKey::Basic, 'price_per_student_per_term' => 500])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'students_count' => 100,
        'amount' => 50000,
    ]);

    return [
        User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]),
        $school,
    ];
}

test('a top-up refuses a file that is not a receipt', function () {
    Storage::fake('local');
    [$admin] = topUpSchoolAdmin();

    fakeScreening(['is_payment_receipt' => false, 'what_it_looks_like' => 'a blank page']);

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 20,
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->image('blank.jpg'),
    ])->assertSessionHasErrors('receipt');

    expect(SubscriptionTopUp::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('a top-up refuses a receipt for less than the slots cost', function () {
    // 20 slots at 500 each is 10,000. A receipt for 900 is not that, and this
    // route must not be the cheap way past the amount check.
    Storage::fake('local');
    [$admin] = topUpSchoolAdmin();

    fakeScreening(['is_payment_receipt' => true, 'amount' => 900]);

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 20,
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->image('short.jpg'),
    ])->assertSessionHasErrors('receipt');

    expect(SubscriptionTopUp::count())->toBe(0);
});

test('a top-up with a matching receipt is recorded, still pending verification', function () {
    Storage::fake('local');
    [$admin] = topUpSchoolAdmin();

    fakeScreening(['is_payment_receipt' => true, 'amount' => 10000, 'reference' => 'TRF/77']);

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 20,
        'payment_method' => 'bank_transfer',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertRedirect();

    $topUp = SubscriptionTopUp::firstOrFail();

    // Recorded as a request. Passing screening grants no capacity at all.
    expect($topUp->status)->toBe(SubscriptionTopUpStatus::PendingVerification)
        ->and((float) $topUp->additional_amount)->toBe(10000.0);
});
