<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Staff\Cbt\DocumentUploadController;
use App\Http\Controllers\SuperAdmin\CbtDocumentUploadController;
use App\Jobs\ProcessCbtDocumentUpload;
use App\Jobs\ProcessCbtTestDocumentUpload;
use App\Models\CbtDocumentUpload;
use App\Models\CbtTest;
use App\Models\CbtTestDocumentUpload;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CbtExtractionAvailability;
use App\Services\QueueWorkerHealth;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->school = School::factory()->create();

    $plan = Plan::firstOrCreate(
        ['key' => PlanKey::Standard],
        Plan::factory()->make(['key' => PlanKey::Standard])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $this->school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $this->teacher = Staff::factory()->create([
        'school_id' => $this->school->id,
        'role' => StaffRole::Teacher,
    ]);
});

// -----------------------------------------------------------------------------
// The honesty rule: a percentage only exists where one is actually known.
// -----------------------------------------------------------------------------

test('processing reports no percentage, because none is known', function () {
    // This is the whole point. The extractor gives no signal of how far
    // through a document it is, so a number here would be invented - and an
    // invented number that reaches 100% while the work continues is what made
    // a slow upload look like a broken one.
    expect(CbtDocumentUploadStatus::Processing->progressPercent())->toBeNull();
});

test('the states that are genuinely known report their percentage', function () {
    expect(CbtDocumentUploadStatus::Pending->progressPercent())->toBe(0)
        ->and(CbtDocumentUploadStatus::Completed->progressPercent())->toBe(100)
        ->and(CbtDocumentUploadStatus::Failed->progressPercent())->toBe(100)
        ->and(CbtDocumentUploadStatus::NeedsMapping->progressPercent())->toBe(100);
});

test('only pending and processing count as still running', function () {
    expect(CbtDocumentUploadStatus::Pending->isInProgress())->toBeTrue()
        ->and(CbtDocumentUploadStatus::Processing->isInProgress())->toBeTrue()
        ->and(CbtDocumentUploadStatus::Completed->isInProgress())->toBeFalse()
        ->and(CbtDocumentUploadStatus::Failed->isInProgress())->toBeFalse()
        ->and(CbtDocumentUploadStatus::NeedsMapping->isInProgress())->toBeFalse();
});

// -----------------------------------------------------------------------------
// Detecting the root cause: a queue nothing is working.
// -----------------------------------------------------------------------------

test('an empty queue counts as healthy', function () {
    config(['queue.default' => 'database']);

    expect(app(QueueWorkerHealth::class)->isRunning())->toBeTrue();
});

test('a job waiting far longer than any worker would take means nothing is running', function () {
    config(['queue.default' => 'database']);

    // Exactly the state the live database was found in: jobs sitting unclaimed
    // for weeks, which is why uploads never finished.
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subHour()->timestamp,
        'created_at' => now()->subHour()->timestamp,
    ]);

    expect(app(QueueWorkerHealth::class)->isRunning())->toBeFalse();
});

test('a job queued moments ago is not mistaken for a stalled queue', function () {
    config(['queue.default' => 'database']);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    expect(app(QueueWorkerHealth::class)->isRunning())->toBeTrue();
});

test('a queue we cannot inspect is assumed healthy rather than warned about', function () {
    config(['queue.default' => 'sync']);
    app(QueueWorkerHealth::class)->forget();

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subDay()->timestamp,
        'created_at' => now()->subDay()->timestamp,
    ]);

    expect(app(QueueWorkerHealth::class)->isRunning())->toBeTrue();
});

// -----------------------------------------------------------------------------
// Super Admin: the XHR upload, the status endpoint, and the way back.
// -----------------------------------------------------------------------------

