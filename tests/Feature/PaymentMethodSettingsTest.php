<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\PaymentMethodSetting;
use App\Models\Plan;
use App\Models\School;
use App\Models\User;
use App\Services\AvailablePaymentMethods;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->team = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->bankTransfer = PaymentMethodSetting::where('key', 'bank_transfer')->sole();
    $this->paystack = PaymentMethodSetting::where('key', 'paystack')->sole();
});

/**
 * A school standing on the payment step of the subscription wizard.
 */
function schoolAtPaymentStep(): User
{
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());

    session([
        'subscription_wizard' => [
            'plan_id' => $plan->id,
            'billing_cycle' => 'termly',
            'amount' => 50000,
            'billing_contact_name' => 'Ada Obi',
            'billing_email' => 'ada@example.test',
            'billing_phone' => '08011112222',
            'billing_address' => '12 Awolowo Road',
        ],
    ]);

    return $admin;
}

// ---------------------------------------------------------------------------
// Nothing is hard-coded any more
// ---------------------------------------------------------------------------

test('the payment page shows the account details the EduNest Team entered', function () {
    $this->bankTransfer->update([
        'details' => [
            'bank_name' => 'Zenith Bank',
            'account_name' => 'EduNest Nigeria Ltd',
            'account_number' => '1234509876',
        ],
    ]);

    $this->actingAs(schoolAtPaymentStep())
        ->get(route('subscriptions.payment-method'))
        ->assertOk()
        ->assertSee('Zenith Bank')
        ->assertSee('EduNest Nigeria Ltd')
        ->assertSee('1234509876')

        // The values that used to be typed into the template.
        ->assertDontSee('GTBank')
        ->assertDontSee('0123456789');
});

test('changing the details changes what the next school sees', function () {
    $this->actingAs($this->team)
        ->put(route('super-admin.payment-settings.update', $this->bankTransfer), [
            'label' => 'Bank Transfer',
            'bank_name' => 'First Bank',
            'account_name' => 'EduNest Ltd',
            'account_number' => '3011223344',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs(schoolAtPaymentStep())
        ->get(route('subscriptions.payment-method'))
        ->assertOk()
        ->assertSee('First Bank')
        ->assertSee('3011223344');
});

test('an empty detail is left out rather than printed blank', function () {
    // "Sort Code —" on a payment page is worse than no row: somebody transfers
    // the money anyway and guesses.
    $this->bankTransfer->update([
        'details' => ['bank_name' => 'Zenith Bank', 'account_number' => '1234509876', 'sort_code' => null],
    ]);

    $this->actingAs(schoolAtPaymentStep())
        ->get(route('subscriptions.payment-method'))
        ->assertOk()
        ->assertDontSee('Sort Code');
});

// ---------------------------------------------------------------------------
// Enabling and disabling
// ---------------------------------------------------------------------------

test('a disabled method disappears from the school\'s choices', function () {
    $this->paystack->update(['is_enabled' => true]);

    $this->actingAs(schoolAtPaymentStep())
        ->get(route('subscriptions.payment-method'))
        ->assertOk()
        ->assertSee('Paystack (Card, USSD, Transfer)');

    $this->actingAs($this->team)
        ->post(route('super-admin.payment-settings.toggle', $this->paystack))
        ->assertSessionHasNoErrors();

    $this->actingAs(schoolAtPaymentStep())
        ->get(route('subscriptions.payment-method'))
        ->assertOk()
        ->assertDontSee('Paystack (Card, USSD, Transfer)');
});

test('a disabled method is refused by the server, not just hidden', function () {
    // The brief is explicit: hiding the radio while leaving the endpoint open
    // is not the same thing. This posts the disabled key directly.
    Storage::fake('local');

    expect($this->paystack->is_enabled)->toBeFalse();

    $this->actingAs(schoolAtPaymentStep())
        ->post(route('subscriptions.payment-method.store'), [
            'payment_method' => 'paystack',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
        ])
        ->assertSessionHasErrors('payment_method');
});

test('an enabled method is accepted', function () {
    Storage::fake('local');

    $this->actingAs(schoolAtPaymentStep())
        ->post(route('subscriptions.payment-method.store'), [
            'payment_method' => 'bank_transfer',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();
});

test('with every method disabled the page says so instead of breaking', function () {
    PaymentMethodSetting::query()->update(['is_enabled' => false]);

    $this->actingAs(schoolAtPaymentStep())
        ->get(route('subscriptions.payment-method'))
        ->assertOk()
        ->assertSee('No payment methods are available right now')
        ->assertSee('Back');
});

test('with every method disabled nothing can be submitted either', function () {
    Storage::fake('local');
    PaymentMethodSetting::query()->update(['is_enabled' => false]);

    $this->actingAs(schoolAtPaymentStep())
        ->post(route('subscriptions.payment-method.store'), [
            'payment_method' => 'bank_transfer',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
        ])
        ->assertSessionHasErrors('payment_method');
});

// ---------------------------------------------------------------------------
// The service both sides ask
// ---------------------------------------------------------------------------

test('the availability service and the page agree', function () {
    $available = app(AvailablePaymentMethods::class);

    expect($available->allows('bank_transfer'))->toBeTrue()
        ->and($available->allows('paystack'))->toBeFalse()
        ->and($available->allows('not_a_method'))->toBeFalse()
        ->and($available->noneAvailable())->toBeFalse();

    PaymentMethodSetting::query()->update(['is_enabled' => false]);

    expect(app(AvailablePaymentMethods::class)->noneAvailable())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Who may change it
// ---------------------------------------------------------------------------

test('a School Admin cannot reach payment settings', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)
        ->get(route('super-admin.payment-settings.index'))
        ->assertForbidden();
});

test('an account number with letters in it is refused', function () {
    // The one field on the page where a typo costs somebody their money.
    $this->actingAs($this->team)
        ->put(route('super-admin.payment-settings.update', $this->bankTransfer), [
            'label' => 'Bank Transfer',
            'account_number' => '01234ABCDE',
        ])
        ->assertSessionHasErrors('account_number');
});

test('the shipped placeholder is flagged until it is replaced', function () {
    $this->actingAs($this->team)
        ->get(route('super-admin.payment-settings.index'))
        ->assertOk()
        ->assertSee('placeholder details that shipped with EduNest');

    $this->bankTransfer->update([
        'details' => ['bank_name' => 'Zenith Bank', 'account_number' => '1234509876'],
    ]);

    $this->actingAs($this->team)
        ->get(route('super-admin.payment-settings.index'))
        ->assertOk()
        ->assertDontSee('placeholder details that shipped with EduNest');
});
