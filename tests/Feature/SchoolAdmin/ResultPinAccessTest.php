<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\School;
use App\Models\User;

function resultPinSchoolAdmin(PlanKey $planKey): User
{
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    activateSchool($school, $planKey);

    return $admin;
}

test('a standard-plan school admin is forbidden from viewing the result-pins page', function () {
    $admin = resultPinSchoolAdmin(PlanKey::Standard);

    $this->actingAs($admin)
        ->get(route('result-pins.index'))
        ->assertForbidden();
});

test('an exclusive-plan school admin is forbidden from viewing the result-pins page', function () {
    $admin = resultPinSchoolAdmin(PlanKey::Exclusive);

    $this->actingAs($admin)
        ->get(route('result-pins.index'))
        ->assertForbidden();
});

test('a standard-plan school admin cannot assign a pin via a direct form submission', function () {
    $admin = resultPinSchoolAdmin(PlanKey::Standard);
    $examination = Examination::factory()->create(['school_id' => $admin->school_id]);
    $pin = ResultCheckingPin::factory()->create(['school_id' => $admin->school_id, 'examination_id' => null]);

    $this->actingAs($admin)
        ->post(route('result-pins.assign', $pin), ['examination_id' => $examination->id])
        ->assertForbidden();

    expect($pin->fresh()->examination_id)->toBeNull();
});

test('a standard-plan school admin cannot revoke a pin via a direct request', function () {
    $admin = resultPinSchoolAdmin(PlanKey::Standard);
    $pin = ResultCheckingPin::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)
        ->post(route('result-pins.revoke', $pin))
        ->assertForbidden();
});

test('a basic-plan school admin can reach the result-pins page', function () {
    $admin = resultPinSchoolAdmin(PlanKey::Basic);

    $this->actingAs($admin)
        ->get(route('result-pins.index'))
        ->assertOk();
});
