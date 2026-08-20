<?php

use App\Enums\ExamTerm;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can create an examination', function () {
    $response = $this->actingAs($this->admin)->post(route('examinations.store'), [
        'name' => 'First Term Examination',
        'class_name' => 'JSS 1',
        'term' => ExamTerm::First->value,
        'session' => '2025/2026',
    ]);

    $response->assertRedirect();

    $examination = Examination::where('name', 'First Term Examination')->firstOrFail();
    expect($examination->school_id)->toBe($this->school->id);
    expect($examination->class_name)->toBe('JSS 1');
});

test('an arbitrary session string is rejected', function () {
    $this->actingAs($this->admin)->post(route('examinations.store'), [
        'name' => 'First Term Examination',
        'class_name' => 'JSS 1',
        'term' => ExamTerm::First->value,
        'session' => 'not-a-real-session',
    ])->assertSessionHasErrors('session');
});

test('a school admin can edit an examination, including its session', function () {
    $examination = Examination::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'Old Name',
        'class_name' => 'JSS 1',
        'term' => ExamTerm::First,
        'session' => '2025/2026',
    ]);

    $this->actingAs($this->admin)->put(route('examinations.update', $examination), [
        'name' => 'New Name',
        'class_name' => 'JSS 2',
        'term' => ExamTerm::Second->value,
        'session' => '2026/2027',
    ])->assertRedirect();

    $examination->refresh();
    expect($examination->name)->toBe('New Name');
    expect($examination->class_name)->toBe('JSS 2');
    expect($examination->term)->toBe(ExamTerm::Second);
    expect($examination->session)->toBe('2026/2027');
});

test('a school admin cannot edit another school\'s examination', function () {
    $otherSchool = School::factory()->create();
    $examination = Examination::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)->put(route('examinations.update', $examination), [
        'name' => 'Hacked',
        'class_name' => 'JSS 1',
        'term' => ExamTerm::First->value,
        'session' => '2025/2026',
    ])->assertForbidden();
});

test('a school admin only sees examinations from their own school', function () {
    $otherSchool = School::factory()->create();
    Examination::factory()->create(['school_id' => $this->school->id, 'name' => 'Own Exam']);
    Examination::factory()->create(['school_id' => $otherSchool->id, 'name' => 'Other Exam']);

    $this->actingAs($this->admin)
        ->get(route('examinations.index'))
        ->assertSee('Own Exam')
        ->assertDontSee('Other Exam');
});

test('a school admin cannot view another school\'s examination', function () {
    $otherSchool = School::factory()->create();
    $examination = Examination::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->get(route('examinations.show', $examination))
        ->assertForbidden();
});

test('a school admin can add a subject to an examination', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('examinations.subjects.store', $examination), [
        'name' => 'Mathematics',
    ])->assertRedirect();

    $subject = $examination->subjects()->where('name', 'Mathematics')->firstOrFail();
    expect($subject->max_score)->toBe(100);
});

test('the add subject form offers the class\'s configured subjects once any are set', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'SS 1 Science']);
    $physics = Subject::factory()->create(['name' => 'Physics']);
    SubjectOffering::factory()->create(['school_id' => $this->school->id, 'class_name' => 'SS 1 Science', 'subject_id' => $physics->id]);

    $this->actingAs($this->admin)
        ->get(route('examinations.show', $examination))
        ->assertOk()
        ->assertSee('Physics');
});

test('subject names must be unique within an examination', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id]);
    ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Mathematics']);

    $this->actingAs($this->admin)->post(route('examinations.subjects.store', $examination), [
        'name' => 'Mathematics',
    ])->assertSessionHasErrors('name');
});

test('a school admin can save test and exam scores for a subject', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)->post(route('examinations.scores.store', $subject), [
        'test_scores' => [$student->id => 34],
        'exam_scores' => [$student->id => 51],
    ])->assertRedirect();

    $score = $subject->scores()->where('student_id', $student->id)->firstOrFail();
    expect((float) $score->test_score)->toBe(34.0);
    expect((float) $score->exam_score)->toBe(51.0);
    expect((float) $score->score)->toBe(85.0);
    expect($score->percentage())->toBe(85.0);
    expect($score->grade())->toBe('A');
});

test('saving scores twice updates the existing score instead of duplicating', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)->post(route('examinations.scores.store', $subject), ['test_scores' => [$student->id => 20], 'exam_scores' => [$student->id => 40]]);
    $this->actingAs($this->admin)->post(route('examinations.scores.store', $subject), ['test_scores' => [$student->id => 35], 'exam_scores' => [$student->id => 55]]);

    expect($subject->scores()->where('student_id', $student->id)->count())->toBe(1);
    expect((float) $subject->scores()->where('student_id', $student->id)->first()->score)->toBe(90.0);
});

test('a school admin cannot save scores for a student outside their school', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id]);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id]);
    $otherStudent = Student::factory()->create(['school_id' => School::factory()->create()->id]);

    $this->actingAs($this->admin)->post(route('examinations.scores.store', $subject), [
        'test_scores' => [$otherStudent->id => 30],
        'exam_scores' => [$otherStudent->id => 40],
    ]);

    expect($subject->scores()->where('student_id', $otherStudent->id)->exists())->toBeFalse();
});

test('report cards rank students by average percentage across subjects', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);

    $topStudent = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'first_name' => 'Top', 'last_name' => 'Student']);
    $lowStudent = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'first_name' => 'Low', 'last_name' => 'Student']);

    $subject->scores()->create(['student_id' => $topStudent->id, 'score' => 95]);
    $subject->scores()->create(['student_id' => $lowStudent->id, 'score' => 40]);

    $this->actingAs($this->admin)
        ->get(route('examinations.report-cards.index', $examination))
        ->assertSeeInOrder(['Top Student', 'Low Student']);
});

test('a school admin can view an individual report card with grades', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject->scores()->create(['student_id' => $student->id, 'score' => 75]);

    $this->actingAs($this->admin)
        ->get(route('examinations.report-cards.show', [$examination, $student]))
        ->assertStatus(200)
        ->assertSee('Mathematics')
        ->assertSee('75%');
});
