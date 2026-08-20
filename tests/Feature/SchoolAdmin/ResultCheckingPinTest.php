<?php

use App\Enums\PlanKey;
use App\Enums\ResultCheckingPinStatus;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school, PlanKey::Basic);
    $this->examination = Examination::factory()->create(['school_id' => $this->school->id]);
});

test('a basic-plan school admin can view its PIN pool', function () {
    $pin = ResultCheckingPin::factory()->create([
        'school_id' => $this->school->id,
        'examination_id' => null,
        'code' => 'ABCD-EFGH-JKLM',
    ]);

    $this->actingAs($this->admin)
        ->get(route('result-pins.index'))
        ->assertOk()
        ->assertSee('ABCD-EFGH-JKLM')
        ->assertSee('Unassigned');
});

test('a basic-plan school admin can assign an unassigned PIN to one of its own examinations', function () {
    $pin = ResultCheckingPin::factory()->create([
        'school_id' => $this->school->id,
        'examination_id' => null,
    ]);

    $this->actingAs($this->admin)
        ->post(route('result-pins.assign', $pin), ['examination_id' => $this->examination->id])
        ->assertRedirect();

    expect($pin->fresh()->examination_id)->toBe($this->examination->id);
});

test('a PIN that is already assigned cannot be reassigned', function () {
    $otherExamination = Examination::factory()->create(['school_id' => $this->school->id]);
    $pin = ResultCheckingPin::factory()->create([
        'school_id' => $this->school->id,
        'examination_id' => $this->examination->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('result-pins.assign', $pin), ['examination_id' => $otherExamination->id])
        ->assertStatus(422);

    expect($pin->fresh()->examination_id)->toBe($this->examination->id);
});

test('a PIN cannot be assigned to another school\'s examination', function () {
    $otherSchool = School::factory()->create();
    $otherExamination = Examination::factory()->create(['school_id' => $otherSchool->id]);
    $pin = ResultCheckingPin::factory()->create([
        'school_id' => $this->school->id,
        'examination_id' => null,
    ]);

    $this->actingAs($this->admin)
        ->post(route('result-pins.assign', $pin), ['examination_id' => $otherExamination->id])
        ->assertSessionHasErrors('examination_id');

    expect($pin->fresh()->examination_id)->toBeNull();
});

test('a school admin cannot assign another school\'s PIN', function () {
    $otherSchool = School::factory()->create();
    $pin = ResultCheckingPin::factory()->create([
        'school_id' => $otherSchool->id,
        'examination_id' => null,
    ]);

    $this->actingAs($this->admin)
        ->post(route('result-pins.assign', $pin), ['examination_id' => $this->examination->id])
        ->assertForbidden();
});

test('a basic-plan school admin can revoke a PIN', function () {
    $pin = ResultCheckingPin::factory()->create([
        'school_id' => $this->school->id,
        'examination_id' => $this->examination->id,
        'generated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('result-pins.revoke', $pin))
        ->assertRedirect();

    expect($pin->fresh()->status)->toBe(ResultCheckingPinStatus::Revoked);
});

test('a school admin cannot revoke another school\'s PIN', function () {
    $otherSchool = School::factory()->create();
    $pin = ResultCheckingPin::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->post(route('result-pins.revoke', $pin))
        ->assertForbidden();
});

test('there is no route left for a school admin to generate PINs themselves', function () {
    expect(Route::has('result-pins.store'))->toBeFalse();
});
