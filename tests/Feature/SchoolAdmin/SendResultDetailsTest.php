<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\ResultCheckingPinStatus;
use App\Enums\ResultDeliveryChannel;
use App\Enums\ResultDeliveryStatus;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
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
use App\Services\ResultRepository;
use App\Services\ResultTokenIssuer;
use App\Services\WhatsApp\SchoolWhatsAppVerifier;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
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
        ->and(ResultDetailDelivery::sole()->error)->toContain('Not sent automatically');
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
        ->assertOk()->assertSee('Email and WhatsApp')->assertSee('Email only')->assertSee('WhatsApp only')->assertSee('Sending history');

    $this->actingAs($this->admin)->get(route('results.index', ['class' => 'JSS 1', 'session' => '2026/2027', 'term' => 'first']))
        ->assertOk()->assertSee('Send to JSS 1 parents');
});

test('WhatsApp receipts are not held to the API\'s 60-a-minute limit', function () {
    $statuses = collect(range(1, 70))->map(fn () => $this->postJson(route('webhooks.whatsapp'), ['entry' => []])->status());

    // Unsigned, so each is refused, but none is rate limited.
    expect($statuses->unique()->values()->all())->toBe([403]);
});

test('a used-up token is replaced with a fresh one before the message goes out, every time', function () {
    $old = $this->tokens['AKN/001']['token'];
    $old->update(['status' => ResultCheckingPinStatus::Exhausted]);

    $sendForAda = fn () => $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'email', 'examination' => $this->exam->uuid, 'students' => [$this->ada->uuid],
    ]);

    $sendForAda();

    $current = ResultCheckingPin::where('bound_student_id', $this->ada->id)->latest('id')->first();
    expect($current->id)->not->toBe($old->id)
        ->and($current->accessRefusalReason())->toBeNull()
        ->and(ResultDetailDelivery::where('student_id', $this->ada->id)->sole()->result_checking_pin_id)->toBe($current->id);

    // The message carries the NEW token.
    Notification::assertSentOnDemand(ResultDetailsNotification::class, fn ($n) => $n->token === $current->plainToken());

    // Used up again later: renewed again, even though an older token in the
    // history is revoked by the first renewal.
    $current->update(['status' => ResultCheckingPinStatus::Exhausted]);
    $sendForAda();

    expect(ResultCheckingPin::where('bound_student_id', $this->ada->id)->latest('id')->first()->accessRefusalReason())->toBeNull()
        ->and(ResultCheckingPin::where('bound_student_id', $this->ada->id)->count())->toBe(3);
});

test('the admission number and token a parent receives open that child\'s result', function () {
    $this->school->update(['result_link_enabled' => true]);
    $fresh = Student::factory()->create([
        'school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true,
        'first_name' => 'Tobi', 'last_name' => 'Ade', 'admission_number' => 'TGA-2026/2027-JUR-009',
        'guardian_name' => 'Mrs Ade', 'guardian_email' => 'ade@example.com', 'guardian_phone' => '08039876543',
    ]);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $this->exam->id, 'name' => 'Mathematics', 'max_score' => 100]);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $fresh->id, 'test_score' => 30, 'exam_score' => 50, 'score' => 80]);
    app(ResultRepository::class)->publish($this->exam, $fresh, $this->admin, $this->admin->name);

    // No token yet: one is made, then sent, to the parent on Tobi's record.
    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'email', 'examination' => $this->exam->uuid, 'students' => [$fresh->uuid],
    ]);

    $sent = null;
    Notification::assertSentOnDemand(ResultDetailsNotification::class, function ($n, $channels, $to) use (&$sent) {
        $sent = ['n' => $n, 'to' => $to->routes['mail']];

        return true;
    });

    $mail = $sent['n']->toMail(new AnonymousNotifiable)->render();

    expect($sent['to'])->toBe('ade@example.com')
        ->and((string) $mail)->toContain('TGA-2026/2027-JUR-009')
        ->and((string) $mail)->toContain($sent['n']->token)
        ->and($sent['n']->forWhatsApp()['text'])->toContain('TGA-2026/2027-JUR-009')->toContain($sent['n']->token);

    // The parent, logged out, follows the link with what the message gave them.
    auth()->logout();
    $link = parse_url($sent['n']->resultLink, PHP_URL_PATH);

    $this->post($link.'/identify', ['admission_number' => 'TGA-2026/2027-JUR-009'])->assertRedirect();
    $result = $this->post($link, ['code' => $sent['n']->token]);
    $result->assertRedirect();

    $this->get($result->headers->get('Location'))->assertOk()->assertSee('Tobi')->assertSee('Mathematics');
});

