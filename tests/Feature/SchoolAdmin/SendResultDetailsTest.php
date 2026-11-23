<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\ResultCheckingPinStatus;
use App\Enums\ResultDeliveryChannel;
use App\Enums\ResultDeliveryStatus;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\Guardian;
use App\Models\ResultCheckingPin;
use App\Models\ResultDetailDelivery;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ResultDetailsNotification;
use App\Services\ResultDetails\GuardianContacts;
use App\Services\ResultDetails\ResultDetailsSender;
use App\Services\ResultTokenIssuer;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Sending students' result details (result link, token, admission number) to
 * their parents/guardians by email, WhatsApp or both.
 */
beforeEach(function () {
    Notification::fake();

    config([
        'services.whatsapp.token' => 'test-token',
        'services.whatsapp.phone_number_id' => '1234567890',
        'services.whatsapp.result_template' => 'result_details',
        'services.whatsapp.app_secret' => 'app-secret',
        'services.whatsapp.webhook_verify_token' => 'verify-me',
    ]);

    $this->school = activateSchool(School::factory()->create(['result_link_enabled' => true]), PlanKey::Basic);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->exam = Examination::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 1',
        'session' => '2026/2027',
        'term' => ExamTerm::First->value,
    ]);

    $this->ada = Student::factory()->create([
        'school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true,
        'first_name' => 'Ada', 'last_name' => 'Obi', 'admission_number' => 'AKN/001',
        'guardian_name' => 'Mrs Obi', 'guardian_email' => 'obi@example.com', 'guardian_phone' => '08031234567',
    ]);
    $this->ben = Student::factory()->create([
        'school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true,
        'first_name' => 'Ben', 'last_name' => 'Eze', 'admission_number' => 'AKN/002',
        'guardian_name' => 'Mr Eze', 'guardian_email' => 'eze@example.com', 'guardian_phone' => '+234 805 555 1234',
    ]);
    $this->noContact = Student::factory()->create([
        'school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true,
        'first_name' => 'Chi', 'last_name' => 'None', 'admission_number' => 'AKN/003',
        'guardian_name' => null, 'guardian_email' => null, 'guardian_phone' => null,
    ]);

    $issuer = app(ResultTokenIssuer::class);
    $this->tokens = collect([$this->ada, $this->ben, $this->noContact])->mapWithKeys(fn (Student $s) => [
        $s->admission_number => $issuer->issue($this->school, $s, $this->exam, $this->admin),
    ]);
});

function fakeWhatsApp(int $status = 200, array $body = ['messages' => [['id' => 'wamid.OK']]]): void
{
    Http::fake(['graph.facebook.com/*' => Http::response($body, $status)]);
}

function tokenUuid(object $test, string $admission): string
{
    return $test->tokens[$admission]['token']->uuid;
}

function plain(object $test, string $admission): string
{
    return $test->tokens[$admission]['plain'];
}

test('contacts come from the student record and linked parent accounts, validated', function () {
    $linked = Guardian::factory()->create(['school_id' => $this->school->id, 'name' => 'Aunt Ngozi', 'email' => 'ngozi@example.com', 'phone' => '0809 111 2222']);
    $linked->students()->attach($this->ada);
    $elsewhere = Guardian::factory()->create(['email' => 'stranger@example.com', 'phone' => '08070000000']);
    $elsewhere->students()->attach($this->ada);

    $this->ada->update(['guardian_email' => 'not-an-email']);

    $found = app(GuardianContacts::class)->for($this->ada->fresh());

    expect(collect($found['email'])->pluck('address')->all())->toBe(['ngozi@example.com'])
        ->and(collect($found['whatsapp'])->pluck('address')->all())->toBe(['2348031234567', '2348091112222'])
        ->and($found['invalid'])->toHaveCount(1)
        ->and($found['invalid'][0])->toContain('not-an-email');
});

test('one student is sent by email straight away, carrying only their own details', function () {
    $this->actingAs($this->admin)
        ->post(route('result-pins.details.send'), ['channel' => 'email', 'tokens' => [tokenUuid($this, 'AKN/001')]])
        ->assertRedirect();

    $row = ResultDetailDelivery::sole();

    expect($row->status)->toBe(ResultDeliveryStatus::Sent)
        ->and($row->channel)->toBe(ResultDeliveryChannel::Email)
        ->and($row->recipient)->toBe('obi@example.com')
        ->and($row->student_id)->toBe($this->ada->id);

    Notification::assertSentOnDemand(ResultDetailsNotification::class, function (ResultDetailsNotification $n, array $channels, AnonymousNotifiable $to) {
        $mail = $n->toMail($to);
        $text = collect($mail->introLines)->join("\n");

        return $to->routes['mail'] === 'obi@example.com'
            && $n->student->is($this->ada)
            && str_contains($text, plain($this, 'AKN/001'))
            && str_contains($text, 'AKN/001')
            && ! str_contains($text, plain($this, 'AKN/002'))
            && $mail->actionUrl === $this->school->resultLinkUrl();
    });

    // The token is never stored in the history.
    expect(json_encode(ResultDetailDelivery::sole()->toArray()))->not->toContain(plain($this, 'AKN/001'));
});

