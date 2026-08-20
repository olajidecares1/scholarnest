<?php

use App\Enums\PlanKey;
use App\Enums\ResultCheckingPinStatus;
use App\Enums\UserRole;
use App\Models\ResultCheckingPin;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    $this->basicSchool = School::factory()->create();
    activateSchool($this->basicSchool, PlanKey::Basic);
});

test('a super admin can see basic-plan schools in the result-pins list', function () {
    $standardSchool = School::factory()->create();
    activateSchool($standardSchool, PlanKey::Standard);

    $response = $this->actingAs($this->superAdmin)->get(route('super-admin.result-pins.index'));

    $response->assertOk()
        ->assertSee($this->basicSchool->name)
        ->assertDontSee($standardSchool->name);
});

test('a super admin can bulk-generate any quantity of PINs for a basic-plan school', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.result-pins.generate', $this->basicSchool), ['quantity' => 1000])
        ->assertRedirect();

    $pins = ResultCheckingPin::where('school_id', $this->basicSchool->id)->get();
    expect($pins)->toHaveCount(1000);
    expect($pins->pluck('code')->unique())->toHaveCount(1000);
    expect($pins->every(fn ($pin) => $pin->examination_id === null))->toBeTrue();
    expect($pins->every(fn ($pin) => $pin->status === ResultCheckingPinStatus::Active))->toBeTrue();
});

test('a super admin cannot generate PINs for a standard-plan school', function () {
    $standardSchool = School::factory()->create();
    activateSchool($standardSchool, PlanKey::Standard);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.result-pins.generate', $standardSchool), ['quantity' => 10])
        ->assertForbidden();

    expect(ResultCheckingPin::where('school_id', $standardSchool->id)->count())->toBe(0);
});

test('a super admin cannot generate PINs for an exclusive-plan school', function () {
    $exclusiveSchool = School::factory()->create();
    activateSchool($exclusiveSchool, PlanKey::Exclusive);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.result-pins.generate', $exclusiveSchool), ['quantity' => 10])
        ->assertForbidden();

    expect(ResultCheckingPin::where('school_id', $exclusiveSchool->id)->count())->toBe(0);
});

test('a super admin can revoke a PIN', function () {
    $pin = ResultCheckingPin::factory()->create(['school_id' => $this->basicSchool->id]);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.result-pins.revoke', $pin))
        ->assertRedirect();

    expect($pin->fresh()->status)->toBe(ResultCheckingPinStatus::Revoked);
});

test('a school admin cannot access the super admin result-pins routes', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->basicSchool->id]);

    $this->actingAs($admin)
        ->get(route('super-admin.result-pins.index'))
        ->assertForbidden();
});