test('the confirmation warns, by name, about pupils with no result to see yet', function () {
    // The fixture's pupils have tokens but no marks.
    $this->actingAs($this->admin)->post(route('result-pins.details.preview'), [
        'channel' => 'email', 'examination' => $this->exam->uuid, 'all' => 1,
    ])->assertOk()
        ->assertSee('have no result for their parents to see yet')
        ->assertSee('Ada Obi');
});

test('the main send button sends by email and WhatsApp at once, to every parent found', function () {
    $this->actingAs($this->admin)->get(route('results.index', ['class' => 'JSS 1', 'session' => '2026/2027', 'term' => 'first']))
        ->assertOk()
        ->assertSee('Send to JSS 1 parents')
        ->assertSee('name="channel" value="both"', false);

    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'both', 'examination' => $this->exam->uuid, 'students' => [$this->ada->uuid, $this->ben->uuid],
    ])->assertRedirect();

    // Each child's own parent, on both channels.
    expect(ResultDetailDelivery::where('student_id', $this->ada->id)->pluck('channel')->map->value->sort()->values()->all())->toBe(['email', 'whatsapp'])
        ->and(ResultDetailDelivery::where('student_id', $this->ada->id)->where('channel', 'email')->value('recipient'))->toBe('obi@example.com')
        ->and(ResultDetailDelivery::where('student_id', $this->ben->id)->where('channel', 'email')->value('recipient'))->toBe('eze@example.com');
});

test('without WhatsApp connected, each parent\'s message can be sent by hand, already typed', function () {
    config(['services.whatsapp.token' => null]);

    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'both', 'examination' => $this->exam->uuid, 'students' => [$this->ada->uuid],
    ]);

    $whatsApp = ResultDetailDelivery::where('student_id', $this->ada->id)->where('channel', 'whatsapp')->sole();
    expect($whatsApp->status)->toBe(ResultDeliveryStatus::Failed);

    $this->actingAs($this->admin)->get(route('result-pins.details.history'))
        ->assertOk()
        ->assertSee('Send on WhatsApp')
        ->assertSee(route('result-pins.details.whatsapp', $whatsApp), false);

    $response = $this->actingAs($this->admin)->post(route('result-pins.details.whatsapp', $whatsApp));

    $location = $response->headers->get('Location');
    $text = rawurldecode(Str::after($location, '?text='));
    $token = ResultCheckingPin::where('bound_student_id', $this->ada->id)->latest('id')->first();

    expect($location)->toStartWith('https://wa.me/2348031234567?text=')
        ->and($text)->toContain($this->ada->admission_number)
        ->and($text)->toContain($token->plainToken())
        ->and($whatsApp->fresh()->status)->toBe(ResultDeliveryStatus::Sent);
});

test('another school\'s message cannot be opened for sending by hand', function () {
    config(['services.whatsapp.token' => null]);
    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'whatsapp', 'examination' => $this->exam->uuid, 'students' => [$this->ada->uuid],
    ]);
    $whatsApp = ResultDetailDelivery::where('student_id', $this->ada->id)->sole();

    $otherSchool = School::factory()->create();
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $otherSchool->id]);
    activateSchool($otherSchool);

    $this->actingAs($otherAdmin)->post(route('result-pins.details.whatsapp', $whatsApp))->assertForbidden();
});

// ---------------------------------------------------------------------------
// The school's own registered WhatsApp Business number
// ---------------------------------------------------------------------------

function connectSchoolWhatsApp(object $test): void
{
    // Meta: a GET checks the number, a POST sends a message.
    Http::fake(['graph.facebook.com/*' => fn ($request) => $request->method() === 'GET'
        ? Http::response(['display_phone_number' => '+234 814 127 9663', 'verified_name' => 'Glory Academy', 'id' => '555000111'])
        : Http::response(['messages' => [['id' => 'wamid.SCHOOL']]])]);

    $test->actingAs($test->admin)->put(route('settings.whatsapp.update'), [
        'phone_number_id' => '555000111',
        'access_token' => 'EAAG-school-own-token-0123456789',
        'result_template' => 'school_results',
        'template_language' => 'en',
    ])->assertRedirect(route('settings.whatsapp'));
}

