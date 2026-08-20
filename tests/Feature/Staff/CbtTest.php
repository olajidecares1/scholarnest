<?php

use App\Enums\CbtTestStatus;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessCbtTestDocumentUpload;
use App\Models\CbtTest;
use App\Models\CbtTestAttempt;
use App\Models\CbtTestQuestion;
use App\Models\CbtTestQuestionOption;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Subscription;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
});

test('a non-teacher staff member cannot access CBT management', function () {
    $nonTeacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::SupportStaff]);

    $this->actingAs($nonTeacher, 'staff')
        ->get(route('staff.cbt.tests.index', $this->school))
        ->assertForbidden();
});

test('a teacher does not see the CBT Management card on their dashboard if not a teacher', function () {
    $nonTeacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Administrator]);

    $this->actingAs($nonTeacher, 'staff')
        ->get(route('staff.dashboard', $this->school))
        ->assertStatus(200)
        ->assertDontSee('CBT Management');
});

test('a teacher sees the CBT Management card on their dashboard', function () {
    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.dashboard', $this->school))
        ->assertStatus(200)
        ->assertSee('CBT Management');
});

test('a teacher can create a CBT test', function () {
    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.store', $this->school), [
            'title' => 'First Term Mathematics Test',
            'subject' => 'Mathematics',
            'class_name' => 'JSS 1',
            'duration_minutes' => 30,
            'pass_mark' => 50,
        ])
        ->assertRedirect();

    $test = CbtTest::where('title', 'First Term Mathematics Test')->firstOrFail();
    expect($test->school_id)->toBe($this->school->id);
    expect($test->staff_id)->toBe($this->teacher->id);
    expect($test->status)->toBe(CbtTestStatus::Draft);
});

test('a teacher can view and update their own test', function () {
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id, 'title' => 'Old Title']);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.cbt.tests.show', [$this->school, $test]))
        ->assertStatus(200)
        ->assertSee('Old Title');

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.cbt.tests.update', [$this->school, $test]), [
            'title' => 'New Title',
            'subject' => $test->subject,
            'class_name' => $test->class_name,
            'duration_minutes' => $test->duration_minutes,
            'pass_mark' => $test->pass_mark,
        ])
        ->assertRedirect();

    expect($test->fresh()->title)->toBe('New Title');
});

test('a teacher cannot view or manage another teacher\'s test', function () {
    $otherTeacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $otherTeacher->id]);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.cbt.tests.show', [$this->school, $test]))
        ->assertForbidden();

    $this->actingAs($this->teacher, 'staff')
        ->delete(route('staff.cbt.tests.destroy', [$this->school, $test]))
        ->assertForbidden();
});

test('a teacher can add a manual question with options', function () {
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.questions.store', [$this->school, $test]), [
            'question_text' => 'What is 2 + 2?',
            'options' => ['3', '4', '5'],
            'correct_index' => 1,
        ])
        ->assertRedirect();

    $question = $test->questions()->firstOrFail();
    expect($question->question_text)->toBe('What is 2 + 2?');
    expect($question->options()->count())->toBe(3);
    expect($question->correctOption()->option_text)->toBe('4');
});

test('a teacher can edit and delete a question', function () {
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);
    $question = CbtTestQuestion::factory()->create(['cbt_test_id' => $test->id]);

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.cbt.tests.questions.update', [$this->school, $test, $question]), [
            'question_text' => 'Updated question?',
            'options' => ['A', 'B'],
            'correct_index' => 0,
        ])
        ->assertRedirect();

    expect($question->fresh()->question_text)->toBe('Updated question?');

    $this->actingAs($this->teacher, 'staff')
        ->delete(route('staff.cbt.tests.questions.destroy', [$this->school, $test, $question]))
        ->assertRedirect();

    expect(CbtTestQuestion::find($question->id))->toBeNull();
});

test('publishing requires at least one question', function () {
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'published'])
        ->assertStatus(422);
});

test('a teacher can lock, then publish, a test that has questions', function () {
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);
    CbtTestQuestion::factory()->create(['cbt_test_id' => $test->id]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'locked'])
        ->assertRedirect();

    expect($test->fresh()->status)->toBe(CbtTestStatus::Locked);
    expect($test->fresh()->isOpenForStudents())->toBeFalse();

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'published'])
        ->assertRedirect();

    expect($test->fresh()->status)->toBe(CbtTestStatus::Published);
    expect($test->fresh()->isOpenForStudents())->toBeTrue();
});

test('a teacher can archive a published test', function () {
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id, 'status' => CbtTestStatus::Published]);
    CbtTestQuestion::factory()->create(['cbt_test_id' => $test->id]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'archived'])
        ->assertRedirect();

    expect($test->fresh()->status)->toBe(CbtTestStatus::Archived);
    expect($test->fresh()->isOpenForStudents())->toBeFalse();
});

test('a test cannot be locked or unlocked once a student has started an attempt', function () {
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id, 'status' => CbtTestStatus::Published]);
    CbtTestQuestion::factory()->create(['cbt_test_id' => $test->id]);
    CbtTestAttempt::factory()->create(['cbt_test_id' => $test->id]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'draft'])
        ->assertStatus(422);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'locked'])
        ->assertStatus(422);

    // Archiving is still allowed once attempts exist.
    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'archived'])
        ->assertRedirect();

    expect($test->fresh()->status)->toBe(CbtTestStatus::Archived);
});

test('a teacher can duplicate a test with its questions and options', function () {
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id, 'title' => 'Original Test', 'status' => CbtTestStatus::Published]);
    $question = CbtTestQuestion::factory()->create(['cbt_test_id' => $test->id, 'question_text' => 'What is 2 + 2?']);
    CbtTestQuestionOption::factory()->create(['cbt_test_question_id' => $question->id, 'option_text' => '4', 'is_correct' => true]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.duplicate', [$this->school, $test]))
        ->assertRedirect();

    $copy = CbtTest::where('title', 'Original Test (Copy)')->firstOrFail();
    expect($copy->status)->toBe(CbtTestStatus::Draft);
    expect($copy->staff_id)->toBe($this->teacher->id);
    expect($copy->questions()->count())->toBe(1);
    expect($copy->questions()->first()->options()->count())->toBe(1);
    expect($copy->questions()->first()->correctOption()->option_text)->toBe('4');
});

test('uploading a document dispatches the extraction job', function () {
    Bus::fake();
    Storage::fake('local');

    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.uploads.store', [$this->school, $test]), [
            'file' => UploadedFile::fake()->create('questions.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $upload = $test->documentUploads()->firstOrFail();
    expect($upload->staff_id)->toBe($this->teacher->id);

    Bus::assertDispatched(ProcessCbtTestDocumentUpload::class, fn ($job) => $job->upload->is($upload));
});
