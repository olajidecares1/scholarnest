<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\RepositoryResult;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;

beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Basic);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 1',
        'admission_number' => 'GRN/001',
    ]);

    $this->examination = Examination::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 1',
        'session' => '2025/2026',
        'term' => ExamTerm::First,
    ]);
});

/**
 * Give a pupil a complete mark in one subject: a test score, an exam score and
 * a total. ResultCompleteness wants all three.
 */
function gradeSubject(Examination $examination, Student $student, string $name = 'Mathematics', int $test = 30, int $exam = 42): ExaminationSubject
{
    $subject = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'name' => $name,
        'max_score' => 100,
    ]);

    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'test_score' => $test,
        'exam_score' => $exam,
        'score' => $test + $exam,
    ]);

    return $subject;
}

/**
 * A subject nobody has marked yet, the thing that blocks a push.
 */
function ungradedSubject(Examination $examination, string $name = 'English Language'): ExaminationSubject
{
    return ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'name' => $name,
        'max_score' => 100,
    ]);
}

function classTeacherOf(School $school, string $className): Staff
{
    $staff = Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
    ]);

    TeacherAssignment::factory()->create([
        'school_id' => $school->id,
        'staff_id' => $staff->id,
        'class_name' => $className,
        'type' => TeacherAssignmentType::ClassTeacher,
    ]);

    return $staff;
}

// ---------------------------------------------------------------------------
// Pushing
// ---------------------------------------------------------------------------

test('a School Admin can push a completed result, filed under the right term', function () {
    gradeSubject($this->examination, $this->student);

    $this->actingAs($this->admin)
        ->post(route('results.push', [$this->examination, $this->student]))
        ->assertRedirect();

    $record = RepositoryResult::sole();

    expect($record->school_id)->toBe($this->school->id)
        ->and($record->student_id)->toBe($this->student->id)
        ->and($record->examination_id)->toBe($this->examination->id)
        ->and($record->class_name)->toBe('JSS 1')
        ->and($record->session)->toBe('2025/2026')
        ->and($record->term)->toBe(ExamTerm::First)
        ->and($record->version)->toBe(1)
        ->and($record->pushed_by_name)->toBe($this->admin->name);
});

test('the stored card carries the marks, not a pointer to them', function () {
    gradeSubject($this->examination, $this->student, 'Mathematics', 30, 42);

    ExaminationReport::firstOrCreateFor($this->examination, $this->student)
        ->update(['teacher_remark' => 'A steady term.', 'principal_remark' => 'Well done.']);

    $this->actingAs($this->admin)->post(route('results.push', [$this->examination, $this->student]));

    $payload = RepositoryResult::sole()->payload;

    expect($payload['student']['admission_number'])->toBe('GRN/001')
        ->and($payload['subjects'][0]['name'])->toBe('Mathematics')
        ->and($payload['subjects'][0]['test_score'])->toEqual(30)
        ->and($payload['subjects'][0]['exam_score'])->toEqual(42)
        ->and($payload['subjects'][0]['score'])->toEqual(72)
        ->and($payload['subjects'][0]['grade'])->not->toBeNull()
        ->and($payload['remarks']['teacher'])->toBe('A steady term.')
        ->and($payload['remarks']['principal'])->toBe('Well done.');
});

test('an incomplete result is refused, and says which subject is missing', function () {
    gradeSubject($this->examination, $this->student, 'Mathematics');
    ungradedSubject($this->examination, 'English Language');

    $this->actingAs($this->admin)
        ->post(route('results.push', [$this->examination, $this->student]))
        ->assertSessionHasErrors(['repository' => 'No score has been recorded for English Language.']);

    expect(RepositoryResult::count())->toBe(0);
});

test('an examination with no subjects has nothing to publish', function () {
    $this->actingAs($this->admin)
        ->post(route('results.push', [$this->examination, $this->student]))
        ->assertSessionHasErrors('repository');

    expect(RepositoryResult::count())->toBe(0);
});

test('a total with no test or exam mark behind it is refused', function () {
    $subject = ExaminationSubject::factory()->create([
        'examination_id' => $this->examination->id,
        'name' => 'Mathematics',
    ]);

    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $this->student->id,
        'test_score' => null,
        'exam_score' => null,
        'score' => 72,
    ]);

    $this->actingAs($this->admin)
        ->post(route('results.push', [$this->examination, $this->student]))
        ->assertSessionHasErrors('repository');

    expect(RepositoryResult::count())->toBe(0);
});

test('pushing again updates the same record instead of duplicating it', function () {
    gradeSubject($this->examination, $this->student, 'Mathematics', 30, 42);

    $this->actingAs($this->admin)->post(route('results.push', [$this->examination, $this->student]));
    $this->actingAs($this->admin)->post(route('results.push', [$this->examination, $this->student]));

    expect(RepositoryResult::count())->toBe(1)
        ->and(RepositoryResult::sole()->version)->toBe(2);
});

