<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Enums\TeacherAssignmentType;
use App\Models\Examination;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\TeacherAssignment;

/**
 * A school on the given plan, with a teacher and a class of students.
 *
 * @return array{0: School, 1: Staff}
 */
function scoreEntrySchool(PlanKey $planKey = PlanKey::Basic): array
{
    $school = School::factory()->create(['current_session' => '2026/2027']);

    $plan = Plan::firstOrCreate(
        ['key' => $planKey],
        Plan::factory()->make(['key' => $planKey])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $teacher = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher]);

    Student::factory()->count(3)->create([
        'school_id' => $school->id,
        'class_name' => 'SS 3 Science',
        'is_active' => true,
    ]);

    return [$school, $teacher];
}

function scoreEntryExamination(School $school): Examination
{
    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'SS 3 Science',
        'session' => '2026/2027',
        'term' => ExamTerm::First,
    ]);

    foreach (['Mathematics', 'English Language', 'Physics'] as $name) {
        $examination->subjects()->create(['name' => $name, 'max_score' => 100]);
    }

    return $examination;
}

// -----------------------------------------------------------------------------
// The defect: a class teacher could not reach score entry at all.
// -----------------------------------------------------------------------------

test('a class teacher can enter scores for every subject in their own class', function () {
    // Compiling the class's results is what being its class teacher means. A
    // teacher who taught no individual subject - the ordinary arrangement in a
    // primary class - previously saw an empty page and no way forward.
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => null,
    ]);

    $examination = scoreEntryExamination($school);

    $response = $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.index', $school))
        ->assertOk();

    foreach (['Mathematics', 'English Language', 'Physics'] as $subject) {
        $response->assertSee($subject);
    }

    expect($teacher->canEnterScoresFor('SS 3 Science', 'Mathematics'))->toBeTrue();
});

test('a class teacher can open the score sheet for a subject they do not personally teach', function () {
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => null,
    ]);

    $examination = scoreEntryExamination($school);
    $subject = $examination->subjects()->where('name', 'Physics')->firstOrFail();

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.scores.edit', [$school, $examination, $subject]))
        ->assertOk();
});

test('a subject teacher still only reaches the subject they teach', function () {
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::SubjectTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => 'Mathematics',
    ]);

    $examination = scoreEntryExamination($school);

    expect($teacher->canEnterScoresFor('SS 3 Science', 'Mathematics'))->toBeTrue()
        ->and($teacher->canEnterScoresFor('SS 3 Science', 'Physics'))->toBeFalse();

    $physics = $examination->subjects()->where('name', 'Physics')->firstOrFail();

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.scores.edit', [$school, $examination, $physics]))
        ->assertForbidden();
});

test('a teacher with no assignment at all is told that, not that no exams match', function () {
    [$school, $teacher] = scoreEntrySchool();
    scoreEntryExamination($school);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.index', $school))
        ->assertOk()
        ->assertSee('have not been assigned to a class or subject');
});

test('an assigned teacher with no examination yet is told that instead', function () {
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => null,
    ]);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.index', $school))
        ->assertOk()
        ->assertSee('No examination has been created for your class yet');
});

test('a teacher cannot enter scores for a class they have nothing to do with', function () {
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 2 Science',
        'subject' => null,
    ]);

    $examination = scoreEntryExamination($school);
    $subject = $examination->subjects()->first();

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.scores.edit', [$school, $examination, $subject]))
        ->assertForbidden();
});

// -----------------------------------------------------------------------------
// The teacher enters two numbers. The system does the rest.
// -----------------------------------------------------------------------------

test('the teacher is shown the maximums as labels, never as fields to fill in', function (PlanKey $planKey) {
    // 40, 60 and 100 are the limits the system sets, printed in the column
    // headings. They are not inputs, and the same is true on every plan.
    [$school, $teacher] = scoreEntrySchool($planKey);

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => null,
    ]);

    $examination = scoreEntryExamination($school);
    $subject = $examination->subjects()->first();

    $response = $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.scores.edit', [$school, $examination, $subject]))
        ->assertOk()
        ->assertSee('Test / 40')
        ->assertSee('Exam / 60')
        ->assertSee('Total / 100');

    $html = $response->getContent();

    // Exactly two inputs per student: the test mark and the exam mark.
    expect(substr_count($html, 'name="test_scores['))->toBe(3)
        ->and(substr_count($html, 'name="exam_scores['))->toBe(3)
        ->and($html)->not->toContain('name="total')
        ->and($html)->not->toContain('name="grade')
        ->and($html)->not->toContain('name="max_score');
})->with([
    [PlanKey::Basic],
    [PlanKey::Standard],
    [PlanKey::Exclusive],
]);

