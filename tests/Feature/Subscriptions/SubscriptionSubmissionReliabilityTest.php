<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\EmailDelivery;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Notifications\NewSubscriptionSubmittedNotification;
use App\Notifications\SchoolRegisteredNotification;
use App\Services\SubscriptionInvoiceIssuer;
use App\Support\SecureRoute;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UploadFixtures;

/*
 * The last steps of a school's registration: uploading the payment receipt
 * and pressing "Confirm & Submit".
 *
 * In production these failed with a server error whenever the mail server
 * refused the login: the application was saved, the team notification email
 * threw, the school saw an error page, went through the wizard again and the
 * same payment reached the AkademicNest Team twice.
 */

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Storage::fake('local');
    $this->team = User::factory()->create(['role' => UserRole::SuperAdmin]);
});

/** A mail server that refuses every connection, as a wrong password does. */
function refuseAllMail(): void
{
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
        'mail.mailers.smtp.username' => 'support@example.com',
        'mail.mailers.smtp.password' => 'wrong',
        'mail.from.address' => 'support@example.com',
    ]);
}

function registeringSchoolAdmin(string $schoolName = 'Greenfield Academy'): User
{
    $school = School::factory()->create(['name' => $schoolName]);

    return User::factory()->create(['school_id' => $school->id, 'role' => UserRole::SchoolAdmin]);
}

/**
 * Walk a school through every step up to the review page.
 */
function walkToReview($test, User $user, ?UploadedFile $receipt = null): void
{
    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();

    $test->actingAs($user)->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id]);
    $test->actingAs($user)->post(route('subscriptions.students.store'), ['students_count' => 100]);
    $test->actingAs($user)->post(route('subscriptions.billing-details.store'), [
        'billing_contact_name' => 'Jane Doe',
        'billing_email' => 'billing@greenfield.example',
        'billing_phone' => '08012345678',
        'billing_address' => '1 School Road',
    ]);
    $test->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => $receipt ?? UploadFixtures::receiptPng(),
    ])->assertSessionHasNoErrors()->assertRedirect(route('subscriptions.review'));
}

test('the final step completes and saves everything even when the mail server refuses', function () {
    refuseAllMail();
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);

    $response = $this->actingAs($user)->post(route('subscriptions.review.store'));

    $subscription = Subscription::sole();
    $response->assertRedirect(route('subscriptions.confirmation', $subscription));

    $payment = Payment::sole();
    expect($subscription->status)->toBe(SubscriptionStatus::PendingVerification)
        ->and($subscription->school_id)->toBe($user->school_id)
        ->and((float) $subscription->amount)->toEqual(50000.0)
        ->and($payment->subscription_id)->toBe($subscription->id)
        ->and($payment->receipt_original_name)->toBe('receipt.png')
        ->and(SubscriptionInvoice::where('subscription_id', $subscription->id)->count())->toBe(1)
        ->and(session('subscription_wizard'))->toBeNull();

    Storage::disk('local')->assertExists($payment->receipt_path);

    // The team still hears about it in the app, and the emails that did not
    // go are recorded with the reason, to be sent again.
    expect($this->team->notifications()->where('type', NewSubscriptionSubmittedNotification::class)->count())->toBe(1)
        ->and(EmailDelivery::where('kind', 'team.subscription.submitted')->value('status'))->toBe(EmailDelivery::FAILED)
        ->and(EmailDelivery::where('kind', 'invoice-issued')->value('status'))->toBe(EmailDelivery::FAILED);

    $this->actingAs($user)->get(route('subscriptions.confirmation', $subscription))
        ->assertOk()
        ->assertSee('Your Payment Has Been Received');
});

test('a school can register even when the mail server refuses', function () {
    refuseAllMail();

    $this->post(route('register'), [
        'school_name' => 'Zed Academy',
        'email' => 'admin@zed.example',
        'phone' => '08030000000',
        'address' => '12 admin zed example Avenue, Ikeja, Lagos',
        'password' => 'Very-Strong-Pass-93!',
        'password_confirmation' => 'Very-Strong-Pass-93!',
        'terms' => '1',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(School::where('name', 'Zed Academy')->count())->toBe(1)
        ->and($this->team->notifications()->where('type', SchoolRegisteredNotification::class)->count())->toBe(1);
});

test('pressing submit again cannot create a second application', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);
    $wizard = session('subscription_wizard');

    $this->actingAs($user)->post(route('subscriptions.review.store'));
    $first = Subscription::sole();

    // The same submission arriving again with the wizard still in the session:
    // a second tab, or a request retried by the browser.
    $this->actingAs($user)->withSession(['subscription_wizard' => $wizard])
        ->post(route('subscriptions.review.store'))
        ->assertRedirect(route('subscriptions.confirmation', $first))
        ->assertSessionHas('status', fn ($status) => str_contains($status, 'already been submitted'));

    expect(Subscription::count())->toBe(1)->and(Payment::count())->toBe(1)->and(SubscriptionInvoice::count())->toBe(1);
    Storage::disk('local')->assertExists(Payment::sole()->receipt_path);
});