test('a correction reaches the repository only when it is pushed again', function () {
    // The whole point of storing the card rather than pointing at the marks.
    $subject = gradeSubject($this->examination, $this->student, 'Mathematics', 30, 42);

    $this->actingAs($this->admin)->post(route('results.push', [$this->examination, $this->student]));

    ExaminationScore::where('examination_subject_id', $subject->id)
        ->where('student_id', $this->student->id)
        ->update(['test_score' => 10, 'exam_score' => 20, 'score' => 30]);

    expect(RepositoryResult::sole()->payload['subjects'][0]['score'])->toEqual(72);

    $this->actingAs($this->admin)->post(route('results.push', [$this->examination, $this->student]));

    expect(RepositoryResult::sole()->payload['subjects'][0]['score'])->toEqual(30);
});

test('pushing a class publishes the ready ones and leaves the rest', function () {
    $ready = $this->student;
    $notReady = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $subject = gradeSubject($this->examination, $ready, 'Mathematics');

    // The second pupil has no score for that subject at all.
    expect(ExaminationScore::where('student_id', $notReady->id)->count())->toBe(0);

    $this->actingAs($this->admin)
        ->post(route('results.push-class', $this->examination))
        ->assertRedirect();

    expect(RepositoryResult::count())->toBe(1)
        ->and(RepositoryResult::sole()->student_id)->toBe($ready->id)
        ->and($subject->fresh())->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Who may push
// ---------------------------------------------------------------------------

test('a Class Teacher can push their own class', function () {
    gradeSubject($this->examination, $this->student);

    $teacher = classTeacherOf($this->school, 'JSS 1');

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.results.push', [$this->school, $this->examination, $this->student]))
        ->assertRedirect();

    expect(RepositoryResult::sole()->pushed_by_type)->toBe((new Staff)->getMorphClass());
});

test('a teacher cannot push a class they are not the Class Teacher of', function () {
    gradeSubject($this->examination, $this->student);

    $otherClassTeacher = classTeacherOf($this->school, 'JSS 3');

    $this->actingAs($otherClassTeacher, 'staff')
        ->post(route('staff.results.push', [$this->school, $this->examination, $this->student]))
        ->assertForbidden();

    expect(RepositoryResult::count())->toBe(0);
});

test('a School Admin cannot push another school\'s result', function () {
    $otherSchool = activateSchool(School::factory()->create(), PlanKey::Basic);
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $otherSchool->id]);

    gradeSubject($this->examination, $this->student);

    $this->actingAs($otherAdmin)
        ->post(route('results.push', [$this->examination, $this->student]))
        ->assertForbidden();

    expect(RepositoryResult::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// The Repository page
// ---------------------------------------------------------------------------

test('the School Admin sees their own school\'s stored results and no others', function () {
    gradeSubject($this->examination, $this->student, 'Mathematics');
    $this->actingAs($this->admin)->post(route('results.push', [$this->examination, $this->student]));

    // Another school, its own pupil, its own published result.
    $otherSchool = activateSchool(School::factory()->create(), PlanKey::Basic);
    $stranger = Student::factory()->create([
        'school_id' => $otherSchool->id,
        'class_name' => 'JSS 1',
        'first_name' => 'Chidinma',
        'last_name' => 'Obi',
    ]);
    RepositoryResult::factory()->create([
        'school_id' => $otherSchool->id,
        'student_id' => $stranger->id,
        'class_name' => 'JSS 1',
        'session' => '2025/2026',
        'term' => ExamTerm::First,
    ]);

    $this->actingAs($this->admin)
        ->get(route('result-repository.index', ['class' => 'JSS 1', 'session' => '2025/2026', 'term' => 'first']))
        ->assertOk()
        ->assertSee($this->student->fullName())
        ->assertDontSee('Chidinma');
});

test('the repository page filters by class, academic year and term', function () {
    gradeSubject($this->examination, $this->student, 'Mathematics');
    $this->actingAs($this->admin)->post(route('results.push', [$this->examination, $this->student]));

    // A term the school has published nothing for.
    $this->actingAs($this->admin)
        ->get(route('result-repository.index', ['class' => 'JSS 1', 'session' => '2025/2026', 'term' => 'third']))
        ->assertOk()
        ->assertDontSee($this->student->admission_number);

    $this->actingAs($this->admin)
        ->get(route('result-repository.index', ['class' => 'JSS 1', 'session' => '2025/2026', 'term' => 'first']))
        ->assertOk()
        ->assertSee($this->student->admission_number);
});

test('a teacher has no route to the Repository page', function () {
    // Refused outright rather than redirected to a sign-in they already have.
    // Publishing into the repository and administering it are different jobs.
    $teacher = classTeacherOf($this->school, 'JSS 1');

    $this->actingAs($teacher, 'staff')
        ->get(route('result-repository.index'))
        ->assertForbidden();
});

test('a signed-out visitor cannot reach the Repository page', function () {
    $this->get(route('result-repository.index'))->assertRedirect();
});

test('one school cannot open another school\'s stored card', function () {
    $otherSchool = activateSchool(School::factory()->create(), PlanKey::Basic);
    $theirRecord = RepositoryResult::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->getJson(route('result-repository.show', $theirRecord))
        ->assertForbidden();
});