test('WhatsApp goes through the approved result template with that student\'s details', function () {
    fakeWhatsApp();

    $this->actingAs($this->admin)
        ->post(route('result-pins.details.send'), ['channel' => 'whatsapp', 'tokens' => [tokenUuid($this, 'AKN/002')]]);

    $row = ResultDetailDelivery::sole();
    expect($row->status)->toBe(ResultDeliveryStatus::Sent)
        ->and($row->recipient)->toBe('2348055551234')
        ->and($row->provider_message_id)->toBe('wamid.OK');

    Http::assertSent(function (HttpRequest $request) {
        $values = collect($request['template']['components'][0]['parameters'])->pluck('text')->all();

        return $request['to'] === '2348055551234'
            && $request['template']['name'] === 'result_details'
            && $values === ['Mr Eze', $this->school->name, 'Ben Eze', 'First Term 2026/2027', 'AKN/002', plain($this, 'AKN/002'), $this->school->resultLinkUrl()];
    });
});

test('a WhatsApp error is recorded as failed with the reason, never as sent', function () {
    fakeWhatsApp(400, ['error' => ['message' => 'Template name does not exist']]);

    $this->actingAs($this->admin)
        ->post(route('result-pins.details.send'), ['channel' => 'whatsapp', 'tokens' => [tokenUuid($this, 'AKN/001')]]);

    $row = ResultDetailDelivery::sole();
    expect($row->status)->toBe(ResultDeliveryStatus::Failed)
        ->and($row->error)->toBe('Template name does not exist')
        ->and($row->sent_at)->toBeNull();
});

test('without WhatsApp credentials nothing is pretended', function () {
    config(['services.whatsapp.token' => null]);
    Http::fake();

    $this->actingAs($this->admin)
        ->post(route('result-pins.details.send'), ['channel' => 'whatsapp', 'tokens' => [tokenUuid($this, 'AKN/001')]]);

    expect(ResultDetailDelivery::sole()->status)->toBe(ResultDeliveryStatus::Failed)
        ->and(ResultDetailDelivery::sole()->error)->toContain('not set up');
    Http::assertNothingSent();
});

test('bulk sending shows a confirmation summary first and sends nothing until confirmed', function () {
    $tokens = [tokenUuid($this, 'AKN/001'), tokenUuid($this, 'AKN/002'), tokenUuid($this, 'AKN/003')];

    $this->actingAs($this->admin)
        ->post(route('result-pins.details.preview'), ['channel' => 'both', 'tokens' => $tokens])
        ->assertOk()
        ->assertSee('Confirm &amp; Send', false)
        ->assertViewHas('summary', fn ($summary) => $summary['students'] === 3
            && $summary['messages'] === ['email' => 2, 'whatsapp' => 2]
            && $summary['missing'] === ['email' => 1, 'whatsapp' => 1])
        ->assertSee('Missing contact');

    expect(ResultDetailDelivery::count())->toBe(0);
    Notification::assertNothingSent();
});

test('a bulk send by both channels gives every parent only their own child\'s details', function () {
    fakeWhatsApp();

    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'both',
        'tokens' => [tokenUuid($this, 'AKN/001'), tokenUuid($this, 'AKN/002'), tokenUuid($this, 'AKN/003')],
    ]);

    expect(ResultDetailDelivery::where('status', ResultDeliveryStatus::Sent)->count())->toBe(4)
        ->and(ResultDetailDelivery::where('status', ResultDeliveryStatus::MissingContact)->count())->toBe(2)
        ->and(ResultDetailDelivery::where('student_id', $this->noContact->id)->pluck('status')->unique()->all())->toBe([ResultDeliveryStatus::MissingContact]);

    Notification::assertSentOnDemandTimes(ResultDetailsNotification::class, 2);
    Notification::assertSentOnDemand(ResultDetailsNotification::class, fn ($n, $c, $to) => $to->routes['mail'] === 'obi@example.com' && $n->token === plain($this, 'AKN/001'));
    Notification::assertSentOnDemand(ResultDetailsNotification::class, fn ($n, $c, $to) => $to->routes['mail'] === 'eze@example.com' && $n->token === plain($this, 'AKN/002'));

    Http::assertSent(fn (HttpRequest $r) => $r['to'] === '2348031234567' && in_array(plain($this, 'AKN/001'), collect($r['template']['components'][0]['parameters'])->pluck('text')->all(), true));
    Http::assertNotSent(fn (HttpRequest $r) => $r['to'] === '2348031234567' && in_array(plain($this, 'AKN/002'), collect($r['template']['components'][0]['parameters'])->pluck('text')->all(), true));
});

