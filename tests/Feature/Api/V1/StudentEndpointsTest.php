<?php

use App\Enums\MemorandumAudience;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Guardian;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolNotice;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\ResultTokenIssuer;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->school = School::factory()->create(['school_code' => 'GRN001']);
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create([
        'school_id' => $this->school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'admission_number' => 'GRN001/001',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
        'class_name' => 'JSS 1',
    ]);

    $this->token = $this->postJson('/api/v1/tokens', [
        'role' => 'student',
        'school_code' => 'GRN001',
        'login' => 'GRN001/001',
        'password' => 'Correct-Horse1!',
        'device_name' => 'Test device',
    ])->json('token');

    app('auth')->forgetGuards();
});

/**
 * An examination this student has a mark in.
 */
function examinationFor(Student $student, string $subject = 'Mathematics', int $mark = 72): Examination
{
    $examination = Examination::factory()->create([
        'school_id' => $student->school_id,
        'class_name' => $student->class_name,
    ]);

    $examinationSubject = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'name' => $subject,
    ]);

    ExaminationScore::factory()->create([
        'examination_subject_id' => $examinationSubject->id,
        'student_id' => $student->id,
        'score' => $mark,
    ]);

    return $examination;
}

function examTokenFor(School $school, Student $student, Examination $examination): string
{
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin)['plain'];
}

test('the results list names the examinations without giving away a single mark', function () {
    $examination = examinationFor($this->student);

    $response = $this->withToken($this->token)->getJson('/api/v1/student/results')->assertOk();

    $response->assertJsonPath('data.0.id', $examination->uuid)
        ->assertJsonPath('data.0.requires_exam_token', true)
        ->assertJsonPath('data.0.is_withheld', false);

    // The list is a table of contents, not the book. Asserted on the keys
    // rather than by searching the body for "72" - a uuid or a date will
    // contain those two digits sooner or later, and a test that fails on
    // that is a test nobody trusts.
    expect(array_keys($response->json('data.0')))->toBe([
        'id', 'name', 'term', 'session', 'class_name', 'exam_date',
        'is_withheld', 'withheld_reason', 'requires_exam_token',
    ]);
});

test('another class\'s examination is not in the list', function () {
    examinationFor($this->student);

    $otherClass = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 3']);
    examinationFor($otherClass);

    $this->withToken($this->token)->getJson('/api/v1/student/results')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('a result will not open without its exam token', function () {
    $examination = examinationFor($this->student);

    $this->withToken($this->token)
        ->postJson("/api/v1/student/results/{$examination->uuid}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('exam_token');
});

test('the right exam token opens the report card', function () {
    $examination = examinationFor($this->student, 'Mathematics', 72);
    $examToken = examTokenFor($this->school, $this->student, $examination);

    $this->withToken($this->token)
        ->postJson("/api/v1/student/results/{$examination->uuid}", ['exam_token' => $examToken])
        ->assertOk()
        ->assertJsonPath('data.examination.id', $examination->uuid)
        ->assertJsonPath('data.student.admission_number', 'GRN001/001')
        ->assertJsonPath('data.subjects.0.subject', 'Mathematics')
        ->assertJsonPath('data.subjects.0.total', 72)
        ->assertJsonPath('data.subjects.0.is_graded', true);
});

test('a wrong exam token is refused, and says nothing about why', function () {
    $examination = examinationFor($this->student);

    $this->withToken($this->token)
        ->postJson("/api/v1/student/results/{$examination->uuid}", ['exam_token' => 'not-a-real-token'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('exam_token');
});

test('another child\'s token does not open this child\'s result', function () {
    $examination = examinationFor($this->student);

    $classmate = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $theirToken = examTokenFor($this->school, $classmate, $examination);

    $this->withToken($this->token)
        ->postJson("/api/v1/student/results/{$examination->uuid}", ['exam_token' => $theirToken])
        ->assertStatus(422);
});

test('a result from another school is not found, token or no token', function () {
    $otherSchool = School::factory()->create();
    $stranger = Student::factory()->create(['school_id' => $otherSchool->id, 'class_name' => 'JSS 1']);
    $theirExamination = examinationFor($stranger);

    $this->withToken($this->token)
        ->postJson("/api/v1/student/results/{$theirExamination->uuid}", ['exam_token' => 'anything'])
        ->assertNotFound();
});

test('attendance comes back for this student only', function () {
    AttendanceRecord::factory()->count(3)->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'class_name' => 'JSS 1',
    ]);

    $classmate = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    AttendanceRecord::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => $classmate->id,
        'class_name' => 'JSS 1',
    ]);

    $this->withToken($this->token)->getJson('/api/v1/student/attendance')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('the timetable is this class\'s timetable', function () {
    TimetableEntry::factory()->count(2)->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 1',
    ]);
    TimetableEntry::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 3',
    ]);

    $this->withToken($this->token)->getJson('/api/v1/student/timetable')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('assignments are this class\'s assignments', function () {
    Assignment::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'title' => 'Fractions']);
    Assignment::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 3', 'title' => 'Someone else\'s work']);

    $this->withToken($this->token)->getJson('/api/v1/student/assignments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Fractions');
});

test('a memorandum for the staff room does not reach a pupil', function () {
    SchoolNotice::factory()->create([
        'school_id' => $this->school->id,
        'audience' => MemorandumAudience::Staff,
        'title' => 'Staff briefing',
    ]);
    SchoolNotice::factory()->create([
        'school_id' => $this->school->id,
        'audience' => MemorandumAudience::Students,
        'title' => 'Sports day',
    ]);
    SchoolNotice::factory()->create([
        'school_id' => $this->school->id,
        'audience' => MemorandumAudience::All,
        'title' => 'Term ends Friday',
    ]);

    $response = $this->withToken($this->token)->getJson('/api/v1/student/notices')->assertOk();

    expect(collect($response->json('data'))->pluck('title')->sort()->values()->all())
        ->toBe(['Sports day', 'Term ends Friday']);
});

test('a student token is refused at a guardian endpoint', function () {
    // A valid token is not the same thing as a token that belongs here.
    $this->withToken($this->token)->getJson('/api/v1/guardian/children')->assertForbidden();
});

test('a guardian token is refused at a student endpoint', function () {
    $guardian = Guardian::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'parent@example.com',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);

    $guardianToken = $this->postJson('/api/v1/tokens', [
        'role' => 'guardian',
        'school_code' => 'GRN001',
        'login' => 'parent@example.com',
        'password' => 'Correct-Horse1!',
        'device_name' => 'Parent phone',
    ])->json('token');

    app('auth')->forgetGuards();

    $this->withToken($guardianToken)->getJson('/api/v1/student/results')->assertForbidden();

    expect($guardian->fresh())->not->toBeNull();
});
