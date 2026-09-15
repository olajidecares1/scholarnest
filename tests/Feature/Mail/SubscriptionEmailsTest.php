<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTopUpStatus;
use App\Enums\UserRole;
use App\Models\EmailDelivery;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionTopUp;
use App\Models\User;
use App\Notifications\MailDeliveryTestNotification;
use App\Notifications\SubscriptionApprovedNotification;
use App\Notifications\SubscriptionInvoiceIssuedNotification;
use App\Notifications\SubscriptionTopUpApprovedNotification;
use App\Services\Mail\MailReadiness;
use App\Services\Mail\SubscriptionEmails;
use Database\Seeders\PlanSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * The emails a school receives when its payment is approved.
 *
 *   New subscription: welcome email + paid invoice (PDF attached)
 *   Top-up:           top-up confirmation + paid invoice for the top-up
 *
 * And the rule that matters most: a failure is never silent.
 */
beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->school = School::factory()->create([
        'name' => 'Greenfield College',
        'billing_email' => 'bursar@greenfield.test',
    ]);

    $this->admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $this->school->id,
        'email' => 'principal@greenfield.test',
        'username' => 'greenfield-admin',
    ]);

    // Another school's admin, who must never receive Greenfield's emails.
    $this->otherAdmin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => School::factory()->create()->id,
    ]);
});

function pendingGreenfieldSubscription(): Subscription
{
    $subscription = Subscription::factory()->create([
        'school_id' => test()->school->id,
        'plan_id' => Plan::where('key', PlanKey::Basic)->firstOrFail()->id,
        'billing_cycle' => 'per_student_per_term',
        'students_count' => 200,
        'amount' => 100000,
        'status' => SubscriptionStatus::PendingVerification,
        'reference' => 'GRN-2026-0001',
    ]);

    Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'status' => PaymentStatus::Pending,
        'method' => PaymentMethod::BankTransfer,
        'amount' => 100000,
        'reference' => 'GRN-2026-0001',
        'created_at' => now()->subDay(),
    ]);

    return $subscription;
}

function approvedGreenfieldTopUp(): SubscriptionTopUp
{
    $subscription = pendingGreenfieldSubscription();
    $subscription->update(['status' => SubscriptionStatus::Active, 'starts_at' => now(), 'ends_at' => now()->addMonths(4)]);

    return SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 50,
        'additional_amount' => 25000,
        'price_per_student' => 500,
        'payment_method' => PaymentMethod::BankTransfer,
        'reference' => 'TOP-2026-0042',
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);
}