test('an XHR upload answers with the URLs the progress bar needs', function () {
    Bus::fake();
    Storage::fake('local');

    $response = $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->postJson(route('super-admin.cbt.uploads.store'), [
            'file' => UploadedFile::fake()->create('paper.pdf', 100, 'application/pdf'),
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['status_url', 'redirect_url']);

    Bus::assertDispatched(ProcessCbtDocumentUpload::class);
});

test('a plain form upload still redirects, so the page works without JavaScript', function () {
    Bus::fake();
    Storage::fake('local');

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->post(route('super-admin.cbt.uploads.store'), [
            'file' => UploadedFile::fake()->create('paper.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();
});

test('the status endpoint reports a document that is still being read', function () {
    $upload = CbtDocumentUpload::factory()->create(['status' => CbtDocumentUploadStatus::Processing]);

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->getJson(route('super-admin.cbt.uploads.status', $upload))
        ->assertOk()
        ->assertJson([
            'status' => 'processing',
            'in_progress' => true,
            'percent' => null,
            'stalled' => false,
        ]);
});

test('the status endpoint says so when nothing is working the queue', function () {
    config(['queue.default' => 'database']);
    app(QueueWorkerHealth::class)->forget();

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subHour()->timestamp,
        'created_at' => now()->subHour()->timestamp,
    ]);

    $upload = CbtDocumentUpload::factory()->create(['status' => CbtDocumentUploadStatus::Pending]);

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->getJson(route('super-admin.cbt.uploads.status', $upload))
        ->assertOk()
        ->assertJson(['stalled' => true, 'in_progress' => true])
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Nothing is processing jobs'));
});

test('the status endpoint reports a finished document as no longer in progress', function () {
    $upload = CbtDocumentUpload::factory()->create(['status' => CbtDocumentUploadStatus::Completed]);

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->getJson(route('super-admin.cbt.uploads.status', $upload))
        ->assertOk()
        ->assertJson([
            'status' => 'completed',
            'in_progress' => false,
            'percent' => 100,
        ]);
});

test('retrying a failed extraction re-queues it without a fresh upload', function () {
    Bus::fake();

    $upload = CbtDocumentUpload::factory()->create([
        'status' => CbtDocumentUploadStatus::Failed,
        'error_message' => 'Could not read the document.',
    ]);

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->post(route('super-admin.cbt.uploads.retry', $upload))
        ->assertRedirect();

    $upload->refresh();

    expect($upload->status)->toBe(CbtDocumentUploadStatus::Pending)
        ->and($upload->error_message)->toBeNull();

    Bus::assertDispatched(ProcessCbtDocumentUpload::class, fn ($job) => $job->upload->is($upload));
});

test('the upload page no longer reloads itself on a timer', function () {
    $upload = CbtDocumentUpload::factory()->create(['status' => CbtDocumentUploadStatus::Processing]);

    // The old implementation refreshed the whole page every five seconds,
    // which discarded scroll position and re-ran every query behind it.
    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->get(route('super-admin.cbt.uploads.show', $upload))
        ->assertOk()
        ->assertDontSee('http-equiv="refresh"', false);
});

// -----------------------------------------------------------------------------
// The teacher's side gets the same treatment.
// -----------------------------------------------------------------------------

test('a teacher uploading over XHR gets the URLs the progress bar needs', function () {
    Bus::fake();
    Storage::fake('local');

    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->postJson(route('staff.cbt.tests.uploads.store', [$this->school, $test]), [
            'file' => UploadedFile::fake()->create('questions.pdf', 100, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonStructure(['status_url', 'redirect_url']);

    Bus::assertDispatched(ProcessCbtTestDocumentUpload::class);
});

test('a teacher can poll the status of their own upload', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
    ]);

    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $this->teacher->id,
        'status' => CbtDocumentUploadStatus::Processing,
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->getJson(route('staff.cbt.tests.uploads.status', [$this->school, $test, $upload]))
        ->assertOk()
        ->assertJson(['status' => 'processing', 'percent' => null]);
});

test('a teacher cannot poll another teacher\'s upload', function () {
    $otherTeacher = Staff::factory()->create([
        'school_id' => $this->school->id,
        'role' => StaffRole::Teacher,
    ]);

    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $otherTeacher->id,
    ]);

    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $otherTeacher->id,
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->getJson(route('staff.cbt.tests.uploads.status', [$this->school, $test, $upload]))
        ->assertForbidden();
});