test('a school connects its own WhatsApp number, checked with Meta first, the token kept encrypted', function () {
    connectSchoolWhatsApp($this);

    $account = $this->school->fresh()->whatsAppAccount;

    expect($account->display_number)->toBe('+234 814 127 9663')
        ->and($account->access_token)->toBe('EAAG-school-own-token-0123456789')
        ->and(DB::table('school_whatsapp_accounts')->value('access_token'))->not->toContain('EAAG');

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_contains($request->url(), '/555000111')
        && $request->hasHeader('Authorization', 'Bearer EAAG-school-own-token-0123456789'));

    $this->actingAs($this->admin)->get(route('settings.whatsapp'))
        ->assertOk()
        ->assertSee('Result details go out from +234 814 127 9663 (Glory Academy)')
        ->assertDontSee('EAAG-school-own-token-0123456789');
});

test('details Meta does not accept are not saved', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 401)]);

    $this->actingAs($this->admin)->put(route('settings.whatsapp.update'), [
        'phone_number_id' => '555000111',
        'access_token' => 'EAAG-wrong-token-0123456789-xyz',
        'result_template' => 'school_results',
        'template_language' => 'en',
    ])->assertSessionHasErrors(['access_token']);

    expect($this->school->fresh()->whatsAppAccount)->toBeNull();
});

test('result details go from the school\'s own number, with its own template, automatically', function () {
    // No platform number at all: only the school's.
    config(['services.whatsapp.token' => null, 'services.whatsapp.phone_number_id' => null]);
    connectSchoolWhatsApp($this);

    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'whatsapp', 'examination' => $this->exam->uuid, 'students' => [$this->ada->uuid],
    ]);

    $row = ResultDetailDelivery::where('student_id', $this->ada->id)->sole();

    expect($row->status)->toBe(ResultDeliveryStatus::Sent)
        ->and($row->provider_message_id)->toBe('wamid.SCHOOL');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/555000111/messages')
        && $request->hasHeader('Authorization', 'Bearer EAAG-school-own-token-0123456789')
        && $request['to'] === '2348031234567'
        && $request['template']['name'] === 'school_results'
        && in_array('AKN/001', array_column($request['template']['components'][0]['parameters'], 'text'), true));
});

test('one school\'s WhatsApp number never sends for another school', function () {
    config(['services.whatsapp.token' => null, 'services.whatsapp.phone_number_id' => null]);
    connectSchoolWhatsApp($this);

    $other = activateSchool(School::factory()->create(['result_link_enabled' => true]), PlanKey::Basic);

    expect(app(WhatsAppGateway::class)->forSchool($other)->isConfigured())->toBeFalse()
        ->and(app(WhatsAppGateway::class)->forSchool($this->school->fresh())->isConfigured())->toBeTrue();
});

test('the School Admin can send themselves a test message, and disconnect', function () {
    connectSchoolWhatsApp($this);

    $this->actingAs($this->admin)->post(route('settings.whatsapp.test'), ['test_phone' => '0803 123 4567'])
        ->assertSessionHas('status', 'Test message sent to +2348031234567. Check that phone.');

    $this->actingAs($this->admin)->delete(route('settings.whatsapp.destroy'))->assertRedirect(route('settings.whatsapp'));

    expect($this->school->fresh()->whatsAppAccount)->toBeNull();
});

// ---------------------------------------------------------------------------
// The school's WhatsApp number, ordinary or Business, verified by a code
// ---------------------------------------------------------------------------

/** Fake Meta, keeping each code sent so the test can type it back. */
function fakeCodeSending(object $test, int $status = 200): void
{
    config(['services.whatsapp.verify_template' => 'verify_code']);
    $test->sentCodes = [];

    Http::fake(['graph.facebook.com/*' => function ($request) use ($test, $status) {
        if (($request['template']['name'] ?? null) === 'verify_code') {
            $test->sentCodes[] = $request['template']['components'][0]['parameters'][0]['text'];
        }

        return $status === 200
            ? Http::response(['messages' => [['id' => 'wamid.CODE'.count($test->sentCodes)]]])
            : Http::response(['error' => ['message' => 'Invalid parameter']], $status);
    }]);
}

