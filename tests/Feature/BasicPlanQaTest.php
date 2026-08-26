<?php

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\ResultCheckingPin;
use App\Models\ResultCheckingPinUsage;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Basic-plan QA: the whole workflow, and the ways it can be abused.
 *
 * Two things are checked here that the feature tests elsewhere do not.
 *
 * The first is the END-TO-END path - school, teacher, student, assignment,
 * attendance, marks, calculation, token, link, parent, print - as one
 * continuous run. Each step passes on its own in its own test file; this is
 * the test that says they still fit together.
 *
 * The second is BYPASS. Every limit that exists must hold when the request is
 * crafted by hand rather than by the form, because a restriction that only
 * lives in the browser is not a restriction.
 */
function qaBasicSchool(int $studentLicences = 100): School
{
    $school = School::factory()->create(['name' => 'Greenfield College']);
    activateSchool($school, PlanKey::Basic);

    Subscription::where('school_id', $school->id)->update(['students_count' => $studentLicences]);

    return $school->fresh();
}

function qaSchoolAdmin(School $school): User
{
    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

function qaTeacher(School $school): Staff
{
    return Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
        'must_change_password' => false,
    ]);
}

// -----------------------------------------------------------------------------
// 20. The whole Basic workflow, start to finish
// -----------------------------------------------------------------------------

test('the complete Basic-plan workflow runs end to end', function () {
    $school = qaBasicSchool();
    $admin = qaSchoolAdmin($school);

    // --- School Admin creates a teacher -------------------------------------
    $this->actingAs($admin)->post(route('staff.store'), [
        'first_name' => 'John',
        'last_name' => 'Smith',
        'staff_number' => 'STF-001',
        'gender' => Gender::Male->value,
        'role' => StaffRole::Teacher->value,
    ])->assertSessionHasNoErrors();

    // Looked up by the person, not the posted ID: the Staff ID is generated
    // by the system now and a posted one is ignored.
    $teacher = Staff::where('school_id', $school->id)->where('last_name', 'Smith')->firstOrFail();
    $teacher->update(['must_change_password' => false]);

    // --- ...and a student ---------------------------------------------------
    $this->actingAs($admin)->post(route('students.store'), [
        'first_name' => 'Ada',
        'last_name' => 'Okoro',
        'admission_number' => 'ADM-001',
        'gender' => Gender::Female->value,
        'class_name' => 'JSS 1',
        'date_of_birth' => '2012-04-01',
    ])->assertSessionHasNoErrors();

    $student = Student::where('admission_number', 'ADM-001')->firstOrFail();

    // --- Class and subject assignment ---------------------------------------
    $this->actingAs($admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $teacher->uuid,
        'type' => TeacherAssignmentType::ClassTeacher->value,
        'class_name' => 'JSS 1',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $teacher->uuid,
        'type' => TeacherAssignmentType::SubjectTeacher->value,
        'class_name' => 'JSS 1',
        'subject' => 'Mathematics',
    ])->assertSessionHasNoErrors();

    // --- Teacher reaches their own dashboard --------------------------------
    $this->actingAs($teacher, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertOk();

    // --- Attendance ---------------------------------------------------------
    AcademicTerm::factory()->create([
        'school_id' => $school->id,
        'session' => '2026/2027',
        'term' => 'first',
        'starts_on' => now()->subMonth()->startOfDay(),
        'ends_on' => now()->addMonth()->startOfDay(),
    ]);

    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.store', $school), [
        'class_name' => 'JSS 1',
        'date' => now()->subDay()->toDateString(),
        'records' => [$student->id => AttendanceStatus::Present->value],
    ])->assertSessionHasNoErrors();

    // --- Examination and marks ----------------------------------------------
    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'term' => 'first',
        'session' => '2026/2027',
    ]);

    $subject = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'name' => 'Mathematics',
        'max_score' => 100,
    ]);

    // 35 of 40 on the test and 53 of 60 on the exam. This app mandates a
    // 40/60 split rather than letting each subject set its own, so the
    // components differ from the 20/80 in the brief - the total, and the point
    // being made, are the same.
    $this->actingAs($teacher, 'staff')->put(
        route('staff.exams.scores.update', [$school, $examination, $subject]),
        [
            'test_scores' => [$student->id => 35],
            'exam_scores' => [$student->id => 53],
        ],
    )->assertSessionHasNoErrors();

    // --- The engine calculates, nobody types a total ------------------------
    $score = $subject->scores()->where('student_id', $student->id)->firstOrFail();

    expect((float) $score->score)->toBe(88.0)
        ->and($score->percentage())->toBe(88.0);

    // --- School Admin issues the result token -------------------------------
    $this->actingAs($admin)->post(route('result-pins.store'), [
        'student_id' => $student->id,
        'examination_id' => $examination->id,
    ])->assertSessionHasNoErrors();

    $token = collect(session('issued_tokens'))->first()['token'];

    // --- Parent: school link, then token, with no account at all ------------
    $link = $school->fresh()->result_link_slug;

    $this->get("/{$link}/result")->assertOk()->assertSee('School ID / Admission Number');

    // Step one: name the pupil, and see them confirmed before spending a token.
    $this->post("/{$link}/result/identify", ['admission_number' => $student->admission_number])
        ->assertRedirect("/{$link}/result/confirm");

    $this->get("/{$link}/result/confirm")->assertOk()->assertSee($student->fullName());

    $this->post("/{$link}/result", ['code' => $token])->assertRedirect();

    $usage = ResultCheckingPinUsage::latest('id')->firstOrFail();

    // --- The result, with the calculated figures and a way to print it ------
    $this->get("/{$link}/result/view/{$usage->uuid}")
        ->assertOk()
        ->assertSee('Ada')
        ->assertSee('88')
        ->assertSee('Print Result');
});

