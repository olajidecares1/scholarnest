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

test('the payment page shows the account details the AkademicNest Team entered', function () {
    $this->bankTransfer->update([
        'details' => [
            'bank_name' => 'Zenith Bank',
            'account_name' => 'AkademicNest Nigeria Ltd',
            'account_number' => '1234509876',
        ],
    ]);

    $this->actingAs(schoolAtPaymentStep())
        ->get(route('subscriptions.payment-method'))
        ->assertOk()
        ->assertSee('Zenith Bank')
        ->assertSee('AkademicNest Nigeria Ltd')
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
            'account_name' => 'AkademicNest Ltd',
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
    // A bare "Sort Code" label on a payment page is worse than no row: somebody transfers
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
    //
    // Asserted against this method's OWN error bag: the page carries a form
    // per payment method, so each validates into a bag named after itself and
    // Bank Transfer's error cannot surface under Paystack's field.
    $this->actingAs($this->team)
        ->put(route('super-admin.payment-settings.update', $this->bankTransfer), [
            'label' => 'Bank Transfer',
            'account_number' => '01234ABCDE',
        ])
        ->assertSessionHasErrorsIn('bank_transfer', ['account_number']);
});

test('the shipped placeholder is flagged until it is replaced', function () {
    $this->actingAs($this->team)
        ->get(route('super-admin.payment-settings.index'))
        ->assertOk()
        ->assertSee('placeholder details that shipped with AkademicNest');

    $this->bankTransfer->update([
        'details' => ['bank_name' => 'Zenith Bank', 'account_number' => '1234509876'],
    ]);

    $this->actingAs($this->team)
        ->get(route('super-admin.payment-settings.index'))
        ->assertOk()
        ->assertDontSee('placeholder details that shipped with AkademicNest');
});

/**
 * One page, one form per payment method, and they must not collide.
 *
 * This is where "I updated the payment settings and schools still see the old
 * account" came from. Nothing was wrong with saving; the fields on the page
 * were wired so the wrong one got typed into.
 */
describe('the two forms on the page stay separate', function () {
    test('no field id appears twice', function () {
        $body = $this->actingAs($this->team)
            ->get(route('super-admin.payment-settings.index'))
            ->getContent();

        // Every input used to carry a bare id, so the page held two
        // id="account_number", and BOTH <label for="account_number"> pointed
        // at whichever came first in the document. Clicking "Account Number"
        // on the second card put the cursor in the first card's field.
        preg_match_all('/\sid="([^"]+)"/', $body, $matches);

        $duplicates = collect($matches[1])
            ->countBy()
            ->filter(fn (int $count) => $count > 1)
            ->keys();

        expect($duplicates->all())->toBe([]);
    });

    test('the ids are scoped to their own method', function () {
        $this->actingAs($this->team)
            ->get(route('super-admin.payment-settings.index'))
            ->assertOk()
            ->assertSee('id="bank_transfer-account_number"', false)
            ->assertSee('id="bank_transfer-label"', false)
            ->assertSee('id="paystack-label"', false);
    });

    test('only a method that takes bank transfers offers bank fields', function () {
        // Paystack collects the money itself. Bank fields on its form are a
        // trap: they save to a row nothing reads, so a Super Admin who typed
        // their account number there would see no change anywhere.
        $body = $this->actingAs($this->team)
            ->get(route('super-admin.payment-settings.index'))
            ->getContent();

        expect(substr_count($body, 'name="account_number"'))->toBe(1)
            ->and(substr_count($body, 'name="bank_name"'))->toBe(1)
            ->and($body)->toContain('id="bank_transfer-account_number"')
            ->and($body)->not->toContain('id="paystack-account_number"');
    });

    test('a rejected save shows its error on its own card only', function () {
        $this->actingAs($this->team)
            ->put(route('super-admin.payment-settings.update', $this->bankTransfer), [
                'label' => 'Bank Transfer',
                'account_number' => 'not-a-number',
            ])
            ->assertSessionHasErrorsIn('bank_transfer', ['account_number'])
            ->assertSessionDoesntHaveErrors(['account_number']);
    });
});