test('going through the wizard again while a payment is under review leads back to it', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);
    $this->actingAs($user)->post(route('subscriptions.review.store'));
    $pending = Subscription::sole();

    $plan = Plan::where('key', PlanKey::Basic)->firstOrFail();
    $this->actingAs($user)->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id]);
    $this->actingAs($user)->post(route('subscriptions.students.store'), ['students_count' => 100]);
    $this->actingAs($user)->post(route('subscriptions.billing-details.store'), [
        'billing_contact_name' => 'Jane Doe', 'billing_email' => 'billing@greenfield.example',
        'billing_phone' => '08012345678', 'billing_address' => '1 School Road',
    ]);

    $this->actingAs($user)->get(route('subscriptions.payment-method'))
        ->assertRedirect(route('subscriptions.confirmation', $pending));

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => UploadFixtures::receiptPng(),
    ])->assertRedirect(route('subscriptions.confirmation', $pending));

    // Nothing stored for the refused attempt: only the first receipt exists.
    expect(Subscription::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('receipts/'.$user->school_id))->toHaveCount(1);
});

test('a school whose earlier application was rejected can apply again', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);
    $this->actingAs($user)->post(route('subscriptions.review.store'));
    Subscription::sole()->update(['status' => SubscriptionStatus::Rejected]);

    walkToReview($this, $user);
    $this->actingAs($user)->post(route('subscriptions.review.store'));

    expect(Subscription::count())->toBe(2);
});

test('a failure while saving keeps the school on the review page with everything it entered', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);

    $this->mock(SubscriptionInvoiceIssuer::class)
        ->shouldReceive('issueForSubscription')
        ->andThrow(new RuntimeException('database went away'));

    $this->actingAs($user)->post(route('subscriptions.review.store'))
        ->assertRedirect(route('subscriptions.review'))
        ->assertSessionHasErrors('subscription');

    // All or nothing: no half-saved application, and the wizard is intact so
    // pressing the button again works.
    expect(Subscription::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(session('subscription_wizard.payment_method'))->toBe('bank_transfer')
        ->and(session('subscription_wizard.receipt_path'))->not->toBeNull();

    $this->actingAs($user)->get(route('subscriptions.review'))
        ->assertOk()
        ->assertSee('We could not submit your subscription just now');
});

test('an application is never submitted pointing at a receipt that is not on file', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);
    Storage::disk('local')->delete(session('subscription_wizard.receipt_path'));

    $this->actingAs($user)->post(route('subscriptions.review.store'))
        ->assertRedirect(route('subscriptions.payment-method'))
        ->assertSessionHasErrors(['receipt' => 'We could not find the receipt you uploaded. Please upload it again; nothing has been submitted yet.']);

    expect(Subscription::count())->toBe(0);
});

test('a wizard that lost its details is sent back to the start, not to the database', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);
    $wizard = session('subscription_wizard');
    unset($wizard['amount']);

    $this->actingAs($user)->withSession(['subscription_wizard' => $wizard])
        ->post(route('subscriptions.review.store'))
        ->assertRedirect(route('subscriptions.choose-plan'))
        ->assertSessionHasErrors('subscription');

    expect(Subscription::count())->toBe(0);
});

test('a very long school name still produces a reference that fits', function () {
    $user = registeringSchoolAdmin(str_repeat('Greenfield International ', 10).'Academy');
    walkToReview($this, $user);

    $this->actingAs($user)->post(route('subscriptions.review.store'))->assertSessionHasNoErrors();

    $reference = Subscription::sole()->reference;
    expect(strlen($reference))->toBeLessThan(40)->and($reference)->toStartWith('GREENFIELDINTERNATIO-');
});

test('uploading a different receipt replaces the first one', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);
    $first = session('subscription_wizard.receipt_path');

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => UploadFixtures::receiptPng('second.png'),
    ])->assertRedirect(route('subscriptions.review'));

    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists(session('subscription_wizard.receipt_path'));
    expect(session('subscription_wizard.receipt_original_name'))->toBe('second.png');
});

