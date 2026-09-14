<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\TeacherAssignment;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    TeacherAssignment::factory()->subjectTeacher('Mathematics')->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id, 'class_name' => 'JSS 1']);
});

test('a teacher sees examinations for subjects they teach', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'name' => 'First Term Examination']);
    ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'English Language', 'max_score' => 100]);

    $response = $this->actingAs($this->teacher, 'staff')->get(route('staff.exams.index', $this->school));

    $response->assertOk()
        ->assertSee('JSS 1')
        ->assertSee('Mathematics')
        ->assertDontSee('English Language')
        // The examination's name is deliberately not a column any more: it
        // repeated the term and session sitting beside it.
        ->assertDontSee('First Term Examination');
});

test('a teacher does not see examinations for classes/subjects they do not teach', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'name' => 'Other Class Exam']);
    ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Mathematics', 'max_score' => 100]);

    // Asserted on the class, which is what the table now shows. Asserting on
    // the examination's name would pass for the wrong reason, that name is
    // no longer rendered for anybody.
    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.exams.index', $this->school))
        ->assertDontSee('JSS 2');
});

test('a teacher can enter test and exam scores for a subject they teach', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.exams.scores.update', [$this->school, $examination, $subject]), [
            'test_scores' => [$student->id => 34],
            'exam_scores' => [$student->id => 51],
        ])
        ->assertRedirect();

    $score = ExaminationScore::where('examination_subject_id', $subject->id)->where('student_id', $student->id)->firstOrFail();
    expect((float) $score->test_score)->toBe(34.0);
    expect((float) $score->exam_score)->toBe(51.0);
    expect((float) $score->score)->toBe(85.0);
});

test('a teacher cannot enter scores for a subject they do not teach', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'English Language', 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.exams.scores.update', [$this->school, $examination, $subject]), [
            'test_scores' => [$student->id => 34],
            'exam_scores' => [$student->id => 51],
        ])
        ->assertForbidden();

    expect(ExaminationScore::where('examination_subject_id', $subject->id)->exists())->toBeFalse();
});

test('a score entered by a teacher is immediately visible to the school admin result page', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true, 'first_name' => 'Amaka', 'last_name' => 'Obi']);

    $this->actingAs($this->teacher, 'staff')->put(route('staff.exams.scores.update', [$this->school, $examination, $subject]), [
        'test_scores' => [$student->id => 36],
        'exam_scores' => [$student->id => 54],
    ]);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->actingAs($admin)
        ->getJson(route('results.show', [$examination, $student]))
        ->assertOk()
        ->assertJsonFragment(['has_scores' => true]);
});
