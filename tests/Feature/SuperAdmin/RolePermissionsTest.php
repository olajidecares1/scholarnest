<?php

use App\Enums\UserRole;
use App\Models\AdminRole;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('a full-access super admin can view roles and reach every restricted section', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.roles.index'))
        ->assertStatus(200)
        ->assertSee('Roles & Permissions')
        ->assertSee('Full Access');

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.schools.index'))
        ->assertStatus(200);
});

test('super admin can create a role with specific permissions', function () {
    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.roles.store'), [
        'name' => 'Finance Reviewer',
        'permissions' => ['manage_payments', 'manage_subscriptions'],
    ]);

    $response->assertRedirect(route('super-admin.roles.index'));

    $role = AdminRole::where('name', 'Finance Reviewer')->firstOrFail();
    expect($role->permissions)->toBe(['manage_payments', 'manage_subscriptions']);
});

test('super admin can add a team member restricted to a role', function () {
    $role = AdminRole::factory()->create(['permissions' => ['manage_payments']]);

    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.roles.team.store'), [
        'name' => 'Finance Assistant',
        'email' => 'finance@edunest.test',
        'admin_role_id' => $role->id,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('super-admin.roles.index'));

    $member = User::where('email', 'finance@edunest.test')->firstOrFail();
    expect($member->role)->toBe(UserRole::SuperAdmin);
    expect($member->admin_role_id)->toBe($role->id);
});

test('a restricted team member can only access permitted sections', function () {
    $role = AdminRole::factory()->create(['permissions' => ['manage_payments']]);
    $member = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'admin_role_id' => $role->id,
    ]);

    $this->actingAs($member)
        ->get(route('super-admin.payments.index'))
        ->assertStatus(200);

    $this->actingAs($member)
        ->get(route('super-admin.schools.index'))
        ->assertForbidden();
});

test('deleting a role clears it from team members instead of blocking them', function () {
    $role = AdminRole::factory()->create(['permissions' => ['manage_payments']]);
    $member = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'admin_role_id' => $role->id,
    ]);

    $this->actingAs($this->superAdmin)
        ->delete(route('super-admin.roles.destroy', $role))
        ->assertRedirect();

    expect($member->fresh()->admin_role_id)->toBeNull();
    expect(AdminRole::find($role->id))->toBeNull();
});
