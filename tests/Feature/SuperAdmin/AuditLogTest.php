<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('a successful login is recorded in the audit log', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    expect(AuditLog::where('action', 'login')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('a failed login attempt is recorded in the audit log', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    expect(AuditLog::where('action', 'login.failed')->exists())->toBeTrue();
});

test('activating and deactivating a school is recorded in the audit log', function () {
    $school = School::factory()->create(['is_active' => true]);

    $this->actingAs($this->superAdmin)->post(route('super-admin.schools.deactivate', $school));

    expect(AuditLog::where('action', 'school.deactivated')->where('subject_id', $school->id)->exists())->toBeTrue();
});

test('updating settings is recorded in the audit log', function () {
    $this->actingAs($this->superAdmin)->put(route('super-admin.settings.update'), [
        'site_name' => 'Updated Name',
        'notification_from_name' => 'EduNest',
        'maintenance_mode' => '0',
    ]);

    expect(AuditLog::where('action', 'settings.updated')->exists())->toBeTrue();
});

test('super admin can view and filter the audit log page', function () {
    AuditLog::factory()->create(['action' => 'school.activated', 'description' => 'Activated Greenfield Academy']);
    AuditLog::factory()->create(['action' => 'login']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.audit-logs.index', ['action' => 'school.activated']))
        ->assertStatus(200)
        ->assertSee('Activated Greenfield Academy');
});
