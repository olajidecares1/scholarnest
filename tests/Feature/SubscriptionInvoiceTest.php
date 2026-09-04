<?php

use App\Enums\PaymentStatus;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\SubscriptionInvoiceIssuer;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\URL;

/**
 * ScholarNest's invoices to its schools.
 *
 * Distinct from App\Models\Invoice, which is a school billing a parent for
 * school fees. Same word, unrelated business - and the separation is the point
 * of several of these tests.
 */
beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->school = School::factory()->create([
        'name' => 'Vincent Martins College',
        'billing_email' => 'bursar@vmc.example',
        'billing_phone' => '08030000000',
    ]);

    $this->admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $this->school->id,
    ]);
});

function invoicedSubscription(?School $school = null, int $students = 120, float $amount = 180000, string $reference = 'VMC-2026-ABCD'): Subscription
{
    $school ??= test()->school;

    $subscription = Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => Plan::where('key', PlanKey::Basic)->firstOrFail()->id,
        'billing_cycle' => 'per_student_per_term',
        'students_count' => $students,
        'amount' => $amount,
        'status' => SubscriptionStatus::PendingVerification,
        // Unique per subscription, like the real one: the column is unique,
        // so a fixture that reuses a reference cannot create two.
        'reference' => $reference,
    ]);

    Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'status' => PaymentStatus::Pending,
    ]);

    return $subscription;
}

describe('what an invoice records', function () {
    test('it carries everything the school needs to file it', function () {
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

        expect($invoice->number)->toStartWith('SN-'.now()->format('Y').'-')
            ->and($invoice->billed_to_name)->toBe('Vincent Martins College')
            ->and($invoice->billed_to_email)->toBe('bursar@vmc.example')
            ->and($invoice->billed_to_phone)->toBe('08030000000')
            ->and($invoice->plan_name)->toBe('Basic Plan')
            ->and($invoice->licences)->toBe(120)
            ->and((float) $invoice->unit_price)->toBe(1500.0)
            ->and((float) $invoice->total)->toBe(180000.0)
            ->and($invoice->payment_reference)->toBe('VMC-2026-ABCD')
            ->and($invoice->issued_at)->not->toBeNull();
    });

    test('the figures are a snapshot, not a live reading of the plan', function () {
        $subscription = invoicedSubscription();
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription($subscription);

        // A Super Admin raises the price. What this school was charged in
        // February must still say what it said in February - an invoice that
        // restates itself is not a record of anything.
        $subscription->plan->update(['price_per_student_per_term' => 9999]);

        expect((float) $invoice->fresh()->total)->toBe(180000.0)
            ->and((float) $invoice->fresh()->unit_price)->toBe(1500.0);
    });

    test('renaming the school does not rewrite invoices it has already been sent', function () {
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

        $this->school->update(['name' => 'Something Else Entirely']);

        expect($invoice->fresh()->billed_to_name)->toBe('Vincent Martins College');
    });
});

describe('invoice numbering', function () {
    test('numbers run in sequence and never repeat', function () {
        $issuer = app(SubscriptionInvoiceIssuer::class);

        $numbers = collect(['REF-A', 'REF-B', 'REF-C'])
            ->map(fn (string $reference) => $issuer->issueForSubscription(
                invoicedSubscription(reference: $reference)
            )->number);

        $year = now()->format('Y');

        expect($numbers->all())->toBe([
            "SN-{$year}-000001",
            "SN-{$year}-000002",
            "SN-{$year}-000003",
        ]);
    });

    test('one subscription is billed once, however many times the request arrives', function () {
        $subscription = invoicedSubscription();
        $issuer = app(SubscriptionInvoiceIssuer::class);

        // A double-clicked submit button, a retried request, a re-delivered
        // job. Billing a school twice for one subscription is not a bug you
        // get to fix quietly afterwards.
        $first = $issuer->issueForSubscription($subscription);
        $second = $issuer->issueForSubscription($subscription);

        expect($second->id)->toBe($first->id)
            ->and(SubscriptionInvoice::count())->toBe(1);
    });
});