describe('approving a new school', function () {
    test('sends the welcome email and the paid invoice to the school admin', function () {
        Notification::fake();
        $subscription = pendingGreenfieldSubscription();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.subscriptions.approve', $subscription))
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'principal@greenfield.test'))
            ->assertSessionMissing('email_error');

        expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);

        Notification::assertSentTo($this->admin, SubscriptionApprovedNotification::class);
        Notification::assertSentTo($this->admin, SubscriptionInvoiceIssuedNotification::class, fn ($n) => $n->invoice->isPaid());

        // The bursar gets the invoice, not the welcome with the login details.
        Notification::assertSentOnDemand(SubscriptionInvoiceIssuedNotification::class, fn ($n, $channels, AnonymousNotifiable $notifiable) => $notifiable->routeNotificationFor('mail') === 'bursar@greenfield.test');

        // Nobody at another school.
        Notification::assertNotSentTo($this->otherAdmin, SubscriptionApprovedNotification::class);
        Notification::assertNotSentTo($this->otherAdmin, SubscriptionInvoiceIssuedNotification::class);

        expect(EmailDelivery::where('about_id', $subscription->id)->where('status', EmailDelivery::SENT)->pluck('kind')->unique()->sort()->values()->all())
            ->toBe([SubscriptionEmails::INVOICE, SubscriptionEmails::WELCOME]);
    });

    test('the welcome email confirms approval and says how to sign in, without a password', function () {
        $subscription = pendingGreenfieldSubscription();
        $subscription->update(['status' => SubscriptionStatus::Active, 'starts_at' => now(), 'ends_at' => now()->addMonths(4)]);

        $mail = (new SubscriptionApprovedNotification($subscription->fresh()))->toMail($this->admin);
        $body = implode(' ', array_merge($mail->introLines, $mail->outroLines));

        expect($mail->subject)->toContain('Greenfield College')->toContain('Active')
            ->and($body)->toContain('has been approved')
            ->and($body)->toContain('principal@greenfield.test')
            ->and($body)->toContain('greenfield-admin')
            ->and($body)->toContain('Basic Plan')
            ->and($body)->toContain('200 students')
            ->and($body)->toContain('100,000.00')
            ->and($body)->toContain('GRN-2026-0001')
            ->and($body)->not->toContain('password is')
            ->and($mail->actionUrl)->toContain('/portal/admin/');
    });

    test('the invoice email is the paid receipt, with the PDF attached and the transaction on it', function () {
        $subscription = pendingGreenfieldSubscription();

        $this->actingAs($this->superAdmin)->post(route('super-admin.subscriptions.approve', $subscription));

        $invoice = $subscription->fresh()->invoice ?? SubscriptionInvoice::where('subscription_id', $subscription->id)->firstOrFail();
        $mail = (new SubscriptionInvoiceIssuedNotification($invoice))->toMail($this->admin);
        $body = implode(' ', array_merge($mail->introLines, $mail->outroLines));

        expect($mail->subject)->toBe('Payment Receipt: AkademicNest Invoice '.$invoice->number)
            ->and($body)->toContain('Greenfield College')
            ->and($body)->toContain('Basic Plan')
            ->and($body)->toContain('200')
            ->and($body)->toContain('NGN 100,000.00')
            ->and($body)->toContain('GRN-2026-0001')
            ->and($body)->toContain('Bank Transfer')
            ->and($body)->toContain(now()->subDay()->format('j F Y'))
            ->and($body)->toContain('Paid')
            ->and($body)->not->toContain('awaiting approval')
            ->and($mail->rawAttachments)->toHaveCount(1)
            ->and($mail->rawAttachments[0]['name'])->toBe($invoice->number.'.pdf')
            ->and($mail->rawAttachments[0]['options']['mime'])->toBe('application/pdf')
            ->and(substr($mail->rawAttachments[0]['data'], 0, 4))->toBe('%PDF');

        $html = view('invoices.pdf.subscription-invoice', ['invoice' => $invoice->fresh()])->render();

        expect($html)->toContain($invoice->number)
            ->toContain('Greenfield College')
            ->toContain('Basic Plan')
            ->toContain('NGN 100,000.00')
            ->toContain('GRN-2026-0001')
            ->toContain('Bank Transfer')
            ->toContain(now()->subDay()->format('j F Y'))
            ->toContain('Total Paid');
    });

    test('a mail failure is shown to the Super Admin, recorded, and can be resent', function () {
        Notification::fake();
        $subscription = pendingGreenfieldSubscription();

        $this->mock(MailReadiness::class, fn ($mock) => $mock->shouldReceive('problem')->andReturn('Email is not set up on this server.'));

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.subscriptions.approve', $subscription))
            ->assertSessionHas('email_error', fn (string $error) => str_contains($error, 'Email is not set up on this server.'));

        // The approval itself stands, and it is not dressed up as a success.
        expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
            ->and(EmailDelivery::where('about_id', $subscription->id)->where('status', EmailDelivery::SENT)->count())->toBe(0);

        Notification::assertNothingSent();

        // Mail fixed: resending delivers what was missed.
        $this->app->forgetInstance(MailReadiness::class);
        $this->app->offsetUnset(MailReadiness::class);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.subscriptions.resend-emails', $subscription))
            ->assertSessionMissing('email_error');

        Notification::assertSentTo($this->admin, SubscriptionApprovedNotification::class);
        Notification::assertSentTo($this->admin, SubscriptionInvoiceIssuedNotification::class);

        // Resending again sends nothing twice.
        Notification::fake();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.subscriptions.resend-emails', $subscription))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'already been delivered'));

        Notification::assertNothingSent();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.subscriptions.show', $subscription))
            ->assertOk()
            ->assertSee('Emails to the school')
            ->assertSee('Resend emails');
    });

    test('approving the same subscription twice does not email twice', function () {
        Notification::fake();
        $subscription = pendingGreenfieldSubscription();

        $this->actingAs($this->superAdmin)->post(route('super-admin.subscriptions.approve', $subscription));
        $this->actingAs($this->superAdmin)->post(route('super-admin.subscriptions.approve', $subscription))->assertStatus(409);

        expect(EmailDelivery::where('kind', SubscriptionEmails::WELCOME)->where('recipient', $this->admin->email)->count())->toBe(1);
    });
});

