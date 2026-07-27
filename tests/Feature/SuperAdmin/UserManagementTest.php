<?php

use App\Enums\UserRole;
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
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
