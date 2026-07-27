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

    $response = $this->post(route('login'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest();
});

test('super admin can view the add school form', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.schools.create'))
        ->assertStatus(200)
        ->assertSee('Add School');
});

test('super admin can create a school with an admin account', function () {
    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.schools.store'), [
        'school_name' => 'Greenfield Academy',
        'admin_name' => 'Jane Doe',
        'admin_email' => 'jane@greenfield.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $school = School::where('name', 'Greenfield Academy')->firstOrFail();

    $response->assertRedirect(route('super-admin.schools.show', $school));

    $admin = User::where('email', 'jane@greenfield.test')->firstOrFail();
    expect($admin->name)->toBe('Jane Doe');
    expect($admin->role)->toBe(UserRole::SchoolAdmin);
    expect($admin->school_id)->toBe($school->id);
});

test('creating a school requires a unique admin email', function () {
    User::factory()->create(['email' => 'taken@greenfield.test']);

    $this->actingAs($this->superAdmin)->post(route('super-admin.schools.store'), [
        'school_name' => 'Greenfield Academy',
        'admin_name' => 'Jane Doe',
        'admin_email' => 'taken@greenfield.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors('admin_email');

    expect(School::where('name', 'Greenfield Academy')->exists())->toBeFalse();
});
