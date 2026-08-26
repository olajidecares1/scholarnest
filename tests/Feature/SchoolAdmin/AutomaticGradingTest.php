<?php

use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('automatic grading is on by default', function () {
    expect($this->school->automatic_grading)->toBeTrue();
});

test('automatic grading cannot be turned off', function () {
    $this->school->update(['automatic_grading' => false]);

    // Saving settings forces it back on rather than reading it from the form.
    // A grade somebody could type is a grade that can disagree with the marks
    // it came from, and a report card's grades should mean the same thing on
    // every card a school issues.
    $this->actingAs($this->admin)->put(route('settings.update'), [
        'name' => $this->school->name,
        'timezone' => $this->school->timezone ?? 'Africa/Lagos',
        'automatic_grading' => '0',
    ])->assertRedirect();

    expect($this->school->fresh()->automatic_grading)->toBeTrue();
});

test('a grade override is ignored while automatic grading is on', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)->post(route('examinations.scores.store', $subject), [
        'test_scores' => [$student->id => 30],
        'exam_scores' => [$student->id => 40],
        'grade_overrides' => [$student->id => 'Z'],
    ]);

    $score = ExaminationScore::where('examination_subject_id', $subject->id)->where('student_id', $student->id)->firstOrFail();
    expect($score->grade_override)->toBeNull();
    expect($score->grade())->not->toBe('Z');
});

test('a grade override is ignored even with the old setting turned off', function () {
    // The column still exists and is still honoured on read, so a grade a
    // school set by hand before this rule keeps showing on the cards it is
    // already on. What is gone is the ability to set a new one.
    $this->school->update(['automatic_grading' => false]);

    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)->post(route('examinations.scores.store', $subject), [
        'test_scores' => [$student->id => 30],
        'exam_scores' => [$student->id => 40],
        'grade_overrides' => [$student->id => 'Z'],
    ]);

    $score = ExaminationScore::where('examination_subject_id', $subject->id)->where('student_id', $student->id)->firstOrFail();

    expect($score->grade_override)->toBeNull()
        ->and($score->grade())->not->toBe('Z')

        // And the total is the sum of the two marks, worked out on the server
        // rather than accepted from the form.
        ->and((float) $score->score)->toBe(70.0);
});

test('a teacher cannot set a grade or a remark either', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)->post(route('examinations.scores.store', $subject), [
        'test_scores' => [$student->id => 32],
        'exam_scores' => [$student->id => 51],
        'grade_overrides' => [$student->id => 'A'],
        'remarks' => [$student->id => 'Made this up'],
        'scores' => [$student->id => 100],
    ]);

    $score = ExaminationScore::where('examination_subject_id', $subject->id)->where('student_id', $student->id)->firstOrFail();

    // 32 + 51 = 83, whatever else was in the request.
    expect((float) $score->score)->toBe(83.0)
        ->and($score->grade_override)->toBeNull()
        ->and($score->remark)->toBeNull();
});