test('retrying sends only the failed and missing ones, never what already went', function () {
    // WhatsApp fails the first time and works after.
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'Temporary failure']], 500)
        ->whenEmpty(Http::response(['messages' => [['id' => 'wamid.RETRY']]], 200))]);

    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'both',
        'tokens' => [tokenUuid($this, 'AKN/001'), tokenUuid($this, 'AKN/003')],
    ]);

    $batch = ResultDetailDelivery::first()->batch;
    expect(ResultDetailDelivery::where('status', ResultDeliveryStatus::Sent)->count())->toBe(1)      // Ada's email
        ->and(ResultDetailDelivery::where('status', ResultDeliveryStatus::Failed)->count())->toBe(1)  // Ada's WhatsApp
        ->and(ResultDetailDelivery::where('status', ResultDeliveryStatus::MissingContact)->count())->toBe(2);

    // Chi's parent's email is added to the record.
    $this->noContact->update(['guardian_name' => 'Mrs None', 'guardian_email' => 'none@example.com']);

    $this->actingAs($this->admin)->post(route('result-pins.details.retry'), ['batch' => $batch])->assertSessionHas('status');

    expect(ResultDetailDelivery::where('student_id', $this->ada->id)->pluck('status')->unique()->values()->all())->toBe([ResultDeliveryStatus::Sent])
        ->and(ResultDetailDelivery::where('student_id', $this->noContact->id)->where('channel', 'email')->sole()->status)->toBe(ResultDeliveryStatus::Sent)
        ->and(ResultDetailDelivery::where('student_id', $this->noContact->id)->where('channel', 'whatsapp')->sole()->status)->toBe(ResultDeliveryStatus::MissingContact);

    // Ada's email went once: the retry did not send it again.
    Notification::assertSentOnDemandTimes(ResultDetailsNotification::class, 2);
    Notification::assertSentOnDemand(ResultDetailsNotification::class, fn ($n, $c, $to) => $to->routes['mail'] === 'none@example.com');
});

test('from the Results page a student without a token is issued one, and a revoked one is reported', function () {
    $fresh = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true, 'guardian_email' => 'fresh@example.com']);
    $this->tokens['AKN/002']['token']->update(['status' => ResultCheckingPinStatus::Revoked]);
    $revokedOnly = $this->ben;

    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'email',
        'examination' => $this->exam->uuid,
        'students' => [$fresh->uuid, $revokedOnly->uuid],
    ])->assertSessionHas('status', fn ($status) => str_contains($status, 'Ben Eze'));

    $issued = ResultCheckingPin::where('bound_student_id', $fresh->id)->sole();
    expect($issued->examination_id)->toBe($this->exam->id)
        ->and(ResultDetailDelivery::where('student_id', $fresh->id)->sole()->status)->toBe(ResultDeliveryStatus::Sent)
        ->and(ResultDetailDelivery::where('student_id', $revokedOnly->id)->exists())->toBeFalse()
        ->and(ResultCheckingPin::where('bound_student_id', $revokedOnly->id)->count())->toBe(1);
});

test('a message is not sent if the address was removed from the record after queueing', function () {
    $row = ResultDetailDelivery::create([
        'school_id' => $this->school->id, 'batch' => (string) Str::uuid(),
        'student_id' => $this->ada->id, 'result_checking_pin_id' => $this->tokens['AKN/001']['token']->id,
        'examination_id' => $this->exam->id, 'channel' => ResultDeliveryChannel::Email,
        'recipient' => 'old-parent@example.com', 'recipient_name' => 'Old', 'status' => ResultDeliveryStatus::Pending,
    ]);

    app(ResultDetailsSender::class)->deliver($row);

    expect($row->fresh()->status)->toBe(ResultDeliveryStatus::Failed)
        ->and($row->fresh()->error)->toContain('no longer on the student');
    Notification::assertNothingSent();
});

