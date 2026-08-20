<?php

use App\Enums\AttendanceStatus;
use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ResultAvailableNotification;
use App\Services\DefaultAcademicStructure;
use App\Support\AcademicSession;
use Illuminate\Support\Facades\Notification;

function resultSchoolAdmin(PlanKey $planKey = PlanKey::Basic): User
{
    $school = School::factory()->create();
    DefaultAcademicStructure::seedFor($school);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    activateSchool($school, $planKey);

    return $admin;
}

test('the results page defaults to Primary 1 when it exists', function () {
    $admin = resultSchoolAdmin();

    $this->actingAs($admin)
        ->get(route('results.index'))
        ->assertOk()
        ->assertSee('Primary 1', false);
});

test('the results page is reachable on every plan', function (PlanKey $plan) {
    $admin = resultSchoolAdmin($plan);

    $this->actingAs($admin)
        ->get(route('results.index'))
        ->assertOk();
})->with([PlanKey::Basic, PlanKey::Standard, PlanKey::Exclusive]);

test('selecting a class, session, and term lists that examination\'s students', function () {
    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'session' => AcademicSession::current(),
        'term' => ExamTerm::First,
    ]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'first_name' => 'Amaka', 'last_name' => 'Obi']);

    $response = $this->actingAs($admin)->get(route('results.index', [
        'class' => 'JSS 1',
        'session' => AcademicSession::current(),
        'term' => ExamTerm::First->value,
    ]));

    $response->assertOk()->assertSee('Amaka')->assertSee('Obi');
});

test('a class/session/term with no examination shows the empty state', function () {
    $admin = resultSchoolAdmin();

    $response = $this->actingAs($admin)->get(route('results.index', [
        'class' => 'JSS 1',
        'session' => '1999/2000',
        'term' => ExamTerm::First->value,
    ]));

    $response->assertOk()->assertSee('No examination recorded yet');
});

test('result status is computed from how many subjects are graded', function () {
    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Math', 'max_score' => 100]);
    ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'English', 'max_score' => 100]);

    $notStarted = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    $inProgress = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    $complete = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);

    $mathSubject = $examination->subjects()->where('name', 'Math')->first();
    $englishSubject = $examination->subjects()->where('name', 'English')->first();
    ExaminationScore::factory()->create(['examination_subject_id' => $mathSubject->id, 'student_id' => $inProgress->id, 'score' => 70]);
    ExaminationScore::factory()->create(['examination_subject_id' => $mathSubject->id, 'student_id' => $complete->id, 'score' => 70]);
    ExaminationScore::factory()->create(['examination_subject_id' => $englishSubject->id, 'student_id' => $complete->id, 'score' => 80]);

    $response = $this->actingAs($admin)->get(route('results.index', [
        'class' => 'JSS 1',
        'session' => $examination->session,
        'term' => $examination->term->value,
    ]));

    $response->assertOk()->assertSee('Not Started')->assertSee('In Progress')->assertSee('Complete');
});

test('the results table shows each student\'s average score and percentage', function () {
    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    $math = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Math', 'max_score' => 100]);
    $english = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'English', 'max_score' => 50]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    ExaminationScore::factory()->create(['examination_subject_id' => $math->id, 'student_id' => $student->id, 'score' => 80]);
    ExaminationScore::factory()->create(['examination_subject_id' => $english->id, 'student_id' => $student->id, 'score' => 40]);

    // averageScore = (80 + 40) / 2 = 60; average (of per-subject percentages 80% and 80%) = 80%.
    $response = $this->actingAs($admin)->get(route('results.index', [
        'class' => 'JSS 1',
        'session' => $examination->session,
        'term' => $examination->term->value,
    ]));

    $response->assertOk()->assertSee('60')->assertSee('80%');
});

test('the show endpoint returns json with details and report card fragments', function () {
    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'first_name' => 'Amaka', 'last_name' => 'Obi']);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $student->id, 'score' => 85]);

    $response = $this->actingAs($admin)->getJson(route('results.show', [$examination, $student]));

    $response->assertOk()->assertJsonStructure([
        'card_number', 'details_html', 'report_card_html', 'has_scores', 'teacher_remark', 'principal_remark', 'guardian_count', 'last_sent_at',
    ]);
    expect($response->json('has_scores'))->toBeTrue();
    expect($response->json('details_html'))->toContain('Amaka');
    expect($response->json('report_card_html'))->toContain('Amaka');
    expect($response->json('guardian_count'))->toBe(0);
});

test('a school admin cannot view another school\'s result', function () {
    $admin = resultSchoolAdmin();
    $otherSchool = School::factory()->create();
    $examination = Examination::factory()->create(['school_id' => $otherSchool->id]);
    $student = Student::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($admin)
        ->getJson(route('results.show', [$examination, $student]))
        ->assertForbidden();
});

