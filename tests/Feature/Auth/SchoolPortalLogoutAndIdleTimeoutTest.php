<?php

use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    activateSchool($this->school);
});

test('a school admin is redirected to their own school\'s portal login when logging out from the shared login page', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->actingAs($admin)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect($this->school->portalLoginUrl('web'));
});

test('a super admin logging out from the shared login page still returns to the shared login page', function () {
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $response = $this->actingAs($superAdmin)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('a school admin logging out from the school-scoped admin portal returns to that portal\'s login', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->actingAs($admin)->post(route('portal.admin.logout', $this->school));

    $this->assertGuest();
    $response->assertRedirect($this->school->portalLoginUrl('web'));
});

test('an idle school admin session is terminated and redirected to their school login', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->actingAs($admin)
        ->withSession(['idle_last_activity_web' => now()->subMinutes(4)])
        ->get(route('dashboard'));

    $this->assertGuest();
    $response->assertRedirect($this->school->portalLoginUrl('web'));
});

test('an active school admin session is not terminated within the idle window', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->actingAs($admin)
        ->withSession(['idle_last_activity_web' => now()->subMinute()])
        ->get(route('dashboard'));

    $this->assertAuthenticated();
    $response->assertStatus(200);
});

test('a super admin session never expires from the school-portal idle timeout', function () {
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $response = $this->actingAs($superAdmin)
        ->withSession(['idle_last_activity_web' => now()->subMinutes(10)])
        ->get(route('super-admin.dashboard'));

    $this->assertAuthenticated();
    $response->assertStatus(200);
});

test('an idle student session is terminated and redirected to their school\'s student login', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $response = $this->actingAs($student, 'student')
        ->withSession(['idle_last_activity_student' => now()->subMinutes(4)])
        ->get(route('student.dashboard', $this->school));

    $this->assertGuest('student');
    $response->assertRedirect($this->school->portalLoginUrl('student'));
});

test('an idle staff session is terminated and redirected to their school\'s staff login', function () {
    $staff = Staff::factory()->create(['school_id' => $this->school->id]);

    $response = $this->actingAs($staff, 'staff')
        ->withSession(['idle_last_activity_staff' => now()->subMinutes(4)])
        ->get(route('staff.dashboard', $this->school));

    $this->assertGuest('staff');
    $response->assertRedirect($this->school->portalLoginUrl('staff'));
});

test('an idle guardian session is terminated and redirected to their school\'s guardian login', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $guardian->students()->attach($student->id);

    $response = $this->actingAs($guardian, 'guardian')
        ->withSession(['idle_last_activity_guardian' => now()->subMinutes(4)])
        ->get(route('guardian.dashboard', $this->school));

    $this->assertGuest('guardian');
    $response->assertRedirect($this->school->portalLoginUrl('guardian'));
});

test('portalLoginUrl resolves the correct tokenized login route per guard', function () {
    expect($this->school->portalLoginUrl('web'))->toContain((string) $this->school->portal_admin_token);
    expect($this->school->portalLoginUrl('student'))->toContain((string) $this->school->portal_student_token);
    expect($this->school->portalLoginUrl('staff'))->toContain((string) $this->school->portal_staff_token);
    expect($this->school->portalLoginUrl('guardian'))->toContain((string) $this->school->portal_guardian_token);
});
