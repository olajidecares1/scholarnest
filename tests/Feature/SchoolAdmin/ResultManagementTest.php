<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\GradeBand;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Subscription;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Support\AcademicSession;

/**
 * A school with an admin, a teacher and a class of pupils.
 *
 * @return array{0: School, 1: User, 2: Staff}
 */
function resultSchool(PlanKey $planKey = PlanKey::Basic): array
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

    $admin = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::SchoolAdmin]);
    $teacher = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher]);

    Student::factory()->count(3)->create([
        'school_id' => $school->id,
        'class_name' => 'Primary 4',
        'is_active' => true,
    ]);

    return [$school, $admin, $teacher];
}

function resultExamination(School $school, string $className = 'Primary 4'): Examination
{
    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => $className,
        'session' => '2026/2027',
        'term' => ExamTerm::First,
    ]);

    foreach (['Mathematics', 'English Language'] as $name) {
        $examination->subjects()->create(['name' => $name, 'max_score' => 100]);
    }

    return $examination;
}

// -----------------------------------------------------------------------------
// 1. A teacher is not limited to one subject or one class.
// -----------------------------------------------------------------------------

test('a teacher can be assigned several subjects', function () {
    [$school, $admin, $teacher] = resultSchool();

    foreach (['Mathematics', 'English Language', 'Basic Science'] as $subject) {
        $this->actingAs($admin)->post(route('teacher-assignments.store'), [
            'staff_uuid' => $teacher->uuid,
            'type' => TeacherAssignmentType::SubjectTeacher->value,
            'class_name' => 'Primary 4',
            'subject' => $subject,
        ])->assertRedirect();
    }

    expect($teacher->subjectAssignments())->toHaveCount(3)
        ->and($teacher->subjectAssignments()->pluck('subject')->all())
        ->toContain('Mathematics', 'English Language', 'Basic Science');
});

test('a teacher can be class teacher of several classes at once', function () {
    // Primary 4 and Primary 5 together, being given a second class must not
    // quietly take the first one away.
    [$school, $admin, $teacher] = resultSchool();

    foreach (['Primary 4', 'Primary 5'] as $className) {
        $this->actingAs($admin)->post(route('teacher-assignments.store'), [
            'staff_uuid' => $teacher->uuid,
            'type' => TeacherAssignmentType::ClassTeacher->value,
            'class_name' => $className,
        ])->assertRedirect();
    }

    expect($teacher->scorableClassNames()->all())->toContain('Primary 4', 'Primary 5');
});

test('a class only ever has one class teacher', function () {
    // The other side of the same rule: many classes per teacher, but handing a
    // class to somebody else moves it rather than adding a second holder.
    [$school, $admin, $teacher] = resultSchool();
    $other = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher]);

    foreach ([$teacher, $other] as $person) {
        $this->actingAs($admin)->post(route('teacher-assignments.store'), [
            'staff_uuid' => $person->uuid,
            'type' => TeacherAssignmentType::ClassTeacher->value,
            'class_name' => 'Primary 4',
        ])->assertRedirect();
    }

    $holders = TeacherAssignment::where('school_id', $school->id)
        ->where('class_name', 'Primary 4')
        ->where('type', TeacherAssignmentType::ClassTeacher)
        ->get();

    expect($holders)->toHaveCount(1)
        ->and($holders->first()->staff_id)->toBe($other->id);
});

test('subject and class-teacher assignments are managed independently', function () {
    [$school, $admin, $teacher] = resultSchool();

    $this->actingAs($admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $teacher->uuid,
        'type' => TeacherAssignmentType::ClassTeacher->value,
        'class_name' => 'Primary 4',
    ])->assertRedirect();

    $subjectRow = TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::SubjectTeacher,
        'class_name' => 'Primary 5',
        'subject' => 'Mathematics',
    ]);

    // Removing the subject row leaves the class-teacher row alone.
    $this->actingAs($admin)->delete(route('teacher-assignments.destroy', $subjectRow))->assertRedirect();

    expect($teacher->subjectAssignments())->toHaveCount(0)
        ->and($teacher->scorableClassNames()->all())->toContain('Primary 4');
});

// -----------------------------------------------------------------------------
// 2. The admin enters scores by class, year, term and subject.
// -----------------------------------------------------------------------------

