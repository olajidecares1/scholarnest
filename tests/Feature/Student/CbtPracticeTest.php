<?php

use App\Enums\AcademicStage;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\CbtAttempt;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtExamBodyClassGrant;
use App\Models\CbtQuestion;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
});

test('a student can start a cbt practice attempt', function () {
    $examBody = CbtExamBody::factory()->create(['academic_stages' => [AcademicStage::JuniorSecondary->value]]);
    $exam = CbtExam::factory()->create(['cbt_exam_body_id' => $examBody->id]);
    CbtQuestion::factory()->count(3)->create(['cbt_exam_id' => $exam->id]);

    $response = $this->actingAs($this->student, 'student')
        ->post(route('student.cbt-practice.start', [$this->school, $exam]));

    $attempt = CbtAttempt::where('student_id', $this->student->id)->firstOrFail();
    expect($attempt->cbt_exam_id)->toBe($exam->id);
    expect($attempt->total_questions)->toBe(3);
    expect($attempt->started_at->diffInMinutes($attempt->expires_at))->toBe((float) $exam->duration_minutes);
    $response->assertRedirect(route('student.cbt-practice.attempts.show', [$this->school, $attempt]));
});

test('a student cannot view another student\'s attempt', function () {
    $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);
    $attempt = CbtAttempt::factory()->create(['student_id' => $otherStudent->id]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.cbt-practice.attempts.show', [$this->school, $attempt]))
        ->assertForbidden();
});

test('a junior secondary student only sees junior-secondary exam bodies', function () {
    $bece = CbtExamBody::factory()->create(['name' => 'BECE', 'academic_stages' => [AcademicStage::JuniorSecondary->value]]);
    $waec = CbtExamBody::factory()->create(['name' => 'WAEC', 'academic_stages' => [AcademicStage::SeniorSecondary->value]]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.cbt-practice.index', $this->school))
        ->assertStatus(200)
        ->assertSee('BECE')
        ->assertDontSee('WAEC');
});

test('a senior secondary student only sees senior-secondary exam bodies', function () {
    $seniorStudent = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'SSS 2']);
    CbtExamBody::factory()->create(['name' => 'BECE', 'academic_stages' => [AcademicStage::JuniorSecondary->value]]);
    CbtExamBody::factory()->create(['name' => 'WAEC', 'academic_stages' => [AcademicStage::SeniorSecondary->value]]);

    $this->actingAs($seniorStudent, 'student')
        ->get(route('student.cbt-practice.index', $this->school))
        ->assertStatus(200)
        ->assertSee('WAEC')
        ->assertDontSee('BECE');
});

test('a primary student sees no exam bodies by default', function () {
    $primaryStudent = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'Primary 4']);
    CbtExamBody::factory()->create(['name' => 'WAEC', 'academic_stages' => [AcademicStage::SeniorSecondary->value]]);

    $this->actingAs($primaryStudent, 'student')
        ->get(route('student.cbt-practice.index', $this->school))
        ->assertStatus(200)
        ->assertDontSee('WAEC');
});

test('a primary student can see a body specifically granted to their class', function () {
    $primaryStudent = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'Primary 4']);
    $waec = CbtExamBody::factory()->create(['name' => 'WAEC', 'academic_stages' => [AcademicStage::SeniorSecondary->value]]);

    CbtExamBodyClassGrant::factory()->create([
        'school_id' => $this->school->id,
        'cbt_exam_body_id' => $waec->id,
        'class_name' => 'Primary 4',
    ]);

    $this->actingAs($primaryStudent, 'student')
        ->get(route('student.cbt-practice.index', $this->school))
        ->assertStatus(200)
        ->assertSee('WAEC');
});

test('a student cannot view an exam body outside their stage even by direct url', function () {
    $waec = CbtExamBody::factory()->create(['name' => 'WAEC', 'academic_stages' => [AcademicStage::SeniorSecondary->value]]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.cbt-practice.show', [$this->school, $waec]))
        ->assertForbidden();
});

test('a student cannot start an exam outside their stage', function () {
    $waec = CbtExamBody::factory()->create(['name' => 'WAEC', 'academic_stages' => [AcademicStage::SeniorSecondary->value]]);
    $exam = CbtExam::factory()->create(['cbt_exam_body_id' => $waec->id]);

    $this->actingAs($this->student, 'student')
        ->post(route('student.cbt-practice.start', [$this->school, $exam]))
        ->assertForbidden();

    expect(CbtAttempt::where('student_id', $this->student->id)->exists())->toBeFalse();
});
