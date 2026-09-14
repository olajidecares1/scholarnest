<?php

use App\Enums\PaymentStatus;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Notifications\SubscriptionApprovedNotification;
use App\Notifications\SubscriptionInvoiceIssuedNotification;
use App\Services\SubscriptionInvoiceIssuer;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * WHICH email a school gets, and WHEN.
 *
 * The ordering is the requirement, not a detail of it. A school that has
 * submitted a payment is not a customer yet, a Super Admin has still to
 * approve it, and the two emails say two different things:
 *
 *   AT SUBMISSION: the invoice, which states plainly that the account is
 *   awaiting approval.
 *
 *   AT APPROVAL: the welcome, which says the account works.
 *
 * Sending the welcome at submission would tell a school it was live while its
 * portal would still turn its staff away.
 */
beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->school = School::factory()->create([
        'name' => 'Sequence Academy',
        'billing_email' => 'bursar@sequence.example',
    ]);

    $this->admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $this->school->id,
    ]);
});

function submittedSubscription(): Subscription
{
    $subscription = Subscription::factory()->create([
        'school_id' => test()->school->id,
        'plan_id' => Plan::where('key', PlanKey::Basic)->firstOrFail()->id,
        'billing_cycle' => 'per_student_per_term',
        'students_count' => 200,
        'amount' => 300000,
        'status' => SubscriptionStatus::PendingVerification,
        'reference' => 'SEQ-2026-0001',
    ]);

    Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'status' => PaymentStatus::Pending,
    ]);

    return $subscription;
}

test('submitting a subscription raises an invoice and emails it', function () {
    Notification::fake();

    $subscription = submittedSubscription();
    $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription($subscription);

    $this->admin->notify(new SubscriptionInvoiceIssuedNotification($invoice));

    Notification::assertSentTo($this->admin, SubscriptionInvoiceIssuedNotification::class);
});

test('the invoice email says the account is awaiting approval, and attaches the PDF', function () {
    $subscription = submittedSubscription();
    $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription($subscription);

    $mail = (new SubscriptionInvoiceIssuedNotification($invoice))->toMail($this->admin);

    $rendered = implode(' ', array_merge($mail->introLines, $mail->outroLines));

    expect($rendered)->toContain($invoice->number)
        ->toContain('Basic Plan')
        ->toContain('200')
        ->toContain('awaiting approval')
        ->and($mail->subject)->toContain($invoice->number);

    // Attached AND linked: mail systems strip attachments, and a link still
    // works when the attachment is gone.
    expect($mail->rawAttachments)->toHaveCount(1)
        ->and($mail->rawAttachments[0]['name'])->toBe($invoice->number.'.pdf')
        ->and($mail->actionUrl)->toContain('/invoices/');
});

test('no welcome email goes out while the payment is only submitted', function () {
    Notification::fake();

    submittedSubscription();

    // Nothing has approved anything. A "welcome, you're all set" here is the
    // one version of this email that damages trust.
    Notification::assertNotSentTo($this->admin, SubscriptionApprovedNotification::class);
});

test('the welcome email goes out when a Super Admin approves, and not before', function () {
    Notification::fake();

    $subscription = submittedSubscription();

    Notification::assertNotSentTo($this->admin, SubscriptionApprovedNotification::class);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.subscriptions.approve', $subscription))
        ->assertRedirect();

    Notification::assertSentTo($this->admin, SubscriptionApprovedNotification::class);
});

test('the welcome email carries the full subscription summary', function () {
    $subscription = submittedSubscription();
    $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription($subscription);

    $subscription->update([
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonths(4),
    ]);

    $mail = (new SubscriptionApprovedNotification($subscription->fresh(), $invoice))->toMail($this->admin);

    $rendered = implode(' ', array_merge($mail->introLines, $mail->outroLines));

    expect($rendered)
        ->toContain('Sequence Academy')
        ->toContain('bursar@sequence.example')
        ->toContain('Basic Plan')
        ->toContain('200')
        ->toContain('300,000.00')
        ->toContain('Approved')
        ->toContain('Active')
        ->toContain($invoice->number)
        ->toContain('SEQ-2026-0001');
});

describe('the Super Admin remains the gate', function () {
    test('registering does not activate anything', function () {
        // The school exists, the admin exists, and there is no subscription
        // at all, so nothing is active and nothing has been assumed.
        expect($this->school->fresh()->hasActiveSubscription())->toBeFalse()
            ->and($this->school->subscriptions()->count())->toBe(0);
    });

    test('submitting payment evidence does not activate anything', function () {
        $subscription = submittedSubscription();

        // Uploading a receipt is a claim, not a payment. Anyone can upload an
        // image; only a Super Admin can say it was money.
        expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PendingVerification)
            ->and($this->school->fresh()->hasActiveSubscription())->toBeFalse();
    });

    test('raising an invoice does not activate anything either', function () {
        $subscription = submittedSubscription();

        app(SubscriptionInvoiceIssuer::class)->issueForSubscription($subscription);

        expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PendingVerification)
            ->and($this->school->fresh()->hasActiveSubscription())->toBeFalse();
    });

    test('approval is what activates it, and records who did', function () {
        $subscription = submittedSubscription();
        $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription($subscription);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.subscriptions.approve', $subscription))
            ->assertRedirect();

        $subscription = $subscription->fresh();

        expect($subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($subscription->latestPayment->status)->toBe(PaymentStatus::Verified)
            ->and($subscription->latestPayment->verified_by)->toBe($this->superAdmin->id);

        // And the invoice now reads as paid, without anything on the invoice
        // row having been written.
        expect($invoice->fresh()->paymentStatus())->toBe('Paid')
            ->and($invoice->fresh()->approvedBy()->id)->toBe($this->superAdmin->id);
    });

    test('a school admin cannot approve their own subscription', function () {
        $subscription = submittedSubscription();

        $this->actingAs($this->admin)
            ->post(route('super-admin.subscriptions.approve', $subscription))
            ->assertForbidden();

        expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PendingVerification);
    });
});

test('the Super Admin sees the payment and invoice history for a school', function () {
    $subscription = submittedSubscription();
    $invoice = app(SubscriptionInvoiceIssuer::class)->issueForSubscription($subscription);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.subscriptions.approve', $subscription));

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.schools.show', $this->school))
        ->assertOk()
        ->assertSee($invoice->number)
        ->assertSee('Basic Plan')
        ->assertSee('Paid')
        // Who approved it, not merely that somebody did.
        ->assertSee($this->superAdmin->name);

    expect(SubscriptionInvoice::where('school_id', $this->school->id)->count())->toBe(1);
});