test('a school verifies its WhatsApp number, ordinary or Business, by a code sent to it on WhatsApp', function () {
    fakeCodeSending($this);

    $this->actingAs($this->admin)->post(route('settings.whatsapp.number.code'), ['whatsapp_number' => '0803 123 4567'])
        ->assertRedirect(route('settings.whatsapp'))
        ->assertSessionHas('status', 'We sent a 6-digit code to +234 803 123 4567 on WhatsApp. Type it below.');

    expect($this->sentCodes)->toHaveCount(1)->and($this->sentCodes[0])->toMatch('/^\d{6}$/');

    Http::assertSent(fn ($request) => $request['to'] === '2348031234567'
        && $request['template']['name'] === 'verify_code'
        && $request['template']['components'][1]['parameters'][0]['text'] === $this->sentCodes[0]);

    $this->actingAs($this->admin)->get(route('settings.whatsapp'))
        ->assertOk()
        ->assertSee('Enter the 6-digit code sent to +234 803 123 4567 on WhatsApp')
        ->assertDontSee($this->sentCodes[0]);

    $wrong = $this->sentCodes[0] === '111111' ? '222222' : '111111';
    $this->actingAs($this->admin)->post(route('settings.whatsapp.number.verify'), ['code' => $wrong])
        ->assertSessionHasErrors(['code' => 'That code is not right. 4 tries left.']);
    expect($this->school->fresh()->verifiedWhatsApp())->toBeNull();

    $this->actingAs($this->admin)->post(route('settings.whatsapp.number.verify'), ['code' => $this->sentCodes[0]])
        ->assertRedirect(route('settings.whatsapp'))
        ->assertSessionHasNoErrors();

    $school = $this->school->fresh();
    expect($school->verifiedWhatsApp())->toBe('2348031234567')
        ->and($school->whatsapp_number)->toBe('+234 803 123 4567');

    $this->actingAs($this->admin)->get(route('settings.whatsapp'))->assertSee('Verified: +234 803 123 4567');

    // Changing the number afterwards leaves it unverified until it is checked again.
    $school->update(['whatsapp_number' => '0809 999 0000']);
    expect($school->fresh()->verifiedWhatsApp())->toBeNull();
});

test('five wrong codes and a new one is needed', function () {
    fakeCodeSending($this);
    $this->actingAs($this->admin)->post(route('settings.whatsapp.number.code'), ['whatsapp_number' => '08031234567']);
    $wrong = $this->sentCodes[0] === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $ignored) {
        $this->actingAs($this->admin)->post(route('settings.whatsapp.number.verify'), ['code' => $wrong]);
    }

    $this->actingAs($this->admin)->post(route('settings.whatsapp.number.verify'), ['code' => $this->sentCodes[0]])
        ->assertSessionHasErrors(['code' => 'Too many wrong codes. Send a new one.']);

    expect($this->school->fresh()->verifiedWhatsApp())->toBeNull();
});

test('a number that is not a whole number, or a code WhatsApp refuses, is reported', function () {
    fakeCodeSending($this, 400);

    $this->actingAs($this->admin)->post(route('settings.whatsapp.number.code'), ['whatsapp_number' => '0803'])
        ->assertSessionHasErrors('whatsapp_number');

    $this->actingAs($this->admin)->post(route('settings.whatsapp.number.code'), ['whatsapp_number' => '08031234567'])
        ->assertSessionHasErrors(['whatsapp_number' => 'The code could not be sent to +234 803 123 4567: Invalid parameter']);
});

test('a code WhatsApp could not deliver says the number may not be on WhatsApp', function () {
    fakeCodeSending($this);
    $this->actingAs($this->admin)->post(route('settings.whatsapp.number.code'), ['whatsapp_number' => '08031234567']);

    app(SchoolWhatsAppVerifier::class)->receipt('wamid.CODE1', 'failed', 'Message undeliverable');

    $this->actingAs($this->admin)->get(route('settings.whatsapp'))
        ->assertSee('WhatsApp could not deliver the code (Message undeliverable). Check that this number is registered on WhatsApp', false);
});

test('without AkademicNest\'s verification template, the page explains sending from the school\'s own phone', function () {
    config(['services.whatsapp.verify_template' => null]);

    $this->actingAs($this->admin)->get(route('settings.whatsapp'))
        ->assertSee('Result details go to parents from <strong>your own phone</strong>, free', false)
        ->assertDontSee('Send code');

    $this->actingAs($this->admin)->post(route('settings.whatsapp.number.code'), ['whatsapp_number' => '08031234567'])
        ->assertSessionHasErrors('whatsapp_number');
});

