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
use Tests\Support\UploadFixtures;

// The wizard reads real plan records to price the subscription, and the
// end-to-end tests below walk it. Harmless for the rest.
beforeEach(function () {
    $this->seed(PlanSeeder::class);

    // Screening runs on our own servers. Any outside request is a failure.
    Http::preventStrayRequests();
});

/**
 * @return array{passed: bool, reason: ?string, notes: ?string}
 */
function screenReceipt(UploadedFile $receipt, float $required = 50000.0): array
{
    return app(PaymentReceiptScreening::class)->screen($receipt, $required, 'Greenfield College');
}

// -----------------------------------------------------------------------------
// Turning away what is not a receipt.
// -----------------------------------------------------------------------------

test('a blank image is refused', function () {
    $result = screenReceipt(UploadFixtures::blankPng());

    expect($result['passed'])->toBeFalse()
        ->and($result['reason'])->toContain('Payment Verification Failed')
        ->and($result['reason'])->toContain('appears to be blank');
});

test('an image too small to read is refused', function () {
    $result = screenReceipt(UploadedFile::fake()->image('tiny.jpg', 60, 60));

    expect($result['passed'])->toBeFalse()
        ->and($result['reason'])->toContain('too small to read');
});

test('a PDF that is plainly not a payment is refused', function () {
    $result = screenReceipt(UploadFixtures::textPdf([
        'Mathematics Examination',
        'Answer all questions in section A.',
        'Question 1: Solve for x in 2x + 4 = 10.',
    ]));

    expect($result['passed'])->toBeFalse()
        ->and($result['reason'])->toContain('does not look like a proof of payment');
});

test('the refusal says what a good upload looks like', function () {
    $result = screenReceipt(UploadFixtures::blankPng());

    expect($result['reason'])->toContain('bank transfer receipt')
        ->and($result['reason'])->toContain('transaction reference');
});

// -----------------------------------------------------------------------------
// Turning away a payment that is short.
// -----------------------------------------------------------------------------

test('a receipt for clearly less than the plan costs is refused', function () {
    $result = screenReceipt(UploadFixtures::textPdf([
        'Transfer Successful',
        'Amount: NGN 5,000.00',
        'Reference: TRF/2026/00912',
    ]), required: 50000.0);

    expect($result['passed'])->toBeFalse()
        ->and($result['reason'])->toContain('Insufficient Payment')
        ->and($result['reason'])->toContain('₦5,000.00')
        ->and($result['reason'])->toContain('₦50,000.00');
});

test('a receipt for the exact amount passes', function () {
    $result = screenReceipt(UploadFixtures::textPdf([
        'Transfer Successful',
        'Amount: ₦50,000.00',
    ]), required: 50000.0);

    expect($result['passed'])->toBeTrue();
});

test('a receipt a shade under the amount still reaches a person', function () {
    $result = screenReceipt(UploadFixtures::textPdf([
        'Transfer Successful',
        'Amount: NGN 49,500.00',
    ]), required: 50000.0);

    expect($result['passed'])->toBeTrue();
});

test('a statement that also shows a larger balance is not treated as a shortfall', function () {
    $result = screenReceipt(UploadFixtures::textPdf([
        'Account Statement',
        'Debit Transfer to AkademicNest N50,000.00',
        'Balance N1,250,000.00',
    ]), required: 50000.0);

    expect($result['passed'])->toBeTrue();
});

test('an image receipt has no readable amount, so it is never refused for one', function () {
    $result = screenReceipt(UploadFixtures::receiptPng(), required: 50000.0);

    expect($result['passed'])->toBeTrue()
        ->and($result['notes'])->toContain('Image receipt');
});

test('a scanned or protected PDF goes to a person rather than being refused', function () {
    $result = screenReceipt(UploadedFile::fake()->create('statement.pdf', 40, 'application/pdf'));

    expect($result['passed'])->toBeTrue()
        ->and($result['notes'])->toContain('no readable text');
});

// -----------------------------------------------------------------------------
// It never approves.
// -----------------------------------------------------------------------------

test('a receipt that passes every check is still only sent for review', function () {
    // The whole point. A carefully edited receipt passes anything that can be
    // written here, so passing must never read as "verified".
    $result = screenReceipt(UploadFixtures::textPdf([
        'GTBank Transfer Receipt',
        'Transaction Reference: TRF/2026/00912',
        'Amount: NGN 50,000.00',
        'Sender: Greenfield College',
        'Date: '.now()->format('d/m/Y'),
    ]));

    expect($result['passed'])->toBeTrue()
        ->and($result['notes'])->toContain('Not verified')
        ->and($result['notes'])->toContain('Confirm against the bank record')
        ->and($result['notes'])->toContain('TRF/2026/00912')
        ->and($result['notes'])->toContain('₦50,000.00')
        ->and($result['notes'])->toContain('GTBank')
        ->and($result['notes'])->toContain("Mentions the school's name");
});

test('an old date is passed to the reviewer rather than used to refuse', function () {
    $result = screenReceipt(UploadFixtures::textPdf([
        'Transfer Successful',
        'Amount: NGN 50,000.00',
        'Date: '.now()->subYears(2)->format('d/m/Y'),
    ]));

    expect($result['passed'])->toBeTrue()
        ->and($result['notes'])->toContain('more than six months old');
});

test('screening sends nothing to any outside service', function () {
    screenReceipt(UploadFixtures::receiptPng());
    screenReceipt(UploadFixtures::textPdf(['Transfer Successful', 'Amount: NGN 50,000.00']));

    Http::assertNothingSent();
});

// -----------------------------------------------------------------------------
// Both doors, end to end.
// -----------------------------------------------------------------------------

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

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => UploadFixtures::blankPng(),
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

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => UploadFixtures::receiptPng(),
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

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => UploadFixtures::receiptPng(),
    ]);

    $this->actingAs($user)->post(route('subscriptions.review.store'));

    $subscription = Subscription::where('school_id', $user->school_id)->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::PendingVerification)
        ->and($subscription->status)->not->toBe(SubscriptionStatus::Active);

    // And what screening found is on the payment as notes for the reviewer.
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

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 20,
        'payment_method' => 'bank_transfer',
        'receipt' => UploadFixtures::blankPng(),
    ])->assertSessionHasErrors('receipt');

    expect(SubscriptionTopUp::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('a top-up refuses a receipt for less than the slots cost', function () {
    // 20 slots at 500 each is 10,000. A receipt for 900 is not that, and this
    // route must not be the cheap way past the amount check.
    Storage::fake('local');
    [$admin] = topUpSchoolAdmin();

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 20,
        'payment_method' => 'bank_transfer',
        'receipt' => UploadFixtures::textPdf(['Transfer Successful', 'Amount: NGN 900.00']),
    ])->assertSessionHasErrors('receipt');

    expect(SubscriptionTopUp::count())->toBe(0);
});

test('a top-up with a matching receipt is recorded, still pending verification', function () {
    Storage::fake('local');
    [$admin] = topUpSchoolAdmin();

    $this->actingAs($admin)->post(route('subscription-top-up.store'), [
        'additional_students_count' => 20,
        'payment_method' => 'bank_transfer',
        'receipt' => UploadFixtures::textPdf(['Transfer Successful', 'Amount: NGN 10,000.00', 'Reference: TRF/77AB12']),
    ])->assertRedirect();

    $topUp = SubscriptionTopUp::firstOrFail();

    // Recorded as a request. Passing screening grants no capacity at all.
    expect($topUp->status)->toBe(SubscriptionTopUpStatus::PendingVerification)
        ->and((float) $topUp->additional_amount)->toBe(10000.0);
});