// -----------------------------------------------------------------------------
// 5. The student licence limit holds against a hand-crafted request
// -----------------------------------------------------------------------------

test('a Basic school can fill its allocation exactly', function () {
    $school = qaBasicSchool(3);
    $admin = qaSchoolAdmin($school);

    Student::factory()->count(2)->create(['school_id' => $school->id, 'is_active' => true]);

    $this->actingAs($admin)->post(route('students.store'), [
        'first_name' => 'Third',
        'last_name' => 'Student',
        'admission_number' => 'ADM-003',
        'gender' => Gender::Female->value,
        'class_name' => 'JSS 1',
    ])->assertSessionHasNoErrors();

    expect($school->students()->where('is_active', true)->count())->toBe(3);
});

test('the student after the last licence is refused, with a message that says why', function () {
    $school = qaBasicSchool(3);
    $admin = qaSchoolAdmin($school);

    Student::factory()->count(3)->create(['school_id' => $school->id, 'is_active' => true]);

    $this->actingAs($admin)->post(route('students.store'), [
        'first_name' => 'Fourth',
        'last_name' => 'Student',
        'admission_number' => 'ADM-004',
        'gender' => Gender::Female->value,
        'class_name' => 'JSS 1',
    ])->assertSessionHasErrors('admission_number');

    expect(session('errors')->first('admission_number'))
        ->toContain('Student/Pupil Capacity Reached')
        ->toContain('additional payment');

    expect($school->students()->where('is_active', true)->count())->toBe(3);
});

test('reactivating a student cannot push a school past its allocation', function () {
    $school = qaBasicSchool(2);
    $admin = qaSchoolAdmin($school);

    $dormant = Student::factory()->create(['school_id' => $school->id, 'is_active' => false]);
    Student::factory()->count(2)->create(['school_id' => $school->id, 'is_active' => true]);

    // Deactivate, add, reactivate is the loophole a single create-time check
    // misses entirely.
    $this->actingAs($admin)->put(route('students.update', $dormant), [
        'first_name' => $dormant->first_name,
        'last_name' => $dormant->last_name,
        'admission_number' => $dormant->admission_number,
        'gender' => $dormant->gender->value,
        'class_name' => $dormant->class_name,
        'is_active' => '1',
    ]);

    expect($school->students()->where('is_active', true)->count())->toBe(2);
});

// -----------------------------------------------------------------------------
// 7. Score boundaries, checked on the server
// -----------------------------------------------------------------------------

test('a score above the configured maximum is refused', function (mixed $test, mixed $exam, string $field) {
    $school = qaBasicSchool();
    $teacher = qaTeacher($school);

    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::SubjectTeacher,
        'class_name' => 'JSS 1',
        'subject' => $subject->name,
    ]);

    // Crafted by hand, not by the form - the browser's own max attribute is
    // not a restriction, it is a hint.
    $this->actingAs($teacher, 'staff')->put(
        route('staff.exams.scores.update', [$school, $examination, $subject]),
        ['test_scores' => [$student->id => $test], 'exam_scores' => [$student->id => $exam]],
    )->assertSessionHasErrors($field);

    expect($subject->scores()->count())->toBe(0);
})->with([
    'test above max' => [999, 10, 'test_scores.*'],
    'exam above max' => [10, 999, 'exam_scores.*'],
    'negative test' => [-1, 10, 'test_scores.*'],
    'negative exam' => [10, -1, 'exam_scores.*'],
    'letters' => ['abc', 10, 'test_scores.*'],
]);

test('the boundary values themselves are accepted', function () {
    $school = qaBasicSchool();
    $teacher = qaTeacher($school);

    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::SubjectTeacher,
        'class_name' => 'JSS 1',
        'subject' => $subject->name,
    ]);

    // Exactly the maximum on both halves - the off-by-one that a naive
    // "less than max" check gets wrong.
    $this->actingAs($teacher, 'staff')->put(
        route('staff.exams.scores.update', [$school, $examination, $subject]),
        [
            'test_scores' => [$student->id => $subject->testMaxScore()],
            'exam_scores' => [$student->id => $subject->examMaxScore()],
        ],
    )->assertSessionHasNoErrors();

    expect((float) $subject->scores()->first()->score)
        ->toBe((float) $subject->testMaxScore() + (float) $subject->examMaxScore());
});

