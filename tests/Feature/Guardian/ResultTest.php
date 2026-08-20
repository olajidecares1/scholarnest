<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Guardian;
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
    $this->guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $this->guardian->students()->attach($this->student->id, ['relationship' => 'Mother']);
    $this->examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $this->examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $this->student->id, 'score' => 85]);
});

test('a guardian can view their linked child\'s report card', function () {
    $this->actingAs($this->guardian, 'guardian')
        ->getJson(route('guardian.children.results.show', [$this->school, $this->student, $this->examination]))
        ->assertOk()
        ->assertJsonFragment(['has_scores' => true]);
});

test('a guardian cannot view a report card for a student who is not their child', function () {
    $otherStudent = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->guardian, 'guardian')
        ->getJson(route('guardian.children.results.show', [$this->school, $otherStudent, $this->examination]))
        ->assertForbidden();
});

test('a guardian can print and download their child\'s report card', function () {
    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.results.print', [$this->school, $this->student, $this->examination]))
        ->assertOk()
        ->assertSee('Amaka');

    $response = $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.results.pdf', [$this->school, $this->student, $this->examination]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('the send tab has no route on the guardian portal', function () {
    expect(Route::has('guardian.children.results.send'))->toBeFalse();
});
