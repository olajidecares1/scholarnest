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

test('a school admin can turn automatic grading off from settings', function () {
    $this->actingAs($this->admin)->put(route('settings.update'), [
        'name' => $this->school->name,
        'timezone' => $this->school->timezone ?? 'Africa/Lagos',
    ])->assertRedirect();

    expect($this->school->fresh()->automatic_grading)->toBeFalse();
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

test('a school admin can override a grade once automatic grading is off', function () {
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
    expect($score->grade_override)->toBe('Z');
    expect($score->grade())->toBe('Z');
});
