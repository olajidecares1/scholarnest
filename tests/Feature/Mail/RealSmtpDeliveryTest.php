<?php

use App\Enums\PaymentMethod;
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
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Every required email, delivered over a REAL SMTP connection.
 *
 * The other mail tests fake the notification layer. This one does not: it
 * points the smtp mailer at a local SMTP server that writes each message it
 * receives to disk, runs the real workflows, then opens the delivered .eml
 * files and reads them the way a mail client would, the headers, the text,
 * the PDF attachment, and the reset code typed back into the page.
 *
 * Skipped unless SMTP_SINK_DIR and SMTP_SINK_PORT are set, because it needs
 * that server running.
 */
beforeEach(function () {
    $this->inbox = getenv('SMTP_SINK_DIR') ?: null;

    if (! $this->inbox || ! is_dir($this->inbox)) {
        $this->markTestSkipped('Set SMTP_SINK_DIR and SMTP_SINK_PORT to a running SMTP sink to run real delivery tests.');
    }

    array_map('unlink', glob($this->inbox.'/*.eml') ?: []);

    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => (int) getenv('SMTP_SINK_PORT'),
        'mail.mailers.smtp.scheme' => 'smtp',
        'mail.mailers.smtp.username' => null,
        'mail.mailers.smtp.password' => null,
        'mail.from.address' => 'support@akademicanest.com',
        'mail.from.name' => 'AkademicNest',
    ]);
    Mail::purge('smtp');

    $this->seed(PlanSeeder::class);

    $this->superAdmin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'email' => 'team@akademicanest.test',
        'password' => Hash::make('Team!Old2Pass'),
    ]);

    $this->school = School::factory()->create(['name' => 'Riverside Academy', 'billing_email' => 'bursar@riverside.test']);

    $this->admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $this->school->id,
        'email' => 'head@riverside.test',
        'name' => 'Tunde Bakare',
        'password' => Hash::make('School!Old2Pass'),
    ]);
});

/**
 * The messages delivered to one address, parsed.
 *
 * @return list<array{subject: string, to: string, text: string, raw: string}>
 */
