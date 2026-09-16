<?php

use App\Enums\AcademicStage;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\CbtAttempt;
use App\Models\CbtAttemptAnswer;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtQuestion;
use App\Models\CbtQuestionOption;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $examBody = CbtExamBody::factory()->create(['academic_stages' => [AcademicStage::JuniorSecondary->value]]);
    $this->exam = CbtExam::factory()->create(['cbt_exam_body_id' => $examBody->id, 'pass_mark' => 50, 'duration_minutes' => 30]);
    $this->q1 = CbtQuestion::factory()->create(['cbt_exam_id' => $this->exam->id]);
    $this->q1Correct = CbtQuestionOption::factory()->create(['cbt_question_id' => $this->q1->id, 'label' => 'A', 'is_correct' => true]);
    $this->q1Wrong = CbtQuestionOption::factory()->create(['cbt_question_id' => $this->q1->id, 'label' => 'B', 'is_correct' => false]);
    $this->q2 = CbtQuestion::factory()->create(['cbt_exam_id' => $this->exam->id]);
    $this->q2Correct = CbtQuestionOption::factory()->create(['cbt_question_id' => $this->q2->id, 'label' => 'A', 'is_correct' => true]);
    $this->q2Wrong = CbtQuestionOption::factory()->create(['cbt_question_id' => $this->q2->id, 'label' => 'B', 'is_correct' => false]);
});

test('starting an attempt sets expires_at based on the exam duration', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'started_at' => now(),
        'expires_at' => now()->addMinutes($this->exam->duration_minutes),
    ]);

    expect($attempt->started_at->diffInMinutes($attempt->expires_at))->toBe(30.0);
});

test('a student can view the take page for an in-progress attempt', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 2,
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.cbt-practice.attempts.show', [$this->school, $attempt]))
        ->assertStatus(200)
        ->assertSee('Submit Exam')
        ->assertSee($this->q1->question_text, false);
});

test('selecting an answer autosaves via the answer endpoint', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 2,
    ]);

    $this->actingAs($this->student, 'student')
        ->post(route('student.cbt-practice.attempts.answer', [$this->school, $attempt]), [
            'cbt_question_id' => $this->q1->id,
            'cbt_question_option_id' => $this->q1Correct->id,
        ])
        ->assertOk()
        ->assertJson(['saved' => true]);

    $answer = CbtAttemptAnswer::where('cbt_attempt_id', $attempt->id)->where('cbt_question_id', $this->q1->id)->firstOrFail();
    expect($answer->cbt_question_option_id)->toBe($this->q1Correct->id);
    expect($answer->is_correct)->toBeTrue();
    expect($attempt->fresh()->submitted_at)->toBeNull();
});

test('changing an answer before submit overwrites the previous autosave', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 2,
    ]);

    $this->actingAs($this->student, 'student')->post(route('student.cbt-practice.attempts.answer', [$this->school, $attempt]), [
        'cbt_question_id' => $this->q1->id,
        'cbt_question_option_id' => $this->q1Wrong->id,
    ]);
    $this->actingAs($this->student, 'student')->post(route('student.cbt-practice.attempts.answer', [$this->school, $attempt]), [
        'cbt_question_id' => $this->q1->id,
        'cbt_question_option_id' => $this->q1Correct->id,
    ]);

    expect(CbtAttemptAnswer::where('cbt_attempt_id', $attempt->id)->count())->toBe(1);
    $answer = CbtAttemptAnswer::where('cbt_attempt_id', $attempt->id)->firstOrFail();
    expect($answer->cbt_question_option_id)->toBe($this->q1Correct->id);
    expect($answer->is_correct)->toBeTrue();
});

test('visiting an attempt after expiry auto-finalizes it server-side', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'started_at' => now()->subMinutes(40),
        'expires_at' => now()->subMinutes(10),
        'total_questions' => 2,
    ]);
    CbtAttemptAnswer::create([
        'cbt_attempt_id' => $attempt->id,
        'cbt_question_id' => $this->q1->id,
        'cbt_question_option_id' => $this->q1Correct->id,
        'is_correct' => true,
    ]);

    $response = $this->actingAs($this->student, 'student')
        ->get(route('student.cbt-practice.attempts.show', [$this->school, $attempt]));

    $response->assertStatus(200);
    $attempt->refresh();
    expect($attempt->isSubmitted())->toBeTrue();
    expect($attempt->auto_submitted)->toBeTrue();
    expect((float) $attempt->score)->toBe(50.0);
});

test('the answer endpoint rejects further saves once expired', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'started_at' => now()->subMinutes(40),
        'expires_at' => now()->subMinutes(10),
        'total_questions' => 2,
    ]);

    $response = $this->actingAs($this->student, 'student')
        ->post(route('student.cbt-practice.attempts.answer', [$this->school, $attempt]), [
            'cbt_question_id' => $this->q1->id,
            'cbt_question_option_id' => $this->q1Correct->id,
        ]);

    $response->assertStatus(409);
    $response->assertJson(['expired' => true]);
    expect($attempt->fresh()->isSubmitted())->toBeTrue();
});

