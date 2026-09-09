<?php

use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\PortalSession;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create(['is_active' => true]);
    activateSchool($this->school);
});

test('a school admin can sign in through the unified portal on the default host using a school code', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->post(route('portal.attempt'), [
        'role' => 'web',
        'school_code' => $this->school->school_code,
        'login' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin, 'web');
    $response->assertRedirect(route('dashboard', absolute: false));

    $portalSession = PortalSession::where('guard', 'web')->first();
    expect($portalSession)->not->toBeNull();
    expect($portalSession->school_id)->toBe($this->school->id);
    expect($portalSession->authenticatable_id)->toBe($admin->id);
    expect(strlen($portalSession->token))->toBeGreaterThanOrEqual(22);
});

test('a student can sign in through the unified portal on the default host using a school code', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $response = $this->post(route('portal.attempt'), [
        'role' => 'student',
        'school_code' => $this->school->school_code,
        'login' => $student->admission_number,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($student, 'student');
    $response->assertRedirect(route('student.dashboard', $this->school, absolute: false));

    expect(PortalSession::where('guard', 'student')->where('school_id', $this->school->id)->exists())->toBeTrue();
});

test('a staff member can sign in through the unified portal on the default host using a school code', function () {
    $staff = Staff::factory()->create(['school_id' => $this->school->id]);

    $response = $this->post(route('portal.attempt'), [
        'role' => 'staff',
        'school_code' => $this->school->school_code,
        'login' => $staff->staff_number,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($staff, 'staff');
    $response->assertRedirect(route('staff.dashboard', $this->school, absolute: false));

    expect(PortalSession::where('guard', 'staff')->where('school_id', $this->school->id)->exists())->toBeTrue();
});

test('a guardian can sign in through the unified portal on the default host using a school code', function () {
    // A parent's login is their Parent ID or phone number, never their email -
    // and the unified form's own field is already called "login", so the
    // guardian branch is no longer a special case here.
    $guardian = Guardian::factory()->create([
        'school_id' => $this->school->id,
        'guardian_number' => 'PAR-UNIFIED-1',
    ]);

    $response = $this->post(route('portal.attempt'), [
        'role' => 'guardian',
        'school_code' => $this->school->school_code,
        'login' => 'PAR-UNIFIED-1',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($guardian, 'guardian');
    $response->assertRedirect(route('guardian.dashboard', $this->school, absolute: false));

    expect(PortalSession::where('guard', 'guardian')->where('school_id', $this->school->id)->exists())->toBeTrue();
});

test('the default host requires a school code and rejects an unknown one without revealing whether it exists', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->post(route('portal.attempt'), [
        'role' => 'web',
        'school_code' => 'NOT-A-REAL-CODE',
        'login' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest('web');
    expect(PortalSession::count())->toBe(0);
});

test('the school code field is not required when the school is resolved from the tenant domain', function () {
    config(['custom_domain.tenant_base_domain' => 'akademicnest-test.com']);
    config(['app.url' => 'https://akademicnest-test.com']);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->post("http://{$this->school->subdomain}.akademicnest-test.com/portal/sign-in", [
        'role' => 'web',
        'login' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin, 'web');
    $portalSession = PortalSession::where('guard', 'web')->first();
    expect($portalSession->school_id)->toBe($this->school->id);
});

test('a wrong password fails to authenticate and creates no portal session', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->post(route('portal.attempt'), [
        'role' => 'web',
        'school_code' => $this->school->school_code,
        'login' => $admin->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest('web');
    expect(PortalSession::count())->toBe(0);
});

test('the unified login is rate limited after five failed attempts', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('portal.attempt'), [
            'role' => 'web',
            'school_code' => $this->school->school_code,
            'login' => $admin->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('portal.attempt'), [
        'role' => 'web',
        'school_code' => $this->school->school_code,
        'login' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest('web');
});

test('the old per-guard login pages keep working untouched alongside the new unified one', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->post(route('portal.admin.login', ['school' => $this->school, 'token' => $this->school->portal_admin_token]), [
        'login' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin, 'web');
    $response->assertRedirect(route('dashboard', absolute: false));
});