test('the total is computed on the server from the two marks entered', function () {
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => null,
    ]);

    $examination = scoreEntryExamination($school);
    $subject = $examination->subjects()->first();
    $student = Student::where('school_id', $school->id)->first();

    // The worked example from the brief: 32 + 51 = 83.
    $this->actingAs($teacher, 'staff')
        ->put(route('staff.exams.scores.update', [$school, $examination, $subject]), [
            'test_scores' => [$student->id => 32],
            'exam_scores' => [$student->id => 51],
        ])
        ->assertRedirect();

    $score = $subject->scores()->where('student_id', $student->id)->firstOrFail();

    expect((float) $score->test_score)->toBe(32.0)
        ->and((float) $score->exam_score)->toBe(51.0)
        ->and((float) $score->score)->toBe(83.0)
        ->and($score->percentage())->toBe(83.0);
});

test('a total sent by the client is ignored - the server recalculates', function () {
    // Nothing the browser sends for the total is trusted, so a tampered
    // request cannot produce a mark that disagrees with its own components.
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => null,
    ]);

    $examination = scoreEntryExamination($school);
    $subject = $examination->subjects()->first();
    $student = Student::where('school_id', $school->id)->first();

    $this->actingAs($teacher, 'staff')
        ->put(route('staff.exams.scores.update', [$school, $examination, $subject]), [
            'test_scores' => [$student->id => 20],
            'exam_scores' => [$student->id => 30],
            'score' => [$student->id => 99],
            'total_scores' => [$student->id => 99],
        ])
        ->assertRedirect();

    expect((float) $subject->scores()->where('student_id', $student->id)->firstOrFail()->score)->toBe(50.0);
});

test('a mark above its own maximum is rejected', function () {
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => null,
    ]);

    $examination = scoreEntryExamination($school);
    $subject = $examination->subjects()->first();
    $student = Student::where('school_id', $school->id)->first();

    // 41 out of 40, and 61 out of 60.
    $this->actingAs($teacher, 'staff')
        ->put(route('staff.exams.scores.update', [$school, $examination, $subject]), [
            'test_scores' => [$student->id => 41],
            'exam_scores' => [$student->id => 61],
        ])
        ->assertSessionHasErrors(['test_scores.'.$student->id, 'exam_scores.'.$student->id]);

    expect($subject->scores()->count())->toBe(0);
});

// -----------------------------------------------------------------------------
// The section itself: one place, on every plan, reached from the sidebar.
// -----------------------------------------------------------------------------

test('the sidebar carries a Test/Exam Score link on every plan', function (PlanKey $planKey) {
    [$school, $teacher] = scoreEntrySchool($planKey);

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => null,
    ]);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertOk()
        ->assertSee('Test/Exam Score')
        ->assertSee(route('staff.exams.index', $school), false);
})->with([
    [PlanKey::Basic],
    [PlanKey::Standard],
    [PlanKey::Exclusive],
]);

test('the section offers the class and subject to work on', function () {
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => null,
    ]);

    scoreEntryExamination($school);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.index', $school))
        ->assertOk()
        ->assertSee('name="class_name"', false)
        ->assertSee('name="subject"', false)
        ->assertSee('SS 3 Science')
        ->assertSee('Mathematics')
        ->assertSee('Physics');
});

test('choosing a subject narrows the list to it', function () {
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => null,
    ]);

    scoreEntryExamination($school);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.index', $school).'?subject=Physics')
        ->assertOk()
        ->assertSee('Physics')
        ->assertDontSee('Enter Scores</a>', false);
});

test('a filter cannot be used to reach a class the teacher is not assigned to', function () {
    // The filter narrows what is already theirs; it never widens it.
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::SubjectTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => 'Mathematics',
    ]);

    scoreEntryExamination($school);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.index', $school).'?subject=Physics')
        ->assertOk()
        ->assertDontSee('Enter Scores</a>', false);
});

test('an existing mark can be changed, and the total follows', function () {
    [$school, $teacher] = scoreEntrySchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'SS 3 Science',
        'subject' => null,
    ]);

    $examination = scoreEntryExamination($school);
    $subject = $examination->subjects()->first();
    $student = Student::where('school_id', $school->id)->first();

    $url = route('staff.exams.scores.update', [$school, $examination, $subject]);

    $this->actingAs($teacher, 'staff')->put($url, [
        'test_scores' => [$student->id => 30],
        'exam_scores' => [$student->id => 40],
    ])->assertRedirect();

    // Corrected afterwards, which is the ordinary case - a mark was misread.
    $this->actingAs($teacher, 'staff')->put($url, [
        'test_scores' => [$student->id => 35],
        'exam_scores' => [$student->id => 55],
    ])->assertRedirect();

    $score = $subject->scores()->where('student_id', $student->id)->firstOrFail();

    expect($subject->scores()->count())->toBe(1)
        ->and((float) $score->test_score)->toBe(35.0)
        ->and((float) $score->score)->toBe(90.0);
});
