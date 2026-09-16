<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\CbtTestStatus;
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
use App\Models\CbtTestAttempt;
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
    // through a document it is, so a number here would be invented, and an
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

test('THE BUG: with no worker running, a waiting upload is read when its status is checked', function () {
    // Production had no queue worker, so an upload sat at "Extraction has not
    // started" for ever and "Queue it again" only wrote another job nothing
    // would run. Now the page reads it itself.
    Bus::fake();
    config(['queue.default' => 'database']);

    $upload = CbtDocumentUpload::factory()->create(['status' => CbtDocumentUploadStatus::Pending]);
    $upload->forceFill(['updated_at' => now()->subMinute()])->saveQuietly();

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->getJson(route('super-admin.cbt.uploads.status', $upload))
        ->assertOk();

    Bus::assertDispatchedSync(ProcessCbtDocumentUpload::class, fn ($job) => $job->upload->is($upload));
});

test('an upload still waiting long after it was tried is reported as stalled', function () {
    Bus::fake();
    config(['queue.default' => 'database']);

    $upload = CbtDocumentUpload::factory()->create(['status' => CbtDocumentUploadStatus::Pending]);
    $upload->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->getJson(route('super-admin.cbt.uploads.status', $upload))
        ->assertOk()
        ->assertJson(['stalled' => true, 'in_progress' => true]);
});

test('with no worker running, a new upload is read straight after the page is sent', function () {
    Bus::fake();
    Storage::fake('local');
    config(['queue.default' => 'database']);

    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);

    $this->actingAs($this->teacher, 'staff')
        ->postJson(route('staff.cbt.tests.uploads.store', [$this->school, $test]), [
            'file' => UploadedFile::fake()->create('questions.pdf', 100, 'application/pdf'),
        ])
        ->assertCreated();

    Bus::assertDispatchedAfterResponse(ProcessCbtTestDocumentUpload::class);
});

test('with a worker running, a new upload goes to the queue', function () {
    Bus::fake();
    Storage::fake('local');
    config(['queue.default' => 'database']);
    QueueWorkerHealth::heartbeat();

    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);

    $this->actingAs($this->teacher, 'staff')
        ->postJson(route('staff.cbt.tests.uploads.store', [$this->school, $test]), [
            'file' => UploadedFile::fake()->create('questions.pdf', 100, 'application/pdf'),
        ])
        ->assertCreated();

    Bus::assertDispatched(ProcessCbtTestDocumentUpload::class);
    Bus::assertNotDispatchedAfterResponse(ProcessCbtTestDocumentUpload::class);
});

test('an upload is actually read with no worker at all, end to end', function () {
    Storage::fake('local');
    config(['queue.default' => 'database']);

    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);

    $this->actingAs($this->teacher, 'staff')
        ->postJson(route('staff.cbt.tests.uploads.store', [$this->school, $test]), [
            'file' => UploadedFile::fake()->create('questions.pdf', 100, 'application/pdf'),
        ])
        ->assertCreated();

    // Not a readable paper, so it fails, but it was read: nothing is left
    // waiting for a worker that does not exist.
    expect(CbtTestDocumentUpload::firstOrFail()->status)->not->toBe(CbtDocumentUploadStatus::Pending);
});

test('an upload stuck reading far longer than any document takes is released to try again', function () {
    config(['queue.default' => 'database']);

    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);
    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $this->teacher->id,
        'status' => CbtDocumentUploadStatus::Processing,
    ]);
    // Longer than a sixty-page two-column compilation takes to read, which is
    // what the runner's own limit is set against.
    $upload->forceFill(['updated_at' => now()->subMinutes(40)])->saveQuietly();

    $this->actingAs($this->teacher, 'staff')
        ->getJson(route('staff.cbt.tests.uploads.status', [$this->school, $test, $upload]))
        ->assertOk()
        ->assertJson(['status' => 'failed', 'in_progress' => false])
        ->assertJsonPath('error', fn (string $error) => str_contains($error, 'interrupted'));
});

test('a job a worker picks up after the page already read the paper does nothing', function () {
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);
    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $this->teacher->id,
        'status' => CbtDocumentUploadStatus::Completed,
    ]);

    app()->call([new ProcessCbtTestDocumentUpload($upload), 'handle']);

    expect($upload->fresh()->status)->toBe(CbtDocumentUploadStatus::Completed);
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
    config(['queue.default' => 'database']);
    app(QueueWorkerHealth::class)->forget();

    expect(app(CbtExtractionAvailability::class)->warning())->toBeNull()
        ->and(app(CbtExtractionAvailability::class)->isReady())->toBeTrue();
});

test('no worker is no longer a reason to warn before uploading', function () {
    stallTheQueue();

    expect(app(CbtExtractionAvailability::class)->warning(canOperateTheServer: true))->toBeNull()
        ->and(app(CbtExtractionAvailability::class)->warning())->toBeNull()
        ->and(app(CbtExtractionAvailability::class)->isReady())->toBeTrue();
});

test('the Super Admin upload page does not warn when no worker is running', function () {
    stallTheQueue();

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->get(route('super-admin.cbt.uploads.index'))
        ->assertOk()
        ->assertDontSee('is not running');
});

