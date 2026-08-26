<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'first_name' => 'Amaka', 'last_name' => 'Obi']);
    $this->examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $this->examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $this->student->id, 'score' => 85]);
});

test('a student can view their own report card', function () {
    enterExamToken($this->student, 'student', $this->school, $this->student, $this->examination, 'student.results.unlock', [$this->school, $this->examination]);

    $this->actingAs($this->student, 'student')
        ->getJson(route('student.results.show', [$this->school, $this->examination]))
        ->assertOk()
        ->assertJsonFragment(['has_scores' => true]);
});

test('a student cannot view a report card for an examination outside their class', function () {
    $otherExamination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2']);

    $this->actingAs($this->student, 'student')
        ->getJson(route('student.results.show', [$this->school, $otherExamination]))
        ->assertForbidden();
});

test('a student can print and download their own report card', function () {
    enterExamToken($this->student, 'student', $this->school, $this->student, $this->examination, 'student.results.unlock', [$this->school, $this->examination]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.results.print', [$this->school, $this->examination]))
        ->assertOk()
        ->assertSee('Amaka');

    $response = $this->actingAs($this->student, 'student')
        ->get(route('student.results.pdf', [$this->school, $this->examination]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('the send tab has no route on the student portal', function () {
    expect(Route::has('student.results.send'))->toBeFalse();
});