test('receipts that are not really PNG, JPG or PDF are refused with a clear reason', function (Closure $file, string $message) {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => $file(),
    ])->assertSessionHasErrors(['receipt' => $message]);
})->with([
    'a script named .jpg' => [fn () => UploadFixtures::phpDisguisedAsJpeg(), 'The receipt must be a PNG, JPG or PDF file. This file is not really one of those, even if its name says it is.'],
    'a PNG named .php' => [fn () => UploadFixtures::receiptPng('receipt.php'), 'The receipt must be a PNG, JPG or PDF file.'],
    'a Word document' => [fn () => UploadedFile::fake()->create('receipt.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'), 'The receipt must be a PNG, JPG or PDF file.'],
    'larger than 5MB' => [fn () => UploadedFile::fake()->create('receipt.pdf', 6000, 'application/pdf'), 'The receipt must be 5MB or smaller. A screenshot of the receipt is usually much smaller than a photo.'],
]);

test('a receipt that did not finish uploading says so plainly', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);

    $broken = new UploadedFile(UploadFixtures::receiptPng()->getRealPath(), 'receipt.png', 'image/png', UPLOAD_ERR_INI_SIZE, true);

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), [
        'payment_method' => 'bank_transfer',
        'receipt' => $broken,
    ])->assertSessionHasErrors(['receipt' => 'Your receipt did not finish uploading. It may be larger than 5MB, or the connection dropped. Please try again, or upload a screenshot of the receipt instead of a photo.']);
});

test('the uploader\'s file name is kept short and harmless', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user, UploadFixtures::receiptPng(str_repeat('a', 300).'".png'));

    $this->actingAs($user)->post(route('subscriptions.review.store'));

    $name = Payment::sole()->receipt_original_name;
    expect(mb_strlen($name))->toBeLessThanOrEqual(150)->and($name)->toEndWith('.png')->and($name)->not->toContain('"');
});

test('the receipt and submit steps are rate limited', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);

    foreach (range(1, 10) as $attempt) {
        $this->actingAs($user)->post(route('subscriptions.payment-method.store'), ['payment_method' => 'bank_transfer']);
    }

    $this->actingAs($user)->post(route('subscriptions.payment-method.store'), ['payment_method' => 'bank_transfer'])
        ->assertStatus(429);
});

test('another school cannot open this school\'s confirmation or receipt', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);
    $this->actingAs($user)->post(route('subscriptions.review.store'));
    $subscription = Subscription::sole();

    $this->actingAs(registeringSchoolAdmin('Another School'))
        ->get(route('subscriptions.confirmation', $subscription))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('super-admin.subscriptions.receipt', $subscription))
        ->assertForbidden();
})->skip(fn () => ! Route::has('super-admin.subscriptions.receipt'), 'no receipt route');

test('a rejection is saved even when the mail server refuses', function () {
    refuseAllMail();
    $user = registeringSchoolAdmin();
    walkToReview($this, $user);
    $this->actingAs($user)->post(route('subscriptions.review.store'));
    $subscription = Subscription::sole();

    $this->actingAs($this->team)
        ->post(route('super-admin.subscriptions.reject', $subscription), ['reason' => 'Amount does not match.'])
        ->assertRedirect();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Rejected)
        ->and(EmailDelivery::where('kind', 'subscription-rejected')->value('status'))->toBe(EmailDelivery::FAILED);
});

test('the service worker never keeps a subscription or registration form for later', function () {
    $script = $this->get(route('pwa.service-worker'))->assertOk()->getContent();

    expect($script)
        ->toContain('function isOnlineOnly(pathname)')
        ->toContain('if (isOnlineOnly(url.pathname)) {')
        ->toContain(SecureRoute::uri('subscriptions.review'));
})->skip(fn () => ! Route::has('pwa.service-worker'), 'no service worker route');

test('the team can open a receipt whatever the uploader called it', function () {
    $user = registeringSchoolAdmin();
    walkToReview($this, $user, UploadFixtures::receiptPng('Reçu de paiement.png'));
    $this->actingAs($user)->post(route('subscriptions.review.store'));

    $response = $this->actingAs($this->team)->get(route('super-admin.subscriptions.receipt', Subscription::sole()));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toStartWith('inline; filename=')
        ->toContain("filename*=utf-8''Re%C3%A7u%20de%20paiement.png")
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});