describe('saving one method never damages another, or itself', function () {
    test('saving Paystack leaves the bank account alone', function () {
        $this->bankTransfer->update([
            'details' => ['bank_name' => 'Zenith Bank', 'account_name' => 'AkademicNest Ltd', 'account_number' => '1234509876'],
        ]);

        $this->actingAs($this->team)
            ->put(route('super-admin.payment-settings.update', $this->paystack), [
                'label' => 'Paystack',
            ])
            ->assertSessionHasNoErrors();

        expect($this->bankTransfer->fresh()->detail('account_number'))->toBe('1234509876');
    });

    test('a save that omits the bank fields does not wipe them', function () {
        // `$validated[x] ?? null` used to write null over a perfectly good
        // account number, so any submit missing an input silently blanked the
        // account schools are told to pay into.
        $this->bankTransfer->update([
            'details' => ['bank_name' => 'Zenith Bank', 'account_name' => 'AkademicNest Ltd', 'account_number' => '1234509876'],
        ]);

        $this->actingAs($this->team)
            ->put(route('super-admin.payment-settings.update', $this->bankTransfer), [
                'label' => 'Bank Transfer',
                'instructions' => 'Pay in, then upload your receipt.',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $this->bankTransfer->fresh();

        expect($fresh->detail('account_number'))->toBe('1234509876')
            ->and($fresh->detail('bank_name'))->toBe('Zenith Bank')
            ->and($fresh->instructions)->toBe('Pay in, then upload your receipt.');
    });

    test('the new details reach a Standard school in the wizard', function () {
        $this->actingAs($this->team)
            ->put(route('super-admin.payment-settings.update', $this->bankTransfer), [
                'label' => 'Bank Transfer',
                'bank_name' => 'Access Bank',
                'account_name' => 'AkademicNest Nigeria',
                'account_number' => '0987654321',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs(schoolAtPaymentStep())
            ->get(route('subscriptions.payment-method'))
            ->assertOk()
            ->assertSee('Access Bank')
            ->assertSee('0987654321')
            ->assertDontSee('GTBank');
    });
});

/**
 * The top-up page shows the SAME account as everywhere else.
 *
 * It carried the bank details in its own template, so the AkademicNest Team
 * could change the account in Payment Settings and every "Add More Students"
 * page went on showing the placeholder that shipped with the migration,
 * GTBank / AkademicNest Technologies Ltd / 0123456789, on every plan.
 */
describe('adding student places shows the real account', function () {
    /**
     * A school admin on a per-student plan, which is who sees the top-up page.
     */
    function schoolTellingItsCapacity(PlanKey $planKey): User
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

        activateSchool($school, $planKey);

        $subscription = $school->fresh()->activeSubscription;
        $subscription->update(['students_count' => 100, 'amount' => 100000]);

        return $admin;
    }

    test('a Standard school sees what Payment Settings says', function () {
        $this->bankTransfer->update([
            'details' => ['bank_name' => 'Access Bank', 'account_name' => 'Samson Olajide Jacob', 'account_number' => '0121750252'],
        ]);

        $this->actingAs(schoolTellingItsCapacity(PlanKey::Standard))
            ->get(route('subscription-top-up.create'))
            ->assertOk()
            ->assertSee('Access Bank')
            ->assertSee('Samson Olajide Jacob')
            ->assertSee('0121750252')

            // The values that were typed into the template.
            ->assertDontSee('AkademicNest Technologies Ltd')
            ->assertDontSee('0123456789');
    });

    test('a Basic school sees the same account', function () {
        $this->bankTransfer->update([
            'details' => ['bank_name' => 'Access Bank', 'account_number' => '0121750252'],
        ]);

        $this->actingAs(schoolTellingItsCapacity(PlanKey::Basic))
            ->get(route('subscription-top-up.create'))
            ->assertOk()
            ->assertSee('0121750252')
            ->assertDontSee('0123456789');
    });

    test('with no account set up it says so rather than inventing one', function () {
        // Money transferred to an account nobody owns does not come back.
        $this->bankTransfer->update(['details' => []]);

        $this->actingAs(schoolTellingItsCapacity(PlanKey::Standard))
            ->get(route('subscription-top-up.create'))
            ->assertOk()
            ->assertSee('No payment account has been set up yet')
            ->assertDontSee('0123456789');
    });

    test('a disabled bank transfer leaves no account on the page', function () {
        $this->bankTransfer->update(['is_enabled' => false]);

        $this->actingAs(schoolTellingItsCapacity(PlanKey::Standard))
            ->get(route('subscription-top-up.create'))
            ->assertOk()
            ->assertSee('No payment account has been set up yet');
    });
});
