<?php

use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\GradeBand;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can add a grade band', function () {
    $response = $this->actingAs($this->admin)->post(route('academics.grade-bands.store'), [
        'min_percent' => 75,
        'max_percent' => 100,
        'letter' => 'A+',
        'description' => 'Distinction',
    ]);

    $response->assertRedirect();

    $band = GradeBand::where('school_id', $this->school->id)->firstOrFail();
    expect($band->min_percent)->toBe(75);
    expect($band->max_percent)->toBe(100);
    expect($band->letter)->toBe('A+');
    expect($band->description)->toBe('Distinction');
});

test('a school admin can update a grade band', function () {
    $band = GradeBand::factory()->create(['school_id' => $this->school->id, 'letter' => 'A']);

    $this->actingAs($this->admin)->put(route('academics.grade-bands.update', $band), [
        'min_percent' => 80,
        'max_percent' => 100,
        'letter' => 'A*',
        'description' => 'Outstanding',
    ])->assertRedirect();

    $band->refresh();
    expect($band->letter)->toBe('A*');
    expect($band->min_percent)->toBe(80);
});

test('a school admin cannot update another school\'s grade band', function () {
    $otherSchool = School::factory()->create();
    $band = GradeBand::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)->put(route('academics.grade-bands.update', $band), [
        'min_percent' => 0,
        'max_percent' => 10,
        'letter' => 'Z',
    ])->assertForbidden();
});

test('a school admin can remove a grade band', function () {
    $band = GradeBand::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->delete(route('academics.grade-bands.destroy', $band))->assertRedirect();

    expect(GradeBand::find($band->id))->toBeNull();
});

test('a school admin cannot remove another school\'s grade band', function () {
    $otherSchool = School::factory()->create();
    $band = GradeBand::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)->delete(route('academics.grade-bands.destroy', $band))->assertForbidden();

    expect(GradeBand::find($band->id))->not->toBeNull();
});

test('a score uses the school\'s configured grade bands instead of the default scale', function () {
    GradeBand::factory()->create(['school_id' => $this->school->id, 'min_percent' => 90, 'max_percent' => 100, 'letter' => 'A*']);
    GradeBand::factory()->create(['school_id' => $this->school->id, 'min_percent' => 0, 'max_percent' => 89, 'letter' => 'B*']);

    $subject = ExaminationSubject::factory()->create(['examination_id' => Examination::factory()->create(['school_id' => $this->school->id])->id, 'max_score' => 100]);
    $score = ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'score' => 95]);

    expect($score->grade())->toBe('A*');
});

test('a school with no configured grade bands falls back to the default scale', function () {
    $subject = ExaminationSubject::factory()->create(['examination_id' => Examination::factory()->create(['school_id' => $this->school->id])->id, 'max_score' => 100]);
    $score = ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'score' => 65]);

    expect($score->grade())->toBe('B');
});

test('GradeBand::describe returns the matching band\'s description, configured or default', function () {
    expect(GradeBand::describe($this->school, 85))->toBe('Excellent');
    expect(GradeBand::describe($this->school, 20))->toBe('Needs Improvement');

    GradeBand::factory()->create(['school_id' => $this->school->id, 'min_percent' => 0, 'max_percent' => 100, 'letter' => 'X', 'description' => 'Custom Remark']);

    expect(GradeBand::describe($this->school->fresh(), 50))->toBe('Custom Remark');
});
