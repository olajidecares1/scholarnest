<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('the users list shows all users', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.users.index'))
        ->assertStatus(200)
        ->assertSee('Jane Doe');
});

test('super admin can deactivate a user', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.users.deactivate', $user))
        ->assertRedirect();

    expect($user->fresh()->is_active)->toBeFalse();
});

test('super admin can reactivate a user', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.users.activate', $user))
        ->assertRedirect();

    expect($user->fresh()->is_active)->toBeTrue();
});

test('super admin cannot deactivate their own account', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.users.deactivate', $this->superAdmin))
        ->assertForbidden();

    expect($this->superAdmin->fresh()->is_active)->toBeTrue();
});

test('a deactivated user cannot log in', function () {
    $user = User::factory()->create(['is_active' => false]);

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest();
});

test('super admin can view the add admin form with a list of schools', function () {
    $school = School::factory()->create(['name' => 'Greenfield Academy']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.users.create'))
        ->assertStatus(200)
        ->assertSee('Add Admin')
        ->assertSee('Greenfield Academy');
});

test('super admin can add an admin to an existing school', function () {
    $school = School::factory()->create();

    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.users.store'), [
        'school_id' => $school->id,
        'name' => 'Jane Doe',
        'email' => 'jane@greenfield.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('super-admin.users.index'));

    $admin = User::where('email', 'jane@greenfield.test')->firstOrFail();
    expect($admin->name)->toBe('Jane Doe');
    expect($admin->role)->toBe(UserRole::SchoolAdmin);
    expect($admin->school_id)->toBe($school->id);
});

test('adding an admin requires a valid school', function () {
    $this->actingAs($this->superAdmin)->post(route('super-admin.users.store'), [
        'school_id' => 99999,
        'name' => 'Jane Doe',
        'email' => 'jane@greenfield.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors('school_id');

    expect(User::where('email', 'jane@greenfield.test')->exists())->toBeFalse();
});