describe('approving a top-up', function () {
    test('sends its own confirmation and invoice, not the welcome email', function () {
        Notification::fake();
        $topUp = approvedGreenfieldTopUp();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.subscriptions.top-ups.approve', $topUp), ['approved_students_count' => 50])
            ->assertRedirect()
            ->assertSessionMissing('email_error');

        Notification::assertSentTo($this->admin, SubscriptionTopUpApprovedNotification::class);
        Notification::assertSentTo($this->admin, SubscriptionInvoiceIssuedNotification::class, fn ($n) => $n->invoice->subscription_top_up_id === $topUp->id && $n->invoice->isPaid());
        Notification::assertNotSentTo($this->admin, SubscriptionApprovedNotification::class);
        Notification::assertNotSentTo($this->otherAdmin, SubscriptionTopUpApprovedNotification::class);
    });

    test('the confirmation carries the capacity before, added and after, and the payment', function () {
        $topUp = approvedGreenfieldTopUp();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.subscriptions.top-ups.approve', $topUp), ['approved_students_count' => 50]);

        $topUp->refresh();
        $mail = (new SubscriptionTopUpApprovedNotification($topUp))->toMail($this->admin);
        $body = implode(' ', array_merge($mail->introLines, $mail->outroLines));

        expect($mail->subject)->toContain('Top-Up Approved')->toContain('250')
            ->and($body)->toContain('Greenfield College')
            ->and($body)->toContain('Previous capacity: 200 students')
            ->and($body)->toContain('Additional capacity purchased: 50 students')
            ->and($body)->toContain('New total capacity: 250 students')
            ->and($body)->toContain('Basic Plan')
            ->and($body)->toContain('NGN 25,000.00')
            ->and($body)->toContain('TOP-2026-0042')
            ->and($body)->toContain('Bank Transfer')
            ->and($body)->toContain($topUp->created_at->format('j F Y'))
            ->and($body)->not->toContain('Welcome');

        $invoice = SubscriptionInvoice::where('subscription_top_up_id', $topUp->id)->firstOrFail();

        expect((float) $invoice->total)->toBe(25000.0)
            ->and($invoice->licences)->toBe(50)
            ->and($invoice->isPaid())->toBeTrue();

        $html = view('invoices.pdf.subscription-invoice', ['invoice' => $invoice])->render();

        expect($html)->toContain('NGN 25,000.00')
            ->toContain('TOP-2026-0042')
            ->toContain('200 students')
            ->toContain('250 students')
            ->toContain('Total Paid');
    });

    test('its emails can be resent after a failure', function () {
        Notification::fake();
        $topUp = approvedGreenfieldTopUp();

        $this->mock(MailReadiness::class, fn ($mock) => $mock->shouldReceive('problem')->andReturn('Could not connect to host.'));

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.subscriptions.top-ups.approve', $topUp), ['approved_students_count' => 50])
            ->assertSessionHas('email_error');

        $this->app->offsetUnset(MailReadiness::class);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.subscriptions.top-ups.resend-emails', $topUp))
            ->assertSessionMissing('email_error');

        Notification::assertSentTo($this->admin, SubscriptionTopUpApprovedNotification::class);
    });
});

describe('proving mail works on the server', function () {
    test('production refuses to call a log mailer "sent"', function () {
        app()->detectEnvironment(fn () => 'production');
        config(['mail.default' => 'log']);

        expect(app(MailReadiness::class)->problem())->toContain('MAIL_MAILER is "log"');

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.hostinger.com']);

        expect(app(MailReadiness::class)->problem())->toBeNull()
            ->and(app(MailReadiness::class)->summary())->not->toHaveKey('Password', 'secret');
    });

    test('the Super Admin can send a test email and see the outcome', function () {
        Notification::fake();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.settings.index'))
            ->assertOk()
            ->assertSee('Send test email');

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.settings.test-email'), ['test_email' => 'check@akademicanest.test'])
            ->assertSessionHas('mail_test_status');

        Notification::assertSentOnDemand(MailDeliveryTestNotification::class);

        $this->mock(MailReadiness::class, fn ($mock) => $mock->shouldReceive('problem')->andReturn('MAIL_HOST is empty.')->shouldReceive('summary')->andReturn([]));

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.settings.test-email'), ['test_email' => 'check@akademicanest.test'])
            ->assertSessionHas('mail_test_error', fn (string $error) => str_contains($error, 'MAIL_HOST is empty.'));
    });

    test('mail:test reports the result', function () {
        Notification::fake();

        $this->artisan('mail:test', ['email' => 'check@akademicanest.test'])
            ->expectsOutputToContain('Sent to check@akademicanest.test')
            ->assertSuccessful();
    });
});
