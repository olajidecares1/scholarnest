<?php

use App\Enums\PlanKey;
use App\Enums\ResultCheckingPinStatus;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\ResultCheckingPin;
use App\Models\School;
use App\Models\Student;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->examination = Examination::factory()->create(['school_id' => $this->school->id]);
    $this->subject = ExaminationSubject::factory()->create(['examination_id' => $this->examination->id, 'max_score' => 100]);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'admission_number' => 'STU-0001']);

    ExaminationScore::factory()->create([
        'examination_subject_id' => $this->subject->id,
        'student_id' => $this->student->id,
        'score' => 80,
    ]);

    $this->pin = ResultCheckingPin::factory()->create([
        'school_id' => $this->school->id,
        'examination_id' => $this->examination->id,
        'code' => 'ABCD-EFGH-JKLM',
    ]);
});

test('the check-result page is reachable for a school with no website record', function () {
    $this->get(route('check-result.show', $this->school))->assertOk();
});

test('the check-result page is reachable for a basic-plan school', function () {
    activateSchool($this->school, PlanKey::Basic);

    $this->get(route('check-result.show', $this->school))->assertOk();
});

test('a valid PIN and admission number reveals the result and consumes one use', function () {
    $response = $this->post(route('check-result.verify', $this->school), [
        'code' => $this->pin->code,
        'admission_number' => $this->student->admission_number,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->pin->refresh();
    expect($this->pin->uses_count)->toBe(1);
    expect($this->pin->status)->toBe(ResultCheckingPinStatus::Active);
    expect($this->pin->bound_student_id)->toBe($this->student->id);

    $this->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee($this->student->fullName())
        ->assertSee('80');
});

test('a PIN can be used up to 4 times for its bound student and is exhausted on the 5th attempt', function () {
    for ($i = 1; $i <= 4; $i++) {
        $this->post(route('check-result.verify', $this->school), [
            'code' => $this->pin->code,
            'admission_number' => $this->student->admission_number,
        ])->assertSessionHasNoErrors();
    }

    $this->pin->refresh();
    expect($this->pin->uses_count)->toBe(4);
    expect($this->pin->status)->toBe(ResultCheckingPinStatus::Exhausted);

    $this->post(route('check-result.verify', $this->school), [
        'code' => $this->pin->code,
        'admission_number' => $this->student->admission_number,
    ])->assertSessionHasErrors([
        'code' => 'This result-checking PIN has reached its maximum usage limit.',
    ]);

    expect($this->pin->fresh()->uses_count)->toBe(4);
});

test('a PIN cannot be reused to check a different student once bound', function () {
    $otherStudent = Student::factory()->create(['school_id' => $this->school->id, 'admission_number' => 'STU-0002']);

    $this->post(route('check-result.verify', $this->school), [
        'code' => $this->pin->code,
        'admission_number' => $this->student->admission_number,
    ])->assertSessionHasNoErrors();

    $this->post(route('check-result.verify', $this->school), [
        'code' => $this->pin->code,
        'admission_number' => $otherStudent->admission_number,
    ])->assertSessionHasErrors([
        'code' => 'This PIN is not valid for this student.',
    ]);

    expect($this->pin->fresh()->uses_count)->toBe(1);
});

test('an unknown PIN is rejected', function () {
    $this->post(route('check-result.verify', $this->school), [
        'code' => 'ZZZZ-ZZZZ-ZZZZ',
        'admission_number' => $this->student->admission_number,
    ])->assertSessionHasErrors('code');
});

test('a revoked PIN is rejected', function () {
    $this->pin->update(['status' => ResultCheckingPinStatus::Revoked]);

    $this->post(route('check-result.verify', $this->school), [
        'code' => $this->pin->code,
        'admission_number' => $this->student->admission_number,
    ])->assertSessionHasErrors('code');

    expect($this->pin->fresh()->uses_count)->toBe(0);
});

test('an unknown admission number is rejected', function () {
    $this->post(route('check-result.verify', $this->school), [
        'code' => $this->pin->code,
        'admission_number' => 'NOT-A-STUDENT',
    ])->assertSessionHasErrors('admission_number');

    expect($this->pin->fresh()->uses_count)->toBe(0);
});

test('a PIN not yet assigned to an examination cannot be redeemed', function () {
    $unassignedPin = ResultCheckingPin::factory()->create([
        'school_id' => $this->school->id,
        'examination_id' => null,
        'code' => 'ZZZZ-AAAA-BBBB',
    ]);

    $this->post(route('check-result.verify', $this->school), [
        'code' => $unassignedPin->code,
        'admission_number' => $this->student->admission_number,
    ])->assertSessionHasErrors([
        'code' => 'This PIN has not yet been activated by your school. Please contact them.',
    ]);

    expect($unassignedPin->fresh()->uses_count)->toBe(0);
});

test('a PIN from another school cannot be redeemed against this school', function () {
    $otherSchool = School::factory()->create();

    $this->post(route('check-result.verify', $otherSchool), [
        'code' => $this->pin->code,
        'admission_number' => $this->student->admission_number,
    ])->assertSessionHasErrors('code');
});