function deliveredTo(string $address): array
{
    $messages = [];

    foreach (glob(test()->inbox.'/*.eml') ?: [] as $file) {
        $raw = (string) file_get_contents($file);
        [$head] = explode("\r\n\r\n", $raw, 2);
        $headers = iconv_mime_decode_headers($head, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: [];

        if (! str_contains(strtolower((string) ($headers['X-Envelope-To'] ?? '')), strtolower($address))) {
            continue;
        }

        $messages[] = [
            'subject' => (string) ($headers['Subject'] ?? ''),
            'to' => (string) ($headers['To'] ?? ''),
            'from' => (string) ($headers['From'] ?? ''),
            'text' => html_entity_decode(strip_tags(quoted_printable_decode($raw))),
            'raw' => $raw,
        ];
    }

    return $messages;
}

/**
 * @param  list<array{subject: string}>  $messages
 */
function messageWithSubject(array $messages, string $fragment): array
{
    foreach ($messages as $message) {
        if (str_contains($message['subject'], $fragment)) {
            return $message;
        }
    }

    throw new RuntimeException("No delivered message with a subject containing \"{$fragment}\". Got: ".implode(' | ', array_column($messages, 'subject')));
}

/**
 * The PDF attachment in a delivered message, decoded.
 */
function pdfAttachment(array $message): ?string
{
    if (! preg_match('/Content-Type: application\/pdf[^\r\n]*\r\n(?:[^\r\n]+\r\n)*\r\n([A-Za-z0-9+\/=\r\n]+)/', $message['raw'], $match)) {
        return null;
    }

    return base64_decode(preg_replace('/\s+/', '', $match[1]));
}

test('approving a new school delivers the welcome email and the paid invoice with its PDF', function () {
    $subscription = Subscription::factory()->create([
        'school_id' => $this->school->id,
        'plan_id' => Plan::where('key', PlanKey::Basic)->firstOrFail()->id,
        'billing_cycle' => 'per_student_per_term',
        'students_count' => 300,
        'amount' => 150000,
        'status' => SubscriptionStatus::PendingVerification,
        'reference' => 'RVS-2026-0007',
    ]);

    Payment::factory()->create([
        'subscription_id' => $subscription->id,
        'status' => PaymentStatus::Pending,
        'method' => PaymentMethod::BankTransfer,
        'amount' => 150000,
        'reference' => 'RVS-2026-0007',
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.subscriptions.approve', $subscription))
        ->assertSessionMissing('email_error');

    $toAdmin = deliveredTo('head@riverside.test');

    $welcome = messageWithSubject($toAdmin, 'Welcome to AkademicNest');
    expect($welcome['from'])->toContain('support@akademicanest.com')
        ->and($welcome['text'])->toContain('Riverside Academy has been approved')
        ->and($welcome['text'])->toContain('head@riverside.test')
        ->and($welcome['text'])->toContain('300 students')
        ->and($welcome['text'])->not->toContain('School!Old2Pass');

    $invoice = messageWithSubject($toAdmin, 'Payment Receipt: AkademicNest Invoice');
    $pdf = pdfAttachment($invoice);

    expect($invoice['text'])->toContain('NGN 150,000.00')
        ->and($invoice['text'])->toContain('RVS-2026-0007')
        ->and($invoice['text'])->toContain('Bank Transfer')
        ->and($pdf)->not->toBeNull()
        ->and(substr((string) $pdf, 0, 4))->toBe('%PDF');

    // The bursar receives the invoice, and not the login details.
    $toBursar = deliveredTo('bursar@riverside.test');

    expect(messageWithSubject($toBursar, 'Payment Receipt')['subject'])->toContain('Invoice')
        ->and(array_filter($toBursar, fn ($m) => str_contains($m['subject'], 'Welcome')))->toBeEmpty();
});

test('approving a top-up delivers the top-up confirmation and its invoice', function () {
    $subscription = Subscription::factory()->create([
        'school_id' => $this->school->id,
        'plan_id' => Plan::where('key', PlanKey::Basic)->firstOrFail()->id,
        'students_count' => 300,
        'amount' => 150000,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonths(4),
    ]);

    $topUp = SubscriptionTopUp::factory()->create([
        'subscription_id' => $subscription->id,
        'additional_students_count' => 40,
        'additional_amount' => 20000,
        'price_per_student' => 500,
        'payment_method' => PaymentMethod::BankTransfer,
        'reference' => 'TOP-RVS-0003',
        'status' => SubscriptionTopUpStatus::PendingVerification,
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.subscriptions.top-ups.approve', $topUp), ['approved_students_count' => 40])
        ->assertSessionMissing('email_error');

    $toAdmin = deliveredTo('head@riverside.test');

    $confirmation = messageWithSubject($toAdmin, 'Top-Up Approved');
    expect($confirmation['text'])->toContain('Previous capacity: 300 students')
        ->and($confirmation['text'])->toContain('Additional capacity purchased: 40 students')
        ->and($confirmation['text'])->toContain('New total capacity: 340 students')
        ->and($confirmation['text'])->toContain('NGN 20,000.00')
        ->and($confirmation['text'])->toContain('TOP-RVS-0003');

    $invoice = messageWithSubject($toAdmin, 'Payment Receipt: AkademicNest Invoice');
    expect($invoice['text'])->toContain('NGN 20,000.00')
        ->and(substr((string) pdfAttachment($invoice), 0, 4))->toBe('%PDF')
        ->and(array_filter($toAdmin, fn ($m) => str_contains($m['subject'], 'Welcome')))->toBeEmpty();
});

dataset('accounts', [
    'School Admin' => ['admin', 'School!Old2Pass'],
    'AkademicNest Super Admin' => ['superAdmin', 'Team!Old2Pass'],
]);

test('forgot password works end to end with the code from the delivered email', function (string $who, string $oldPassword) {
    $user = $this->{$who};

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

    $email = messageWithSubject(deliveredTo($user->email), 'Password Reset Code');

    expect(preg_match('/verification code on the password reset page:\s*#?\s*(\d{6})/', $email['text'], $match))->toBe(1);
    $code = $match[1];

    // A wrong code: refused, no password fields.
    $this->post(route('password.verify'), ['code' => $code === '000000' ? '111111' : '000000'])
        ->assertSessionHasErrors('code');
    $this->get(route('password.request'))->assertDontSee('name="password"', false);

    // The code from the email: on to the password fields.
    $this->post(route('password.verify'), ['code' => $code])->assertSessionHasNoErrors();
    $this->get(route('password.request'))->assertSee('name="password_confirmation"', false);

    $this->post(route('password.store'), [
        'password' => 'Fresh!New9Pass',
        'password_confirmation' => 'Fresh!New9Pass',
    ])->assertSessionHasNoErrors();

    expect(Auth::guard('web')->validate(['email' => $user->email, 'password' => 'Fresh!New9Pass']))->toBeTrue()
        ->and(Auth::guard('web')->validate(['email' => $user->email, 'password' => $oldPassword]))->toBeFalse();

    // The account holder is told, and the code is spent.
    expect(messageWithSubject(deliveredTo($user->email), 'Password Was Changed')['text'])->toContain('password');

    $this->withSession(['password_reset.email' => $user->email])
        ->post(route('password.verify'), ['code' => $code])
        ->assertSessionHasErrors('code');
})->with('accounts');