test('a token that no longer belongs to the student is never sent', function () {
    $row = ResultDetailDelivery::create([
        'school_id' => $this->school->id, 'batch' => (string) Str::uuid(),
        'student_id' => $this->ada->id, 'result_checking_pin_id' => $this->tokens['AKN/002']['token']->id,
        'examination_id' => $this->exam->id, 'channel' => ResultDeliveryChannel::Email,
        'recipient' => 'obi@example.com', 'recipient_name' => 'Mrs Obi', 'status' => ResultDeliveryStatus::Pending,
    ]);

    app(ResultDetailsSender::class)->deliver($row);

    expect($row->fresh()->status)->toBe(ResultDeliveryStatus::Failed);
    Notification::assertNothingSent();
});

test('WhatsApp receipts move a message to Delivered or Failed, and unsigned receipts are refused', function () {
    // Sent by the service directly: Meta's receipts arrive with nobody
    // signed in, which is what the requests below are.
    fakeWhatsApp();
    $sender = app(ResultDetailsSender::class);
    $batch = $sender->queue($this->school, collect([$this->tokens['AKN/001']['token']->load(['boundStudent', 'examination'])]), [ResultDeliveryChannel::WhatsApp], $this->admin);
    $sender->process($batch);

    $receipt = fn (string $status, array $extra = []) => ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.OK', 'status' => $status, ...$extra]]]]]]]];
    $signed = function (array $body) {
        $json = json_encode($body);

        return $this->call('POST', route('webhooks.whatsapp'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, 'app-secret'),
        ], $json);
    };

    $this->postJson(route('webhooks.whatsapp'), $receipt('delivered'))->assertForbidden();
    expect(ResultDetailDelivery::sole()->status)->toBe(ResultDeliveryStatus::Sent);

    $signed($receipt('delivered'))->assertOk();
    expect(ResultDetailDelivery::sole()->status)->toBe(ResultDeliveryStatus::Delivered);

    $signed($receipt('failed', ['errors' => [['title' => 'Re-engagement message', 'error_data' => ['details' => 'More than 24 hours have passed']]]]))->assertOk();
    expect(ResultDetailDelivery::sole()->status)->toBe(ResultDeliveryStatus::Failed)
        ->and(ResultDetailDelivery::sole()->error)->toBe('Re-engagement message: More than 24 hours have passed');

    $this->get(route('webhooks.whatsapp.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'verify-me', 'hub_challenge' => 'abc123']))
        ->assertOk()->assertSee('abc123');
    $this->get(route('webhooks.whatsapp.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'wrong', 'hub_challenge' => 'abc123']))
        ->assertForbidden();
});

test('the sending history shows date, recipient, channel and status, and offers retry for failures only', function () {
    fakeWhatsApp(400, ['error' => ['message' => 'Number not on WhatsApp']]);
    $this->actingAs($this->admin)->post(route('result-pins.details.send'), ['channel' => 'both', 'tokens' => [tokenUuid($this, 'AKN/001')]]);

    $this->actingAs($this->admin)
        ->get(route('result-pins.details.history'))
        ->assertOk()
        ->assertSee('Ada Obi')
        ->assertSee('obi@example.com')
        ->assertSee('+2348031234567')
        ->assertSee('Number not on WhatsApp')
        ->assertViewHas('counts', fn ($counts) => $counts['sent'] === 1 && $counts['failed'] === 1);
});

test('only this school\'s School Admins can send, and only this school\'s tokens', function () {
    $other = activateSchool(School::factory()->create(), PlanKey::Basic);
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $other->id]);

    $this->actingAs($otherAdmin)
        ->post(route('result-pins.details.send'), ['channel' => 'email', 'tokens' => [tokenUuid($this, 'AKN/001')]])
        ->assertSessionHasErrors('send');

    $teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    expect($this->actingAs($teacher, 'staff')
        ->post(route('result-pins.details.send'), ['channel' => 'email', 'tokens' => [tokenUuid($this, 'AKN/001')]])
        ->status())->toBeIn([302, 403]);

    expect(ResultDetailDelivery::count())->toBe(0);
    Notification::assertNothingSent();
});

test('the send buttons appear on Issued Tokens and on Results', function () {
    $this->actingAs($this->admin)->get(route('result-pins.index'))
        ->assertOk()->assertSee('Send via Email')->assertSee('Send via WhatsApp')->assertSee('Send via Both')->assertSee('Sending history');

    $this->actingAs($this->admin)->get(route('results.index', ['class' => 'JSS 1', 'session' => '2026/2027', 'term' => 'first']))
        ->assertOk()->assertSee('Send for JSS 1');
});

test('WhatsApp receipts are not held to the API\'s 60-a-minute limit', function () {
    $statuses = collect(range(1, 70))->map(fn () => $this->postJson(route('webhooks.whatsapp'), ['entry' => []])->status());

    // Unsigned, so each is refused, but none is rate limited.
    expect($statuses->unique()->values()->all())->toBe([403]);
});