test('a teacher cannot retry another teacher\'s upload', function () {
    Bus::fake();

    $otherTeacher = Staff::factory()->create([
        'school_id' => $this->school->id,
        'role' => StaffRole::Teacher,
    ]);

    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $otherTeacher->id,
    ]);

    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $otherTeacher->id,
        'status' => CbtDocumentUploadStatus::Failed,
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.uploads.retry', [$this->school, $test, $upload]))
        ->assertForbidden();

    Bus::assertNotDispatched(ProcessCbtTestDocumentUpload::class);
});

// -----------------------------------------------------------------------------
// The size limit the form quotes has to be the one the validator enforces.
// -----------------------------------------------------------------------------

test('a document at the stated limit is accepted', function () {
    Bus::fake();
    Storage::fake('local');

    // 21MB was previously rejected: the form said one number and the validator
    // enforced a smaller one, so a file the user was told was fine failed.
    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->postJson(route('super-admin.cbt.uploads.store'), [
            'file' => UploadedFile::fake()->create('paper.pdf', 21 * 1024, 'application/pdf'),
        ])
        ->assertCreated();
});

test('a document past the limit is rejected with a message about its size', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->postJson(route('super-admin.cbt.uploads.store'), [
            'file' => UploadedFile::fake()->create('paper.pdf', 25 * 1024, 'application/pdf'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

test('both upload paths enforce the same limit', function () {
    $superAdmin = CbtDocumentUploadController::maxUploadLabel();
    $staff = DocumentUploadController::maxUploadLabel();

    expect($superAdmin)->toBe($staff)->toBe('21MB');
});

// -----------------------------------------------------------------------------
// The second cause: extraction credentials that were never configured.
//
// With no worker running, a missing API key never got the chance to throw, so
// nothing was written to error_message either. The two faults hid each other,
// and the only symptom was a document that stayed Pending forever.
//
// The API key half of that is gone: extraction runs locally now and needs no
// key at all. A queue worker is the one prerequisite that remains.
// -----------------------------------------------------------------------------

test('extraction needs no API key at all', function () {
    // The dependency this used to warn about is gone: no key, no credit, no
    // network. Only a queue worker is still required.
    config(['services.anthropic.key' => null, 'queue.default' => 'database']);
    app(QueueWorkerHealth::class)->forget();

    expect(app(CbtExtractionAvailability::class)->warning())->toBeNull()
        ->and(app(CbtExtractionAvailability::class)->isReady())->toBeTrue();
});

test('a stalled queue is reported', function () {
    config(['queue.default' => 'database']);
    app(QueueWorkerHealth::class)->forget();

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subHour()->timestamp,
        'created_at' => now()->subHour()->timestamp,
    ]);

    $warning = app(CbtExtractionAvailability::class)->warning();

    expect($warning)->toContain('queue:work')
        ->and($warning)->toContain('stored safely');
});

test('the Super Admin upload page warns when nothing is working the queue', function () {
    config(['queue.default' => 'database']);
    app(QueueWorkerHealth::class)->forget();

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subHour()->timestamp,
        'created_at' => now()->subHour()->timestamp,
    ]);

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->get(route('super-admin.cbt.uploads.index'))
        ->assertOk()
        ->assertSee('queue:work');
});

test('a healthy server shows no warning on the upload page', function () {
    config(['queue.default' => 'database']);
    app(QueueWorkerHealth::class)->forget();

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->get(route('super-admin.cbt.uploads.index'))
        ->assertOk()
        ->assertDontSee('queue:work');
});