test('a school admin can save teacher and principal remarks', function () {
    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id]);

    $this->actingAs($admin)
        ->putJson(route('results.remarks', [$examination, $student]), [
            'teacher_remark' => 'Excellent performance this term.',
            'principal_remark' => 'Keep up the good work.',
        ])
        ->assertOk();

    $report = ExaminationReport::where('examination_id', $examination->id)->where('student_id', $student->id)->firstOrFail();
    expect($report->teacher_remark)->toBe('Excellent performance this term.');
    expect($report->principal_remark)->toBe('Keep up the good work.');
});

test('sending a result notifies the student', function () {
    Notification::fake();

    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id]);

    $this->actingAs($admin)
        ->postJson(route('results.send', [$examination, $student]), ['recipients' => ['student']])
        ->assertOk();

    Notification::assertSentTo($student, ResultAvailableNotification::class);

    $report = ExaminationReport::where('examination_id', $examination->id)->where('student_id', $student->id)->firstOrFail();
    expect($report->last_sent_at)->not->toBeNull();
    expect($report->last_sent_by)->toBe($admin->id);
});

test('sending a result notifies linked guardians', function () {
    Notification::fake();

    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id]);
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);
    $guardian->students()->attach($student->id, ['relationship' => 'Mother']);

    $this->actingAs($admin)
        ->postJson(route('results.send', [$examination, $student]), ['recipients' => ['guardians']])
        ->assertOk();

    Notification::assertSentTo($guardian, ResultAvailableNotification::class);
});

test('sending to guardians when none are linked does not fail', function () {
    Notification::fake();

    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id]);

    $response = $this->actingAs($admin)
        ->postJson(route('results.send', [$examination, $student]), ['recipients' => ['guardians']]);

    $response->assertOk();
    expect($response->json('status'))->toContain('no parent/guardian is linked');
});

test('a result pdf download returns a pdf', function () {
    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);

    $response = $this->actingAs($admin)->get(route('results.pdf', [$examination, $student]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('the result print view renders successfully', function () {
    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'first_name' => 'Amaka', 'last_name' => 'Obi']);

    $this->actingAs($admin)
        ->get(route('results.print', [$examination, $student]))
        ->assertOk()
        ->assertSee('Amaka')
        ->assertSee('Obi');
});

test('a report card for a student outside the examination\'s class still renders via fallback summary', function () {
    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 2', 'first_name' => 'Amaka', 'last_name' => 'Obi']);

    $this->actingAs($admin)
        ->get(route('results.print', [$examination, $student]))
        ->assertOk()
        ->assertSee('Amaka')
        ->assertSee('Obi');
});

test('the report card shows "term dates not set" when no term dates are configured', function () {
    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'session' => '2025/2026', 'term' => ExamTerm::First]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($admin)
        ->get(route('results.print', [$examination, $student]))
        ->assertOk()
        ->assertSee('Term dates not set');
});

test('the report card shows the attendance summary within the configured term range', function () {
    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'session' => '2025/2026', 'term' => ExamTerm::First]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);

    AcademicTerm::factory()->create([
        'school_id' => $school->id,
        'session' => '2025/2026',
        'term' => ExamTerm::First,
        'starts_on' => '2025-09-01',
        'ends_on' => '2025-12-01',
    ]);

    AttendanceRecord::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'date' => '2025-09-10', 'status' => AttendanceStatus::Present]);
    AttendanceRecord::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'date' => '2025-09-11', 'status' => AttendanceStatus::Absent]);
    // Outside the configured term range - must not be counted.
    AttendanceRecord::factory()->create(['school_id' => $school->id, 'student_id' => $student->id, 'date' => '2026-01-05', 'status' => AttendanceStatus::Present]);

    $response = $this->actingAs($admin)->get(route('results.print', [$examination, $student]));

    $response->assertOk()
        ->assertSeeInOrder(['Days Present', '1', 'Days Absent', '1']);
});

test('the report card shows the number of students in the class and the next term\'s start date', function () {
    $admin = resultSchoolAdmin();
    $school = $admin->school;
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'session' => '2025/2026', 'term' => ExamTerm::First]);
    Student::factory()->count(3)->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    AcademicTerm::factory()->create(['school_id' => $school->id, 'session' => '2025/2026', 'term' => ExamTerm::First, 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-01']);
    AcademicTerm::factory()->create(['school_id' => $school->id, 'session' => '2025/2026', 'term' => ExamTerm::Second, 'starts_on' => '2026-01-12', 'ends_on' => '2026-04-01']);

    $response = $this->actingAs($admin)->get(route('results.print', [$examination, $student]));

    $response->assertOk()
        ->assertSeeInOrder(['Number in Class', '4'])
        ->assertSeeInOrder(['Next Term Begins', 'Jan 12, 2026']);
});
