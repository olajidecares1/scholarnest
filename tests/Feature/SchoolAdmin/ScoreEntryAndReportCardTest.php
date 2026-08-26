<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\GradeBand;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;

/**
 * Two numbers in, a whole report card out.
 *
 * A teacher enters a Test mark out of 40 and an Exam mark out of 60. Nothing
 * else is typed: the total, the percentage, the grade and the remark are all
 * worked out from those two, against the school's own grading bands. That is
 * the point rather than a convenience - a grade somebody could type is a grade
 * that can disagree with the marks it is supposed to come from.
 */
function scoringSchool(string $code = 'MIS'): array
{
    $school = School::factory()->create(['name' => 'Marvel Int School', 'school_code' => $code]);
    activateSchool($school, PlanKey::Basic);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Primary 6',
        'session' => '2024/2025',
    ]);

    $subject = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'name' => 'English Language',
        'max_score' => 100,
    ]);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Primary 6',
        'is_active' => true,
    ]);

    return [$school->fresh(), $admin, $examination, $subject, $student];
}

// -----------------------------------------------------------------------------
// The split, and the arithmetic
// -----------------------------------------------------------------------------

test('the test is out of 40 and the exam out of 60', function () {
    [, , , $subject] = scoringSchool();

    expect($subject->testMaxScore())->toBe(40)
        ->and($subject->examMaxScore())->toBe(60);
});

test('the total is the sum of the two marks, worked out on the server', function () {
    [$school, $admin, , $subject, $student] = scoringSchool();

    $this->actingAs($admin)->post(route('examinations.scores.store', $subject), [
        'test_scores' => [$student->id => 32],
        'exam_scores' => [$student->id => 51],
    ])->assertSessionHasNoErrors();

    $score = ExaminationScore::where('student_id', $student->id)->firstOrFail();

    // 32 / 40 and 51 / 60 makes 83 / 100.
    expect((float) $score->test_score)->toBe(32.0)
        ->and((float) $score->exam_score)->toBe(51.0)
        ->and((float) $score->score)->toBe(83.0);
});

test('the entry grid shows the total, grade and remark without offering to edit them', function () {
    [$school, $admin, $examination, $subject] = scoringSchool();

    $response = $this->actingAs($admin)
        ->get(route('examinations.scores', $subject))
        ->assertOk();

    $response->assertSee('Test / 40')
        ->assertSee('Exam / 60')
        ->assertSee('Total / 100')
        ->assertSee('Grade')
        ->assertSee('Remark')
        ->assertSee('cannot be typed');

    // Two inputs per student and no more: nothing to the right of Exam is a
    // field.
    $response->assertSee('name="test_scores', false)
        ->assertSee('name="exam_scores', false)
        ->assertDontSee('name="grade_overrides', false)
        ->assertDontSee('name="scores', false)
        ->assertDontSee('name="remarks', false);
});

// -----------------------------------------------------------------------------
// Validation
// -----------------------------------------------------------------------------

test('a mark outside its own range is refused', function (array $payload, string $field) {
    [$school, $admin, , $subject, $student] = scoringSchool();

    $this->actingAs($admin)
        ->post(route('examinations.scores.store', $subject), [
            'test_scores' => [$student->id => $payload['test']],
            'exam_scores' => [$student->id => $payload['exam']],
        ])
        ->assertSessionHasErrors($field);

    expect(ExaminationScore::where('student_id', $student->id)->exists())->toBeFalse();
})->with([
    'test above 40' => [['test' => 41, 'exam' => 50], 'test_scores.*'],
    'exam above 60' => [['test' => 30, 'exam' => 61], 'exam_scores.*'],
    'negative test' => [['test' => -1, 'exam' => 50], 'test_scores.*'],
    'negative exam' => [['test' => 30, 'exam' => -5], 'exam_scores.*'],
]);

test('the highest possible marks come to exactly 100', function () {
    [$school, $admin, , $subject, $student] = scoringSchool();

    $this->actingAs($admin)->post(route('examinations.scores.store', $subject), [
        'test_scores' => [$student->id => 40],
        'exam_scores' => [$student->id => 60],
    ])->assertSessionHasNoErrors();

    expect((float) ExaminationScore::where('student_id', $student->id)->firstOrFail()->score)->toBe(100.0);
});

// -----------------------------------------------------------------------------
// Grading, from the school's own bands
// -----------------------------------------------------------------------------

test('the grade comes from the school\'s own bands, not a fixed scale', function () {
    [$school, $admin, , $subject, $student] = scoringSchool();

    // This school calls 80 a B. Another school's 80 might be an A - which is
    // exactly why the scale cannot be hard-coded.
    GradeBand::where('school_id', $school->id)->delete();
    GradeBand::create(['school_id' => $school->id, 'min_percent' => 85, 'max_percent' => 100, 'letter' => 'A', 'description' => 'Outstanding', 'position' => 1]);
    GradeBand::create(['school_id' => $school->id, 'min_percent' => 70, 'max_percent' => 84, 'letter' => 'B', 'description' => 'Very Good', 'position' => 2]);
    GradeBand::create(['school_id' => $school->id, 'min_percent' => 0, 'max_percent' => 69, 'letter' => 'C', 'description' => 'Fair', 'position' => 3]);

    $this->actingAs($admin)->post(route('examinations.scores.store', $subject), [
        'test_scores' => [$student->id => 32],
        'exam_scores' => [$student->id => 51],
    ]);

    $score = ExaminationScore::where('student_id', $student->id)->firstOrFail();

    expect($score->grade())->toBe('B')
        ->and(GradeBand::describe($school->fresh(), 83.0))->toBe('Very Good');
});