test('parents\' result messages carry the school\'s verified WhatsApp number', function () {
    $this->school->forceFill([
        'whatsapp_number' => '0814 127 9663',
        'whatsapp_verified_number' => '2348141279663',
        'whatsapp_verified_at' => now(),
    ])->save();
    fakeWhatsApp();

    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'whatsapp', 'examination' => $this->exam->uuid, 'students' => [$this->ada->uuid],
    ]);

    Http::assertSent(fn ($request) => $request['to'] === '2348031234567'
        && in_array($this->school->name.' (WhatsApp +234 814 127 9663)', array_column($request['template']['components'][0]['parameters'], 'text'), true));
});

test('an unverified school number is never put on a parent\'s message', function () {
    $this->school->forceFill(['whatsapp_number' => '0814 127 9663'])->save();
    fakeWhatsApp();

    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'whatsapp', 'examination' => $this->exam->uuid, 'students' => [$this->ada->uuid],
    ]);

    Http::assertSent(fn ($request) => in_array((string) $this->school->name, array_column($request['template']['components'][0]['parameters'], 'text'), true));
    Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), '9663'));
});

// ---------------------------------------------------------------------------
// Free: from the School Admin's own phone, one parent after another
// ---------------------------------------------------------------------------

test('sending by WhatsApp alone, without automatic sending, goes straight to one-after-another sending', function () {
    config(['services.whatsapp.token' => null]);

    $response = $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'whatsapp', 'examination' => $this->exam->uuid, 'students' => [$this->ada->uuid, $this->ben->uuid],
    ]);

    $batch = ResultDetailDelivery::where('student_id', $this->ada->id)->value('batch');
    $response->assertRedirect(route('result-pins.details.whatsapp-queue', ['batch' => $batch]));

    $ada = ResultDetailDelivery::where('student_id', $this->ada->id)->sole();
    $token = ResultCheckingPin::where('bound_student_id', $this->ada->id)->latest('id')->first();

    $this->actingAs($this->admin)->get(route('result-pins.details.whatsapp-queue', ['batch' => $batch]))
        ->assertOk()
        ->assertSee('Open WhatsApp')
        ->assertSee($this->ada->fullName())
        ->assertSee(str_replace('/', '\\/', route('result-pins.details.whatsapp', $ada)), false)
        // The tokens stay off the page; each goes only into its own WhatsApp message.
        ->assertDontSee($token->plainToken());

    // Each tap: marked sent, and WhatsApp opens with the message typed.
    $this->actingAs($this->admin)->post(route('result-pins.details.whatsapp', $ada))
        ->assertRedirect();
    expect($ada->fresh()->status)->toBe(ResultDeliveryStatus::Sent);

    $page = $this->actingAs($this->admin)->get(route('result-pins.details.whatsapp-queue', ['batch' => $batch]))->getContent();
    preg_match('/rows: (\[.*?\]),\n/s', $page, $m);
    $rows = collect(json_decode(html_entity_decode($m[1]), true));

    expect($rows)->toHaveCount(ResultDetailDelivery::where('batch', $batch)->where('channel', 'whatsapp')->whereNotNull('recipient')->count())
        ->and($rows->firstWhere('id', $ada->uuid)['done'])->toBeTrue()
        ->and($rows->where('done', false)->every(fn ($r) => $r['problem'] === null))->toBeTrue();
});

test('the sending history offers Send to all on WhatsApp for what has not gone', function () {
    config(['services.whatsapp.token' => null]);

    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'both', 'examination' => $this->exam->uuid, 'students' => [$this->ada->uuid],
    ])->assertSessionHas('status', fn ($status) => str_contains($status, 'tap Send to all on WhatsApp'));

    $this->actingAs($this->admin)->get(route('result-pins.details.history'))
        ->assertSee('Send to all on WhatsApp')
        ->assertSee(route('result-pins.details.whatsapp-queue'), false);
});

test('one school never sees another school\'s parents to send to', function () {
    config(['services.whatsapp.token' => null]);
    $this->actingAs($this->admin)->post(route('result-pins.details.send'), [
        'channel' => 'whatsapp', 'examination' => $this->exam->uuid, 'students' => [$this->ada->uuid],
    ]);

    $otherSchool = activateSchool(School::factory()->create(), PlanKey::Basic);
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $otherSchool->id]);

    $this->actingAs($otherAdmin)->get(route('result-pins.details.whatsapp-queue'))
        ->assertOk()
        ->assertSee('No WhatsApp messages are waiting to be sent.')
        ->assertDontSee($this->ada->fullName());
});