test('the admin reaches score entry by choosing class, year, term and subject', function () {
    [$school, $admin] = resultSchool();
    $examination = resultExamination($school);

    $this->actingAs($admin)
        ->get(route('examinations.score-entry', [
            'class_name' => 'Primary 4',
            'session' => '2026/2027',
            'term' => ExamTerm::First->value,
            'subject' => 'Mathematics',
        ]))
        ->assertOk()
        ->assertSee('Test / 40')
        ->assertSee('Exam / 60')
        ->assertSee(Student::where('school_id', $school->id)->first()->fullName());
});

test('the admin needs no assignment to enter scores for a class', function () {
    // Unlike a teacher, an administrator is responsible for every class, so
    // nothing is narrowed by assignment.
    [$school, $admin] = resultSchool();
    resultExamination($school);

    expect(TeacherAssignment::where('school_id', $school->id)->count())->toBe(0);

    $this->actingAs($admin)
        ->get(route('examinations.score-entry', [
            'class_name' => 'Primary 4',
            'session' => '2026/2027',
            'term' => ExamTerm::First->value,
        ]))
        ->assertOk()
        ->assertSee('Mathematics');
});

test('the admin is told plainly when no examination exists for that period', function () {
    [$school, $admin] = resultSchool();

    $this->actingAs($admin)
        ->get(route('examinations.score-entry', [
            'class_name' => 'Primary 4',
            'session' => '2026/2027',
            'term' => ExamTerm::Third->value,
        ]))
        ->assertOk()
        ->assertSee('No examination exists yet for Primary 4');
});

test('the admin cannot enter scores into another school\'s subject', function () {
    [$school, $admin] = resultSchool();
    [$otherSchool] = resultSchool();

    $subject = resultExamination($otherSchool)->subjects()->first();

    $this->actingAs($admin)
        ->get(route('examinations.scores', $subject))
        ->assertForbidden();
});

// -----------------------------------------------------------------------------
// 3. Everything teachers enter shows up for the admin.
// -----------------------------------------------------------------------------

test('marks a teacher entered are visible to the admin by class, year and term', function () {
    [$school, $admin, $teacher] = resultSchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'Primary 4',
        'subject' => null,
    ]);

    $examination = resultExamination($school);
    $subject = $examination->subjects()->first();
    $student = Student::where('school_id', $school->id)->first();

    $this->actingAs($teacher, 'staff')->put(
        route('staff.exams.scores.update', [$school, $examination, $subject]),
        ['test_scores' => [$student->id => 32], 'exam_scores' => [$student->id => 51]],
    )->assertRedirect();

    // The admin selects only class, year and term, no hunting through the
    // teacher's account.
    $this->actingAs($admin)
        ->get(route('results.index', [
            'class' => 'Primary 4',
            'session' => '2026/2027',
            'term' => ExamTerm::First->value,
        ]))
        ->assertOk()
        ->assertSee($student->fullName());

    $score = $subject->scores()->where('student_id', $student->id)->firstOrFail();

    expect((float) $score->score)->toBe(83.0)
        ->and($score->subject->examination->school_id)->toBe($school->id);
});

// -----------------------------------------------------------------------------
// 4 & 5. Two remarks, kept apart.
// -----------------------------------------------------------------------------

test('the class teacher writes their own remark on a pupil\'s result', function () {
    [$school, $admin, $teacher] = resultSchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'Primary 4',
        'subject' => null,
    ]);

    $examination = resultExamination($school);
    $student = Student::where('school_id', $school->id)->first();

    $this->actingAs($teacher, 'staff')->putJson(
        route('staff.results.remarks', [$school, $examination, $student]),
        ['teacher_remark' => 'A steady term. Reading has come on well.'],
    )->assertOk();

    $report = ExaminationReport::firstOrCreateFor($examination, $student);

    expect($report->teacher_remark)->toBe('A steady term. Reading has come on well.')
        ->and($report->principal_remark)->toBeNull();
});

