<?php

use App\Enums\AcademicStage;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\CbtAttempt;
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

/*
 * The practice page and the school-test page share one Alpine component,
 * registered with Alpine.data(), which takes precedence over any function the
 * page defines itself. The practice page used to define its own and so, in
 * the browser, ran the school test's instead: every answer was posted as
 * cbt_test_question_id, refused with a 422, and every practice scored 0%.
 */
test('the practice page tells the shared CBT component which fields its endpoint takes', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 2,
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.cbt-practice.attempts.show', [$this->school, $attempt]))
        ->assertOk()
        ->assertSee("questionField: 'cbt_question_id'", false)
        ->assertSee("optionField: 'cbt_question_option_id'", false)
        ->assertDontSee('function cbtAttempt', false);
});

test('the shared component posts whichever field names it is given', function () {
    $script = file_get_contents(resource_path('js/cbt-attempt.js'));

    expect($script)->toContain('[this.questionField]: questionId')
        ->and($script)->toContain('[this.optionField]: optionId')
        ->and($script)->toContain('onKey(event)');
});

test('the result names the option chosen and the correct option for every question', function () {
    $attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 2,
    ]);

    $this->actingAs($this->student, 'student')
        ->postJson(route('student.cbt-practice.attempts.answer', [$this->school, $attempt]), [
            'cbt_question_id' => $this->q1->id,
            'cbt_question_option_id' => $this->q1Wrong->id,
        ])->assertOk();

    $this->actingAs($this->student, 'student')
        ->post(route('student.cbt-practice.attempts.submit', [$this->school, $attempt]));

    $this->actingAs($this->student, 'student')
        ->get(route('student.cbt-practice.attempts.show', [$this->school, $attempt]))
        ->assertOk()
        ->assertSee('Answer Summary')
        ->assertSee('Wrong. You chose B; the correct option is A.')
        ->assertSee('You did not answer. The correct option is A.');
});