test('one school\'s grading never reaches another school\'s students', function () {
    [$schoolA, $adminA, , $subjectA, $studentA] = scoringSchool('AAA');
    [$schoolB, $adminB, , $subjectB, $studentB] = scoringSchool('BBB');

    GradeBand::where('school_id', $schoolA->id)->delete();
    GradeBand::create(['school_id' => $schoolA->id, 'min_percent' => 0, 'max_percent' => 100, 'letter' => 'A', 'description' => 'Everyone passes', 'position' => 1]);

    GradeBand::where('school_id', $schoolB->id)->delete();
    GradeBand::create(['school_id' => $schoolB->id, 'min_percent' => 0, 'max_percent' => 100, 'letter' => 'F', 'description' => 'Everyone fails', 'position' => 1]);

    foreach ([[$adminA, $subjectA, $studentA], [$adminB, $subjectB, $studentB]] as [$admin, $subject, $student]) {
        $this->actingAs($admin)->post(route('examinations.scores.store', $subject), [
            'test_scores' => [$student->id => 32],
            'exam_scores' => [$student->id => 51],
        ]);
    }

    expect(ExaminationScore::where('student_id', $studentA->id)->firstOrFail()->grade())->toBe('A')
        ->and(ExaminationScore::where('student_id', $studentB->id)->firstOrFail()->grade())->toBe('F');
});

// -----------------------------------------------------------------------------
// The report card, generated from the record
// -----------------------------------------------------------------------------

test('the report card is built from the student\'s own marks', function () {
    [$school, $admin, $examination, $subject, $student] = scoringSchool();

    $this->actingAs($admin)->post(route('examinations.scores.store', $subject), [
        'test_scores' => [$student->id => 32],
        'exam_scores' => [$student->id => 52],
    ]);

    $response = $this->actingAs($admin)
        ->get(route('results.print', [$examination, $student]))
        ->assertOk();

    // The school's own identity, the student's own record, and the marks as
    // entered - none of it written into the page.
    $response->assertSee($school->name)
        ->assertSee($student->fullName())
        ->assertSee($student->admission_number)
        ->assertSee('Primary 6')
        ->assertSee('2024/2025')
        ->assertSee('English Language')
        ->assertSee('32')
        ->assertSee('52')
        ->assertSee('84');
});

test('two students in one class get their own cards', function () {
    [$school, $admin, $examination, $subject, $first] = scoringSchool('TWO');

    $second = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Primary 6',
        'is_active' => true,
    ]);

    $this->actingAs($admin)->post(route('examinations.scores.store', $subject), [
        'test_scores' => [$first->id => 32, $second->id => 12],
        'exam_scores' => [$first->id => 52, $second->id => 20],
    ]);

    $this->actingAs($admin)->get(route('results.print', [$examination, $first]))
        ->assertOk()
        ->assertSee($first->fullName())
        ->assertSee('84')
        ->assertDontSee($second->fullName());

    $this->actingAs($admin)->get(route('results.print', [$examination, $second]))
        ->assertOk()
        ->assertSee($second->fullName())
        ->assertSee('32');
});

test('a teacher enters marks for one subject and then the next', function () {
    [$school, $admin, $examination, $english, $student] = scoringSchool();

    $maths = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'name' => 'Mathematics',
        'max_score' => 100,
    ]);

    $teacher = Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
    ]);

    foreach (['English Language', 'Mathematics'] as $subjectName) {
        TeacherAssignment::create([
            'school_id' => $school->id,
            'staff_id' => $teacher->id,
            'type' => 'subject_teacher',
            'class_name' => 'Primary 6',
            'subject' => $subjectName,
        ]);
    }

    // The page offers the other subjects, so the teacher moves on without
    // going back to the examination first.
    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.scores.edit', [$school, $examination, $english]))
        ->assertOk()
        ->assertSee('English Language')
        ->assertSee('Mathematics');

    foreach ([[$english, 32, 52], [$maths, 31, 50]] as [$subject, $test, $exam]) {
        $this->actingAs($teacher, 'staff')->put(
            route('staff.exams.scores.update', [$school, $examination, $subject]),
            ['test_scores' => [$student->id => $test], 'exam_scores' => [$student->id => $exam]],
        )->assertSessionHasNoErrors();
    }

    expect(ExaminationScore::whereIn('examination_subject_id', [$english->id, $maths->id])
        ->orderBy('examination_subject_id')
        ->pluck('score')
        ->map(fn ($score) => (float) $score)
        ->all())->toBe([84.0, 81.0]);
});
