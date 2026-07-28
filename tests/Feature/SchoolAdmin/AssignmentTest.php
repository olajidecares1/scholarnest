<?php

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\School;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('a school admin can create an assignment', function () {
    $response = $this->actingAs($this->admin)->post(route('assignments.store'), [
        'class_name' => 'JSS 1',
        'subject' => 'Mathematics',
        'title' => 'Algebra Worksheet',
        'due_date' => now()->addWeek()->toDateString(),
        'max_score' => 20,
    ]);

    $response->assertRedirect();

    $assignment = Assignment::where('title', 'Algebra Worksheet')->firstOrFail();
    expect($assignment->school_id)->toBe($this->school->id);
    expect($assignment->max_score)->toBe(20);
});

test('a school admin only sees assignments from their own school', function () {
    $otherSchool = School::factory()->create();
    Assignment::factory()->create(['school_id' => $this->school->id, 'title' => 'Own Assignment']);
    Assignment::factory()->create(['school_id' => $otherSchool->id, 'title' => 'Other Assignment']);

    $this->actingAs($this->admin)
        ->get(route('assignments.index'))
        ->assertSee('Own Assignment')
        ->assertDontSee('Other Assignment');
});

test('a school admin cannot view another school\'s assignment', function () {
    $otherSchool = School::factory()->create();
    $assignment = Assignment::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->get(route('assignments.show', $assignment))
        ->assertForbidden();
});

test('a school admin can save submission statuses and scores', function () {
    $assignment = Assignment::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)->post(route('assignments.submissions.store', $assignment), [
        'submissions' => [
            $student->id => ['status' => SubmissionStatus::Graded->value, 'score' => 88, 'feedback' => 'Great work'],
        ],
    ])->assertRedirect();

    $submission = $assignment->submissions()->where('student_id', $student->id)->firstOrFail();
    expect($submission->status)->toBe(SubmissionStatus::Graded);
    expect((float) $submission->score)->toBe(88.0);
    expect($submission->feedback)->toBe('Great work');
});

test('saving submissions twice updates the existing record instead of duplicating', function () {
    $assignment = Assignment::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)->post(route('assignments.submissions.store', $assignment), [
        'submissions' => [$student->id => ['status' => SubmissionStatus::Submitted->value]],
    ]);
    $this->actingAs($this->admin)->post(route('assignments.submissions.store', $assignment), [
        'submissions' => [$student->id => ['status' => SubmissionStatus::Graded->value, 'score' => 75]],
    ]);

    expect($assignment->submissions()->where('student_id', $student->id)->count())->toBe(1);
    expect($assignment->submissions()->where('student_id', $student->id)->first()->status)->toBe(SubmissionStatus::Graded);
});

test('a school admin cannot save submissions for a student outside their school', function () {
    $assignment = Assignment::factory()->create(['school_id' => $this->school->id]);
    $otherStudent = Student::factory()->create(['school_id' => School::factory()->create()->id]);

    $this->actingAs($this->admin)->post(route('assignments.submissions.store', $assignment), [
        'submissions' => [$otherStudent->id => ['status' => SubmissionStatus::Graded->value, 'score' => 50]],
    ]);

    expect($assignment->submissions()->where('student_id', $otherStudent->id)->exists())->toBeFalse();
});

test('a school admin can delete an assignment', function () {
    $assignment = Assignment::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('assignments.destroy', $assignment))
        ->assertRedirect();

    expect(Assignment::find($assignment->id))->toBeNull();
});