test('a healthy server shows no warning on the upload page', function () {
    config(['queue.default' => 'database']);
    app(QueueWorkerHealth::class)->forget();

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->get(route('super-admin.cbt.uploads.index'))
        ->assertOk()
        ->assertDontSee('queue:work');
});

// -----------------------------------------------------------------------------
// One stalled queue, two audiences
// -----------------------------------------------------------------------------

/**
 * Put the queue into a genuinely stalled state: the database driver, a job
 * that has sat unclaimed long enough to be past the startup grace, and no
 * worker heartbeat.
 */
function stallTheQueue(): void
{
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
}

test('THE BUG: a teacher is not handed a shell command', function () {
    // A teacher cannot start a queue worker and should never be shown
    // "php artisan queue:work". The panel printed it to everyone, so a teacher
    // whose upload was waiting was given an instruction they could not act on
    // and no idea what to do instead.
    stallTheQueue();

    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);
    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    $response = $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.cbt.tests.uploads.show', [$this->school, $test, $upload]))
        ->assertOk();

    $response->assertDontSee('php artisan queue:work')
        ->assertDontSee('composer run dev')
        // What they are told instead: the document is safe, and who to tell.
        ->assertSee('You do not need to upload it again', false)
        ->assertSee('let the AkademicNest Team know', false);
});

test('but the AkademicNest Team is, because they can act on it', function () {
    stallTheQueue();

    $upload = CbtDocumentUpload::factory()->create(['status' => CbtDocumentUploadStatus::Pending]);

    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]))
        ->get(route('super-admin.cbt.uploads.show', $upload))
        ->assertOk()
        ->assertSee('php artisan queue:work', false)
        ->assertSee('composer run dev', false);
});

test('both are told the document is safe and offered a retry', function () {
    stallTheQueue();

    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);
    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.cbt.tests.uploads.show', [$this->school, $test, $upload]))
        ->assertOk()
        ->assertSee('Nothing has been lost', false)
        ->assertSee('Try again', false);
});

test('the panel shows no shell command unless it is told to', function () {
    // The default is the safe answer: a caller that forgets to say who is
    // looking cannot leak operator instructions to a teacher.
    $rendered = Blade::render(
        '<x-upload-status-panel :upload="$upload" :stalled="true" status-url="/s" retry-url="/r" />',
        ['upload' => CbtDocumentUpload::factory()->make(['status' => CbtDocumentUploadStatus::Pending])],
    );

    expect($rendered)->not->toContain('php artisan queue:work');
});

test('the pre-upload warning does not hand a teacher a shell command either', function () {
    // The second place with the same fault. The panel on a finished upload was
    // one; this is the notice shown BEFORE uploading, on the test page.
    stallTheQueue();

    // There is no warning left to leak a command through: extraction no longer
    // depends on a worker, so nothing is shown before uploading.
    expect(app(CbtExtractionAvailability::class)->warning())->toBeNull();
});

test('and says nothing at all when a worker is running', function () {
    config(['queue.default' => 'sync']);

    expect(app(CbtExtractionAvailability::class)->warning())->toBeNull();
});

// -----------------------------------------------------------------------------
// Lock is a toggle
// -----------------------------------------------------------------------------

test('THE BUG: the lock button on a locked test used to do nothing', function () {
    // It always submitted "locked", so pressing it on an already-locked test
    // re-applied the state it was already in. The way back was "Save as
    // Draft", which does not read as the opposite of Lock.
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'status' => CbtTestStatus::Locked,
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.cbt.tests.show', [$this->school, $test]))
        ->assertOk()
        ->assertSee('Unlock')
        ->assertSee('value="draft"', false);
});

test('an unlocked test offers Lock', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'status' => CbtTestStatus::Draft,
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.cbt.tests.show', [$this->school, $test]))
        ->assertOk()
        ->assertSee('Lock')
        ->assertSee('value="locked"', false);
});

test('locking and then unlocking returns the test to draft', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'status' => CbtTestStatus::Draft,
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'locked']);

    expect($test->fresh()->status)->toBe(CbtTestStatus::Locked);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'draft']);

    // Draft, not published. Students only ever see Published tests, so a
    // locked test is a finished draft held back from editing, and publishing
    // stays a separate, deliberate act.
    expect($test->fresh()->status)->toBe(CbtTestStatus::Draft);
});

test('neither locking nor unlocking is possible once students have started', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'status' => CbtTestStatus::Locked,
    ]);

    CbtTestAttempt::factory()->create(['cbt_test_id' => $test->id]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'draft'])
        ->assertStatus(422);

    expect($test->fresh()->status)->toBe(CbtTestStatus::Locked);
});

// -----------------------------------------------------------------------------
// A deleted upload is not a failure
// -----------------------------------------------------------------------------

test('a job whose upload was deleted is dropped, not failed', function () {
    // Found in the live queue: a document uploaded while nothing was working
    // the queue, then deleted. Its job stayed behind, looked the row up when it
    // finally ran, found nothing and threw ModelNotFoundException, raising a
    // failed job over somebody changing their mind.
    expect((new ProcessCbtTestDocumentUpload(
        CbtTestDocumentUpload::factory()->make()
    ))->deleteWhenMissingModels)->toBeTrue();

    expect((new ProcessCbtDocumentUpload(
        CbtDocumentUpload::factory()->make()
    ))->deleteWhenMissingModels)->toBeTrue();
});
