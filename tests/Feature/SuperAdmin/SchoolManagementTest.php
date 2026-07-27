<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('the schools list shows all schools', function () {
    $school = School::factory()->create(['name' => 'Greenfield Academy']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.schools.index'))
        ->assertStatus(200)
        ->assertSee('Greenfield Academy');
});

test('super admin can deactivate a school', function () {
    $school = School::factory()->create(['is_active' => true]);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.schools.deactivate', $school))
        ->assertRedirect();

    $school->refresh();
    expect($school->is_active)->toBeFalse();
    expect($school->deactivated_at)->not->toBeNull();
});

test('super admin can reactivate a school', function () {
    $school = School::factory()->create(['is_active' => false, 'deactivated_at' => now()]);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.schools.activate', $school))
        ->assertRedirect();

    $school->refresh();
    expect($school->is_active)->toBeTrue();
    expect($school->deactivated_at)->toBeNull();
});

test('a school admin belonging to a deactivated school cannot log in', function () {
    $school = School::factory()->create(['is_active' => false]);
    $user = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