test('submit finalizes using only autosaved answers, ignoring any request payload', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 2,
    ]);
    CbtAttemptAnswer::create([
        'cbt_attempt_id' => $attempt->id,
        'cbt_question_id' => $this->q1->id,
        'cbt_question_option_id' => $this->q1Correct->id,
        'is_correct' => true,
    ]);

    $response = $this->actingAs($this->student, 'student')
        ->post(route('student.cbt-practice.attempts.submit', [$this->school, $attempt]), [
            'answers' => [
                $this->q1->id => $this->q1Wrong->id,
                $this->q2->id => $this->q2Correct->id,
            ],
        ]);

    $response->assertRedirect(route('student.cbt-practice.attempts.show', [$this->school, $attempt]));

    $attempt->refresh();
    expect($attempt->isSubmitted())->toBeTrue();
    expect($attempt->auto_submitted)->toBeFalse();
    expect((float) $attempt->score)->toBe(50.0);
    expect(CbtAttemptAnswer::where('cbt_attempt_id', $attempt->id)->count())->toBe(1);
});

// -----------------------------------------------------------------------------
// What the page is allowed to know, and what it is allowed to save.
// -----------------------------------------------------------------------------

test('the take page never tells the browser which option is correct', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 2,
    ]);

    $html = $this->actingAs($this->student, 'student')
        ->get(route('student.cbt-practice.attempts.show', [$this->school, $attempt]))
        ->assertOk()
        ->getContent();

    // Both options are on the page; nothing on it says which one scores.
    expect($html)->toContain($this->q1Correct->option_text)
        ->and($html)->toContain($this->q1Wrong->option_text)
        ->and($html)->not->toContain('is_correct');
});

test('an answer belonging to another question is refused', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 2,
    ]);

    // Question 1 answered with question 2's correct option: a request no
    // honest page makes, and one that used to score a mark.
    $this->actingAs($this->student, 'student')
        ->post(route('student.cbt-practice.attempts.answer', [$this->school, $attempt]), [
            'cbt_question_id' => $this->q1->id,
            'cbt_question_option_id' => $this->q2Correct->id,
        ])
        ->assertStatus(422);

    expect(CbtAttemptAnswer::where('cbt_attempt_id', $attempt->id)->count())->toBe(0);
});

test('a question from another paper cannot be answered', function () {
    $otherExam = CbtExam::factory()->create(['cbt_exam_body_id' => $this->exam->cbt_exam_body_id]);
    $foreign = CbtQuestion::factory()->create(['cbt_exam_id' => $otherExam->id]);
    $foreignOption = CbtQuestionOption::factory()->create(['cbt_question_id' => $foreign->id, 'is_correct' => true]);

    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 2,
    ]);

    $this->actingAs($this->student, 'student')
        ->post(route('student.cbt-practice.attempts.answer', [$this->school, $attempt]), [
            'cbt_question_id' => $foreign->id,
            'cbt_question_option_id' => $foreignOption->id,
        ])
        ->assertStatus(422);

    expect(CbtAttemptAnswer::where('cbt_attempt_id', $attempt->id)->count())->toBe(0);
});

test('a question that is not published cannot be answered', function () {
    $draft = CbtQuestion::factory()->create(['cbt_exam_id' => $this->exam->id, 'is_published' => false]);
    $draftOption = CbtQuestionOption::factory()->create(['cbt_question_id' => $draft->id, 'is_correct' => true]);

    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 2,
    ]);

    $this->actingAs($this->student, 'student')
        ->post(route('student.cbt-practice.attempts.answer', [$this->school, $attempt]), [
            'cbt_question_id' => $draft->id,
            'cbt_question_option_id' => $draftOption->id,
        ])
        ->assertStatus(422);
});

test('clearing an answer is saved as no answer at all', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 2,
    ]);

    $this->actingAs($this->student, 'student')->post(route('student.cbt-practice.attempts.answer', [$this->school, $attempt]), [
        'cbt_question_id' => $this->q1->id,
        'cbt_question_option_id' => $this->q1Correct->id,
    ]);

    $this->actingAs($this->student, 'student')->post(route('student.cbt-practice.attempts.answer', [$this->school, $attempt]), [
        'cbt_question_id' => $this->q1->id,
        'cbt_question_option_id' => null,
    ])->assertOk();

    $answer = CbtAttemptAnswer::where('cbt_attempt_id', $attempt->id)->firstOrFail();

    expect($answer->cbt_question_option_id)->toBeNull()
        ->and($answer->is_correct)->toBeFalse();
});

test('a student cannot autosave into another student\'s attempt', function () {
    $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $otherStudent->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($this->student, 'student')
        ->post(route('student.cbt-practice.attempts.answer', [$this->school, $attempt]), [
            'cbt_question_id' => $this->q1->id,
            'cbt_question_option_id' => $this->q1Correct->id,
        ])
        ->assertForbidden();
});