test('a teacher cannot enter marks for a subject they were never assigned', function () {
    $school = qaBasicSchool();
    $teacher = qaTeacher($school);

    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    // No assignment at all. Permissions follow responsibilities, so this is a
    // refusal rather than a silent no-op.
    $this->actingAs($teacher, 'staff')->put(
        route('staff.exams.scores.update', [$school, $examination, $subject]),
        ['test_scores' => [$student->id => 10], 'exam_scores' => [$student->id => 10]],
    )->assertForbidden();

    expect($subject->scores()->count())->toBe(0);
});

// -----------------------------------------------------------------------------
// 4 / 12. Cross-school bypass attempts
// -----------------------------------------------------------------------------

test('a school admin cannot create a student inside another school', function () {
    $schoolA = qaBasicSchool();
    $schoolB = qaBasicSchool();
    $adminA = qaSchoolAdmin($schoolA);

    // A school_id in the payload is not authority - the acting user's own
    // school is the only one that counts.
    $this->actingAs($adminA)->post(route('students.store'), [
        'first_name' => 'Planted',
        'last_name' => 'Student',
        'admission_number' => 'ADM-X',
        'gender' => Gender::Female->value,
        'class_name' => 'JSS 1',
        'school_id' => $schoolB->id,
    ]);

    expect($schoolB->students()->count())->toBe(0)
        ->and($schoolA->students()->where('admission_number', 'ADM-X')->count())->toBe(1);
});

test('a teacher cannot mark attendance for a class they do not teach', function () {
    $school = qaBasicSchool();
    $teacher = qaTeacher($school);

    TeacherAssignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'JSS 1',
    ]);

    $otherClassStudent = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'SS 3',
        'is_active' => true,
    ]);

    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.store', $school), [
        'class_name' => 'SS 3',
        'date' => now()->subDay()->toDateString(),
        'records' => [$otherClassStudent->id => AttendanceStatus::Present->value],
    ])->assertSessionHasErrors('class_name');
});

// -----------------------------------------------------------------------------
// 17. Query efficiency on the pages that grow
// -----------------------------------------------------------------------------

test('the result token screen does not issue a query per token', function () {
    $school = qaBasicSchool();
    $admin = qaSchoolAdmin($school);

    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);

    $issueTokens = function (int $count) use ($school, $examination) {
        foreach (range(1, $count) as $i) {
            $student = Student::factory()->create([
                'school_id' => $school->id,
                'class_name' => 'JSS 1',
                'is_active' => true,
            ]);

            ResultCheckingPin::factory()->create([
                'school_id' => $school->id,
                'bound_student_id' => $student->id,
                'examination_id' => $examination->id,
            ]);
        }
    };

    $countQueries = function () use ($admin) {
        // Flushed first: enableQueryLog() APPENDS to whatever is already
        // there, so a second measurement without this silently counts the
        // first one again - which looks exactly like an N+1 that is not
        // there.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('result-pins.index'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $issueTokens(15);
    $withFifteen = $countQueries();

    $issueTokens(15);
    $withThirty = $countQueries();

    // The property that actually matters, asserted as a property rather than
    // as a magic number: doubling the rows does not ADD queries. A per-row
    // lookup would show up here as fifteen more, whatever number the budget
    // happened to be set to.
    //
    // Not equality - the very first request of a test does one-off work that
    // later ones do not (seeding platform settings, warming the container),
    // so the second reading is legitimately a little lower.
    expect($withThirty)->toBeLessThanOrEqual($withFifteen);

    // And a ceiling, so an unbounded pile of constant queries is still caught.
    expect($withThirty)->toBeLessThan(45);
});

test('the token screen is paginated rather than loading every token', function () {
    $school = qaBasicSchool();
    $admin = qaSchoolAdmin($school);

    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);

    ResultCheckingPin::factory()->count(45)->create([
        'school_id' => $school->id,
        'examination_id' => $examination->id,
    ]);

    $response = $this->actingAs($admin)->get(route('result-pins.index'));

    expect($response->viewData('tokens')->count())->toBeLessThanOrEqual(20)
        ->and($response->viewData('tokens')->total())->toBe(45);
});

// -----------------------------------------------------------------------------
// 18. Failures are refusals, not stack traces
// -----------------------------------------------------------------------------

test('a bad token shows a message rather than an error page', function () {
    $school = qaBasicSchool();
    $link = $school->result_link_slug;

    $student = Student::factory()->create(['school_id' => $school->id, 'is_active' => true]);
    $this->post("/{$link}/result/identify", ['admission_number' => $student->admission_number]);

    $this->from("/{$link}/result/confirm")
        ->followingRedirects()
        ->post("/{$link}/result", ['code' => 'EDU-NOPE-NOPE-NOPE-NOPE'])
        ->assertOk()
        ->assertSee('Invalid Result Token')
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('Whoops');
});

test('an empty token is refused with a usable message', function () {
    $school = qaBasicSchool();
    $link = $school->result_link_slug;

    $this->post("/{$link}/result", ['code' => ''])
        ->assertSessionHasErrors('code');

    expect(session('errors')->first('code'))->toContain('result token');
});
