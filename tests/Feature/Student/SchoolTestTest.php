<?php

use App\Enums\CbtTestStatus;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\CbtTest;
use App\Models\CbtTestAttempt;
use App\Models\CbtTestQuestion;
use App\Models\CbtTestQuestionOption;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
});

test('a student only sees published tests for their own class', function () {
    $ownClassTest = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'JSS 1',
        'title' => 'My Class Test',
        'status' => CbtTestStatus::Published,
    ]);
    CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'JSS 2',
        'title' => 'Other Class Test',
        'status' => CbtTestStatus::Published,
    ]);
    CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'JSS 1',
        'title' => 'Still Draft Test',
        'status' => CbtTestStatus::Draft,
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.index', $this->school))
        ->assertStatus(200)
        ->assertSee('My Class Test')
        ->assertDontSee('Other Class Test')
        ->assertDontSee('Still Draft Test');
});

test('a student can start, answer, and submit a test', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'JSS 1',
        'status' => CbtTestStatus::Published,
        'pass_mark' => 50,
    ]);
    $question = CbtTestQuestion::factory()->create(['cbt_test_id' => $test->id]);
    $correctOption = CbtTestQuestionOption::factory()->create(['cbt_test_question_id' => $question->id, 'is_correct' => true]);

    $this->actingAs($this->student, 'student')
        ->post(route('student.tests.start', [$this->school, $test]))
        ->assertRedirect();

    $attempt = CbtTestAttempt::where('student_id', $this->student->id)->where('cbt_test_id', $test->id)->firstOrFail();
    expect($attempt->total_questions)->toBe(1);

    $this->actingAs($this->student, 'student')
        ->postJson(route('student.tests.attempts.answer', [$this->school, $attempt]), [
            'cbt_test_question_id' => $question->id,
            'cbt_test_question_option_id' => $correctOption->id,
        ])
        ->assertOk();

    $this->actingAs($this->student, 'student')
        ->post(route('student.tests.attempts.submit', [$this->school, $attempt]))
        ->assertRedirect();

    $attempt->refresh();
    expect($attempt->isSubmitted())->toBeTrue();
    expect((float) $attempt->score)->toBe(100.0);
    expect($attempt->passed())->toBeTrue();
});

test('a student cannot start a test outside their class', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'JSS 2',
        'status' => CbtTestStatus::Published,
    ]);

    $this->actingAs($this->student, 'student')
        ->post(route('student.tests.start', [$this->school, $test]))
        ->assertForbidden();
});

test('a student cannot start a draft test', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'JSS 1',
        'status' => CbtTestStatus::Draft,
    ]);

    $this->actingAs($this->student, 'student')
        ->post(route('student.tests.start', [$this->school, $test]))
        ->assertForbidden();
});

test('a locked test is never visible or startable by students', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'JSS 1',
        'title' => 'Locked Test',
        'status' => CbtTestStatus::Locked,
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.index', $this->school))
        ->assertDontSee('Locked Test');

    $this->actingAs($this->student, 'student')
        ->post(route('student.tests.start', [$this->school, $test]))
        ->assertForbidden();
});

test('an archived test remains visible to a student who already attempted it, but cannot be restarted', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'JSS 1',
        'title' => 'Archived Test',
        'status' => CbtTestStatus::Archived,
    ]);
    CbtTestAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_test_id' => $test->id,
        'submitted_at' => now(),
        'score' => 80,
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.index', $this->school))
        ->assertSee('Archived Test');

    $this->actingAs($this->student, 'student')
        ->post(route('student.tests.start', [$this->school, $test]))
        ->assertForbidden();
});

test('a student cannot view another student\'s attempt', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'JSS 1',
        'status' => CbtTestStatus::Published,
    ]);
    $otherStudent = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $attempt = CbtTestAttempt::factory()->create([
        'student_id' => $otherStudent->id,
        'cbt_test_id' => $test->id,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.attempts.show', [$this->school, $attempt]))
        ->assertForbidden();
});

test('an expired attempt is auto-submitted when viewed', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'JSS 1',
        'status' => CbtTestStatus::Published,
    ]);
    $attempt = CbtTestAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_test_id' => $test->id,
        'started_at' => now()->subMinutes(40),
        'expires_at' => now()->subMinutes(10),
        'total_questions' => 0,
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.attempts.show', [$this->school, $attempt]))
        ->assertStatus(200);

    $attempt->refresh();
    expect($attempt->isSubmitted())->toBeTrue();
    expect($attempt->auto_submitted)->toBeTrue();
});