test('the principal\'s remark is stored separately from the teacher\'s', function () {
    [$school, $admin] = resultSchool();
    $examination = resultExamination($school);
    $student = Student::where('school_id', $school->id)->first();

    $this->actingAs($admin)->put(
        route('results.remarks', [$examination, $student]),
        [
            'teacher_remark' => 'A steady term.',
            'principal_remark' => 'Keep it up next term.',
        ],
    )->assertRedirect();

    $report = ExaminationReport::firstOrCreateFor($examination, $student);

    expect($report->teacher_remark)->toBe('A steady term.')
        ->and($report->principal_remark)->toBe('Keep it up next term.');
});

test('a teacher cannot write the principal\'s remark', function () {
    [$school, $admin, $teacher] = resultSchool();

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'Primary 4',
        'subject' => null,
    ]);

    $examination = resultExamination($school);
    $student = Student::where('school_id', $school->id)->first();

    $this->actingAs($teacher, 'staff')->putJson(
        route('staff.results.remarks', [$school, $examination, $student]),
        [
            'teacher_remark' => 'A steady term.',
            'principal_remark' => 'I am not the principal.',
        ],
    )->assertOk();

    $report = ExaminationReport::firstOrCreateFor($examination, $student);

    expect($report->teacher_remark)->toBe('A steady term.')
        ->and($report->principal_remark)->toBeNull();
});

// -----------------------------------------------------------------------------
// 6. Each school's own grading scale.
// -----------------------------------------------------------------------------

test('a school with no scale of its own uses the built-in one', function () {
    [$school] = resultSchool();

    expect(GradeBand::resolve($school->fresh(), 85.0))->toBe('A')
        ->and(GradeBand::resolve($school->fresh(), 45.0))->toBe('D');
});

test('a school\'s own scale replaces the built-in one entirely', function () {
    [$school, $admin] = resultSchool();

    foreach ([[75, 100, 'A', 'Excellent'], [50, 74, 'B', 'Very Good'], [0, 49, 'F', 'Fail']] as [$min, $max, $letter, $description]) {
        $this->actingAs($admin)->post(route('academics.grade-bands.store'), [
            'min_percent' => $min,
            'max_percent' => $max,
            'letter' => $letter,
            'description' => $description,
        ])->assertRedirect();
    }

    $school = $school->fresh();

    // 72% is a B here and would have been an A on the built-in scale.
    expect(GradeBand::resolve($school, 72.0))->toBe('B')
        ->and(GradeBand::describe($school, 72.0))->toBe('Very Good')
        ->and(GradeBand::resolve($school, 30.0))->toBe('F');
});

test('one school\'s grading never reaches another school\'s pupils', function () {
    [$schoolA, $adminA] = resultSchool();
    [$schoolB] = resultSchool();

    $this->actingAs($adminA)->post(route('academics.grade-bands.store'), [
        'min_percent' => 0,
        'max_percent' => 100,
        'letter' => 'P',
        'description' => 'Pass',
    ])->assertRedirect();

    expect(GradeBand::resolve($schoolA->fresh(), 85.0))->toBe('P')
        ->and(GradeBand::resolve($schoolB->fresh(), 85.0))->toBe('A');
});

test('a school that configures part of a scale is not silently graded on the built-in one', function () {
    // The defect this covers: bands covering only 50-100 left every failing
    // mark graded on a scale the school never chose, with nothing on the page
    // saying so.
    [$school, $admin] = resultSchool();

    $this->actingAs($admin)->post(route('academics.grade-bands.store'), [
        'min_percent' => 50,
        'max_percent' => 100,
        'letter' => 'P',
        'description' => 'Pass',
    ])->assertRedirect();

    $school = $school->fresh();

    expect(GradeBand::resolve($school, 80.0))->toBe('P')
        ->and(GradeBand::resolve($school, 30.0))->toBe('N/A');
});

test('the grading page names the ranges the school has not covered', function () {
    [$school, $admin] = resultSchool();

    $this->actingAs($admin)->post(route('academics.grade-bands.store'), [
        'min_percent' => 50,
        'max_percent' => 100,
        'letter' => 'P',
        'description' => 'Pass',
    ])->assertRedirect();

    $this->actingAs($admin)
        ->get(route('academics.index'))
        ->assertOk()
        ->assertSee('does not cover')
        ->assertSee('0 to 49%', false);

    expect(GradeBand::coverageGaps($school->fresh()))->toBe([['from' => 0, 'to' => 49]]);
});

