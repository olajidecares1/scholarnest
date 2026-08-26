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

// -----------------------------------------------------------------------------
// Expiry is silent
// -----------------------------------------------------------------------------

test('an expired session says nothing about having expired', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->actingAs($admin)
        ->withSession(['idle_last_activity_web' => now()->subMinutes(4)])
        ->get(route('dashboard'));

    // Signed out and returned to their own login - and told nothing. The
    // person stepped away and came back; a banner explaining the interruption
    // only draws attention to something they had already worked out.
    $response->assertRedirect($this->school->portalLoginUrl('web'));

    expect(session('status'))->toBeNull()
        ->and(session('message'))->toBeNull();
});

test('an expired XHR session carries a redirect but no message', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->actingAs($admin)
        ->withSession(['idle_last_activity_web' => now()->subMinutes(4)])
        ->getJson(route('dashboard'));

    $response->assertStatus(401)
        ->assertJsonPath('redirect', $this->school->portalLoginUrl('web'))
        ->assertJsonMissingPath('message');
});

// -----------------------------------------------------------------------------
// Schools stay separate
// -----------------------------------------------------------------------------

test('each school returns to its own login, never another school\'s', function () {
    $other = School::factory()->create();
    activateSchool($other);

    $mine = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    $theirs = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $other->id]);

    $this->actingAs($mine)->post(route('logout'))
        ->assertRedirect($this->school->portalLoginUrl('web'));

    $this->actingAs($theirs)->post(route('logout'))
        ->assertRedirect($other->portalLoginUrl('web'));

    expect($this->school->portalLoginUrl('web'))->not->toBe($other->portalLoginUrl('web'));
});

test('logging out never lands on the registration page', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->actingAs($admin)->post(route('logout'));

    expect($response->headers->get('Location'))
        ->toContain($this->school->slug)
        ->not->toBe(route('register'));
});

// -----------------------------------------------------------------------------
// The back button cannot bring a dashboard back
// -----------------------------------------------------------------------------

test('protected school pages are not cacheable by the browser', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    // Stops the browser showing a cached dashboard on Back without making a
    // request. The server-side auth check on the next real request is the
    // actual boundary; this closes the window where no request happens at all.
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('a signed-out user cannot reach the dashboard again', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->actingAs($admin)->post(route('logout'));

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
