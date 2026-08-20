<?php

use App\Enums\ExamTerm;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\School;
use App\Models\User;
use App\Support\AcademicSession;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can set term dates', function () {
    $response = $this->actingAs($this->admin)->post(route('academics.terms.store'), [
        'session' => AcademicSession::current(),
        'term' => ExamTerm::First->value,
        'starts_on' => '2025-09-08',
        'ends_on' => '2025-12-12',
    ]);

    $response->assertRedirect();

    $term = AcademicTerm::where('school_id', $this->school->id)->firstOrFail();
    expect($term->session)->toBe(AcademicSession::current());
    expect($term->term)->toBe(ExamTerm::First);
    expect($term->starts_on->toDateString())->toBe('2025-09-08');
    expect($term->ends_on->toDateString())->toBe('2025-12-12');
});

test('saving term dates twice for the same session and term updates it, not duplicates it', function () {
    $this->actingAs($this->admin)->post(route('academics.terms.store'), [
        'session' => AcademicSession::current(),
        'term' => ExamTerm::First->value,
        'starts_on' => '2025-09-08',
        'ends_on' => '2025-12-12',
    ]);

    $this->actingAs($this->admin)->post(route('academics.terms.store'), [
        'session' => AcademicSession::current(),
        'term' => ExamTerm::First->value,
        'starts_on' => '2025-09-09',
        'ends_on' => '2025-12-13',
    ]);

    expect(AcademicTerm::where('school_id', $this->school->id)->count())->toBe(1);
    expect(AcademicTerm::where('school_id', $this->school->id)->first()->starts_on->toDateString())->toBe('2025-09-09');
});

test('the end date must not be before the start date', function () {
    $this->actingAs($this->admin)
        ->post(route('academics.terms.store'), [
            'session' => AcademicSession::current(),
            'term' => ExamTerm::First->value,
            'starts_on' => '2025-12-12',
            'ends_on' => '2025-09-08',
        ])
        ->assertSessionHasErrors('ends_on');
});

test('a school admin can remove term dates', function () {
    $term = AcademicTerm::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('academics.terms.destroy', $term))
        ->assertRedirect();

    expect(AcademicTerm::find($term->id))->toBeNull();
});

test('a school admin cannot remove another school\'s term dates', function () {
    $otherSchool = School::factory()->create();
    $term = AcademicTerm::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->delete(route('academics.terms.destroy', $term))
        ->assertForbidden();

    expect(AcademicTerm::find($term->id))->not->toBeNull();
});

test('AcademicTerm::rangeFor returns null when no dates are configured', function () {
    expect(AcademicTerm::rangeFor($this->school, AcademicSession::current(), ExamTerm::First))->toBeNull();
});

test('AcademicTerm::rangeFor finds the configured range', function () {
    AcademicTerm::factory()->create([
        'school_id' => $this->school->id,
        'session' => '2025/2026',
        'term' => ExamTerm::Second,
        'starts_on' => '2026-01-05',
        'ends_on' => '2026-04-10',
    ]);

    $range = AcademicTerm::rangeFor($this->school, '2025/2026', ExamTerm::Second);

    expect($range)->not->toBeNull();
    expect($range->starts_on->toDateString())->toBe('2026-01-05');
});