test('two grades cannot claim the same percentage', function () {
    // Overlapping bands make the grade depend on which row sorts first, which
    // is not a decision anybody made.
    [$school, $admin] = resultSchool();

    $this->actingAs($admin)->post(route('academics.grade-bands.store'), [
        'min_percent' => 70,
        'max_percent' => 100,
        'letter' => 'A',
        'description' => 'Excellent',
    ])->assertRedirect();

    $this->actingAs($admin)->post(route('academics.grade-bands.store'), [
        'min_percent' => 60,
        'max_percent' => 80,
        'letter' => 'B',
        'description' => 'Very Good',
    ])->assertSessionHasErrors('min_percent');

    expect($school->fresh()->gradeBands)->toHaveCount(1);
});

test('editing a grade does not count it as overlapping itself', function () {
    [$school, $admin] = resultSchool();

    $this->actingAs($admin)->post(route('academics.grade-bands.store'), [
        'min_percent' => 70,
        'max_percent' => 100,
        'letter' => 'A',
        'description' => 'Excellent',
    ])->assertRedirect();

    $band = $school->fresh()->gradeBands->first();

    $this->actingAs($admin)->put(route('academics.grade-bands.update', $band), [
        'min_percent' => 75,
        'max_percent' => 100,
        'letter' => 'A',
        'description' => 'Distinction',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($band->fresh()->min_percent)->toBe(75)
        ->and($band->fresh()->description)->toBe('Distinction');
});

test('an admin cannot edit another school\'s grading', function () {
    [$schoolA, $adminA] = resultSchool();
    [$schoolB, $adminB] = resultSchool();

    $this->actingAs($adminB)->post(route('academics.grade-bands.store'), [
        'min_percent' => 0,
        'max_percent' => 100,
        'letter' => 'P',
        'description' => 'Pass',
    ])->assertRedirect();

    $band = $schoolB->fresh()->gradeBands->first();

    $this->actingAs($adminA)->put(route('academics.grade-bands.update', $band), [
        'min_percent' => 0,
        'max_percent' => 100,
        'letter' => 'Z',
        'description' => 'Tampered',
    ])->assertForbidden();

    expect($band->fresh()->letter)->toBe('P');
});

test('the marking grid grades against the school\'s own scale', function () {
    // The grid previews the grade live, and the preview has to come from the
    // same bands the server will use, or the two disagree on screen.
    [$school, $admin] = resultSchool();

    $this->actingAs($admin)->post(route('academics.grade-bands.store'), [
        'min_percent' => 0,
        'max_percent' => 100,
        'letter' => 'P',
        'description' => 'Merit Pass',
    ])->assertRedirect();

    resultExamination($school);

    $this->actingAs($admin)
        ->get(route('examinations.score-entry', [
            'class_name' => 'Primary 4',
            'session' => '2026/2027',
            'term' => ExamTerm::First->value,
            'subject' => 'Mathematics',
        ]))
        ->assertOk()
        ->assertSee('Merit Pass');
});

// -----------------------------------------------------------------------------
// The year the school is actually in.
// -----------------------------------------------------------------------------

test('the school own session wins over the calendar guess', function () {
    // AcademicSession::current() guesses from the date, September onward is
    // the new year. A school whose year turned over earlier was shown the
    // previous session on every page that defaulted from the calendar, so its
    // records looked missing when they were merely filed under the year the
    // school is in.
    $school = School::factory()->create(['current_session' => '2031/2032']);

    expect($school->currentSession())->toBe('2031/2032')
        ->and($school->currentSession())->not->toBe(AcademicSession::current());
});

test('a school with no session set falls back to the calendar', function () {
    $school = School::factory()->create(['current_session' => null]);

    expect($school->currentSession())->toBe(AcademicSession::current());
});

test('score entry opens on the school own academic year', function () {
    [$school, $admin] = resultSchool();
    $school->update(['current_session' => '2026/2027']);

    resultExamination($school);

    // No session in the query string, it has to default to the school's.
    $this->actingAs($admin)
        ->get(route('examinations.score-entry', [
            'class_name' => 'Primary 4',
            'term' => ExamTerm::First->value,
        ]))
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertDontSee('No examination exists yet');
});

// -----------------------------------------------------------------------------
// A missing examination is something to fix here, not somewhere else.
// -----------------------------------------------------------------------------

test('a missing examination offers to create itself from the class subjects', function () {
    [$school, $admin] = resultSchool();
    $school->update(['current_session' => '2026/2027']);

    $subject = Subject::firstOrCreate(['name' => 'Mathematics'], ['category' => 'general']);
    SubjectOffering::create([
        'school_id' => $school->id,
        'class_name' => 'Primary 4',
        'subject_id' => $subject->id,
    ]);

    $this->actingAs($admin)
        ->get(route('examinations.score-entry', [
            'class_name' => 'Primary 4',
            'session' => '2026/2027',
            'term' => ExamTerm::First->value,
        ]))
        ->assertOk()
        ->assertSee('No examination exists yet')
        ->assertSee('Create it and start entering scores');
});

test('creating it from that page lands straight on a usable marking grid', function () {
    [$school, $admin] = resultSchool();

    foreach (['Mathematics', 'English Language'] as $name) {
        $subject = Subject::firstOrCreate(['name' => $name], ['category' => 'general']);
        SubjectOffering::create([
            'school_id' => $school->id,
            'class_name' => 'Primary 4',
            'subject_id' => $subject->id,
        ]);
    }

    $this->actingAs($admin)
        ->post(route('examinations.score-entry.create'), [
            'class_name' => 'Primary 4',
            'session' => '2026/2027',
            'term' => ExamTerm::First->value,
        ])
        ->assertRedirect();

    $examination = Examination::where('school_id', $school->id)->firstOrFail();

    expect($examination->class_name)->toBe('Primary 4')
        ->and($examination->session)->toBe('2026/2027')
        ->and($examination->subjects()->count())->toBe(2)
        ->and($examination->subjects()->first()->max_score)->toBe(100);

    // And the grid it lands on is the real one.
    $this->actingAs($admin)
        ->get(route('examinations.score-entry', [
            'class_name' => 'Primary 4',
            'session' => '2026/2027',
            'term' => ExamTerm::First->value,
        ]))
        ->assertOk()
        ->assertSee('Test / 40')
        ->assertSee('Exam / 60')
        ->assertSee('Total / 100');
});

test('creating it twice does not duplicate the examination', function () {
    [$school, $admin] = resultSchool();

    $subject = Subject::firstOrCreate(['name' => 'Mathematics'], ['category' => 'general']);
    SubjectOffering::create([
        'school_id' => $school->id,
        'class_name' => 'Primary 4',
        'subject_id' => $subject->id,
    ]);

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($admin)->post(route('examinations.score-entry.create'), [
            'class_name' => 'Primary 4',
            'session' => '2026/2027',
            'term' => ExamTerm::First->value,
        ])->assertRedirect();
    }

    expect(Examination::where('school_id', $school->id)->count())->toBe(1)
        ->and(Examination::where('school_id', $school->id)->first()->subjects()->count())->toBe(1);
});

test('a class with no subjects says so rather than making an empty paper', function () {
    [$school, $admin] = resultSchool();

    $this->actingAs($admin)
        ->post(route('examinations.score-entry.create'), [
            'class_name' => 'Primary 4',
            'session' => '2026/2027',
            'term' => ExamTerm::First->value,
        ])
        ->assertSessionHasErrors('class_name');

    expect(Examination::where('school_id', $school->id)->count())->toBe(0);
});

test('the created examination belongs to the admin own school', function () {
    [$schoolA, $adminA] = resultSchool();
    [$schoolB] = resultSchool();

    $subject = Subject::firstOrCreate(['name' => 'Mathematics'], ['category' => 'general']);
    SubjectOffering::create([
        'school_id' => $schoolA->id,
        'class_name' => 'Primary 4',
        'subject_id' => $subject->id,
    ]);

    $this->actingAs($adminA)->post(route('examinations.score-entry.create'), [
        'class_name' => 'Primary 4',
        'session' => '2026/2027',
        'term' => ExamTerm::First->value,
    ])->assertRedirect();

    expect(Examination::where('school_id', $schoolA->id)->count())->toBe(1)
        ->and(Examination::where('school_id', $schoolB->id)->count())->toBe(0);
});