describe('payment status is read, never stored', function () {
    test('a fresh invoice is awaiting approval, not paid', function () {
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

        expect($invoice->paymentStatus())->toBe('Awaiting approval')
            ->and($invoice->isPaid())->toBeFalse()
            ->and($invoice->approvedAt())->toBeNull();
    });

    test('approving the payment is what makes the invoice paid', function () {
        $subscription = invoicedSubscription();
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription($subscription);

        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

        $subscription->latestPayment->update([
            'status' => PaymentStatus::Verified,
            'verified_by' => $superAdmin->id,
            'verified_at' => now(),
        ]);

        $invoice = $invoice->fresh();

        // Nothing on the invoice row changed. That is the design: one answer
        // to "has this been paid", living where the approval happens.
        expect($invoice->paymentStatus())->toBe('Paid')
            ->and($invoice->isPaid())->toBeTrue()
            ->and($invoice->approvedBy()->id)->toBe($superAdmin->id)
            ->and($invoice->approvedAt())->not->toBeNull();
    });
});

describe('who may fetch an invoice', function () {
    test('the school that was billed may download its own', function () {
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

        $this->actingAs($this->admin)
            ->get(route('invoices.download', $invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });

    test('another school may not, however it addresses the request', function () {
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

        $otherSchool = School::factory()->create();
        $intruder = User::factory()->create([
            'role' => UserRole::SchoolAdmin,
            'school_id' => $otherSchool->id,
        ]);

        // The uuid is in the URL and the school comes from the session, so
        // there is nothing to tamper with - which is the point.
        $this->actingAs($intruder)
            ->get(route('invoices.download', $invoice))
            ->assertForbidden();
    });

    test('a Super Admin may fetch any school\'s', function () {
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

        $this->actingAs($superAdmin)
            ->get(route('invoices.download', $invoice))
            ->assertOk();
    });

    test('a signed link works with no session at all', function () {
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

        // The link in the billing email. Whoever opens it is often a bursar
        // with no account here, so it cannot depend on a session.
        $url = URL::temporarySignedRoute(
            'invoices.view',
            now()->addDays(90),
            $invoice,
        );

        $this->assertGuest();

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    });

    test('the same address without a signature is refused', function () {
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

        $this->get(route('invoices.view', $invoice))->assertForbidden();
    });

    test('an expired link stops working', function () {
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

        $url = URL::temporarySignedRoute(
            'invoices.view',
            now()->addDays(90),
            $invoice,
        );

        $this->travel(91)->days();

        $this->get($url)->assertForbidden();
    });
});

describe('the printed invoice', function () {
    test('it says awaiting approval while it is', function () {
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

        $html = view('invoices.pdf.subscription-invoice', ['invoice' => $invoice])->render();

        // A billing document that reads like a receipt, for something still
        // awaiting approval, is how a school comes to believe it has an
        // account it does not have.
        expect($html)->toContain('Awaiting approval')
            ->toContain('Total Payable')
            ->toContain($invoice->number)
            ->toContain('Vincent Martins College')
            ->toContain('120');
    });

    test('it names the approver once approved', function () {
        $subscription = invoicedSubscription();
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription($subscription);

        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null, 'name' => 'Grace Adeyemi']);

        $subscription->latestPayment->update([
            'status' => PaymentStatus::Verified,
            'verified_by' => $superAdmin->id,
            'verified_at' => now(),
        ]);

        $html = view('invoices.pdf.subscription-invoice', ['invoice' => $invoice->fresh()])->render();

        expect($html)->toContain('Total Paid')
            ->toContain('Grace Adeyemi');
    });
});

test('a subscription invoice is not a school fee invoice', function () {
    app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

    // Two tables, two models, one word. Raising ScholarNest's invoice must
    // never put a row in the table a school uses to bill its parents.
    expect(SubscriptionInvoice::count())->toBe(1)
        ->and(Invoice::count())->toBe(0);
});

test('a school admin sees their own billing history and nobody else\'s', function () {
    $mine = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(invoicedSubscription());

    $otherSchool = School::factory()->create(['name' => 'Somebody Else']);
    $theirs = app(SubscriptionInvoiceIssuer::class)->issueForSubscription(
        invoicedSubscription(school: $otherSchool, reference: 'OTHER-1')
    );

    $this->actingAs($this->admin)
        ->get(route('invoices.index'))
        ->assertOk()
        ->assertSee($mine->number)
        ->assertDontSee($theirs->number);
});
