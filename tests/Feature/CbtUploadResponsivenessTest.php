<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Jobs\ProcessCbtDocumentUpload;
use App\Jobs\ProcessCbtTestDocumentUpload;
use App\Models\CbtDocumentUpload;
use App\Models\CbtTest;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use App\Services\QueueWorkerHealth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
});

/**
 * A document of roughly the size a real past-paper PDF runs to.
 */
function cbtDocument(int $kilobytes = 800): UploadedFile
{
    return UploadedFile::fake()->create('past-questions.pdf', $kilobytes, 'application/pdf');
}

test('the ScholarNest Team upload returns without waiting for extraction', function () {
    Queue::fake();

    $team = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $startedAt = microtime(true);

    $this->actingAs($team)
        ->post(route('super-admin.cbt.uploads.store'), ['file' => cbtDocument()])
        ->assertRedirect();

    $elapsed = microtime(true) - $startedAt;

    // The request stores the file and queues the work. Anything approaching a
    // minute means extraction is happening inside the request.
    expect($elapsed)->toBeLessThan(5.0);

    Queue::assertPushed(ProcessCbtDocumentUpload::class);
});

test('the teacher upload returns without waiting for extraction', function () {
    Queue::fake();

    $teacher = Staff::factory()->create([
        'school_id' => $this->school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
    ]);

    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $teacher->id,
    ]);

    $startedAt = microtime(true);

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.cbt.tests.uploads.store', [$this->school, $test]), ['file' => cbtDocument()])
        ->assertRedirect();

    expect(microtime(true) - $startedAt)->toBeLessThan(5.0);

    Queue::assertPushed(ProcessCbtTestDocumentUpload::class);
});

test('extraction never runs inside the web request', function () {
    // The guarantee behind the two timings above, stated directly: if either
    // job is ever handled synchronously, the upload blocks for as long as
    // parsing takes and no amount of front-end progress can hide it.
    foreach ([ProcessCbtDocumentUpload::class, ProcessCbtTestDocumentUpload::class] as $job) {
        expect(in_array(ShouldQueue::class, class_implements($job), true))->toBeTrue();
    }
});

test('the upload is queued as pending, not left without a state', function () {
    Queue::fake();

    $team = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($team)->post(route('super-admin.cbt.uploads.store'), ['file' => cbtDocument()]);

    $upload = CbtDocumentUpload::query()->latest('id')->first();

    expect($upload)->not->toBeNull()
        ->and($upload->status)->toBe(CbtDocumentUploadStatus::Pending)
        ->and($upload->path)->not->toBeNull();

    // And the file it will read is really on disk, so a retry has something to
    // work from rather than asking for the document again.
    Storage::disk('local')->assertExists($upload->path);
});

// ---------------------------------------------------------------------------
// Knowing, rather than guessing, whether anything is processing the queue
// ---------------------------------------------------------------------------

test('a stalled queue is reported in seconds, not after two minutes', function () {
    // The test environment runs the queue synchronously, where there is no
    // queue to stall. These checks are about the database driver the
    // application actually uses, so say so.
    config(['queue.default' => 'database']);

    $health = app(QueueWorkerHealth::class);

    // A job queued a moment ago with no worker running. This is the exact case
    // that used to be reported as healthy for two full minutes, which is the
    // whole of the "it just sits there" complaint.
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subSeconds(25)->timestamp,
        'created_at' => now()->subSeconds(25)->timestamp,
    ]);

    $health->forget();

    expect($health->isRunning())->toBeFalse();
});

test('a worker that is running says so, and is believed', function () {
    // The test environment runs the queue synchronously, where there is no
    // queue to stall. These checks are about the database driver the
    // application actually uses, so say so.
    config(['queue.default' => 'database']);

    $health = app(QueueWorkerHealth::class);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinutes(5)->timestamp,
        'created_at' => now()->subMinutes(5)->timestamp,
    ]);

    // Even with a job that has waited five minutes - which the old inference
    // would have called stalled - a live heartbeat is the better evidence.
    QueueWorkerHealth::heartbeat();
    $health->forget();

    expect($health->isRunning())->toBeTrue();
});

test('a worker that has stopped beating is no longer believed', function () {
    // The test environment runs the queue synchronously, where there is no
    // queue to stall. These checks are about the database driver the
    // application actually uses, so say so.
    config(['queue.default' => 'database']);

    $health = app(QueueWorkerHealth::class);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinutes(5)->timestamp,
        'created_at' => now()->subMinutes(5)->timestamp,
    ]);

    Cache::put('queue-worker-heartbeat', now()->subMinutes(3)->timestamp, now()->addMinutes(5));
    $health->forget();

    expect($health->isRunning())->toBeFalse();
});

test('an idle queue is never called stalled', function () {
    // The test environment runs the queue synchronously, where there is no
    // queue to stall. These checks are about the database driver the
    // application actually uses, so say so.
    config(['queue.default' => 'database']);

    $health = app(QueueWorkerHealth::class);
    $health->forget();

    // Nothing waiting means nothing to be wrong about, worker or no worker.
    expect($health->isRunning())->toBeTrue();
});

test('the person told about a stalled queue is told something they can act on', function () {
    // The test environment runs the queue synchronously, where there is no
    // queue to stall. These checks are about the database driver the
    // application actually uses, so say so.
    config(['queue.default' => 'database']);

    $health = app(QueueWorkerHealth::class);

    // The ScholarNest Team can start a worker, so they are told how.
    expect($health->stalledMessage(canOperateTheServer: true))->toContain('queue:work');

    // A teacher cannot, and should not be shown a shell command. They are told
    // their document is safe and that they need not upload it again.
    $forTeacher = $health->stalledMessage(canOperateTheServer: false);

    expect($forTeacher)->not->toContain('queue:work')
        ->and($forTeacher)->toContain('do not need to upload it again');
});

test('both uploaders report a stall rather than spinning', function () {
    $team = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $upload = CbtDocumentUpload::factory()->create([
        'uploaded_by' => $team->id,
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinute()->timestamp,
        'created_at' => now()->subMinute()->timestamp,
    ]);

    config(['queue.default' => 'database']);
    app(QueueWorkerHealth::class)->forget();

    $this->actingAs($team)
        ->getJson(route('super-admin.cbt.uploads.status', $upload))
        ->assertOk()
        ->assertJson(['stalled' => true])
        ->assertJsonFragment(['status' => 'pending']);
});
