<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;

/**
 * Signing in to one portal must never land you in another.
 *
 * All four portals share one session in a browser, and with it the
 * "intended" page Laravel remembers when a guest asks for a signed-in page.
 * A Staff Portal page asked for while signed out (a bookmark, the installed
 * Staff app, the offline worker refreshing kept pages) left a Staff Portal
 * address in the session, and the School Admin's next sign-in followed it, so
 * admins found themselves at the Staff Portal login instead of their
 * dashboard.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create());
    $this->admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $this->school->id,
        'email' => 'head@example.com',
    ]);
    $this->staff = Staff::factory()->create([
        'school_id' => $this->school->id,
        'staff_number' => 'STF-7001',
    ]);
});

function adminLogin(School $school): string
{
    return route('portal.admin.login', ['school' => $school, 'token' => $school->portal_admin_token]);
}

test('a School Admin lands on their dashboard even after a Staff Portal page was requested while signed out', function () {
    // A guest asks for the staff dashboard: Laravel remembers it as "intended".
    $this->get(route('staff.dashboard', $this->school))->assertRedirect();

    expect(session('url.intended'))->toBe(route('staff.dashboard', $this->school));

    $this->post(adminLogin($this->school), [
        'login' => 'head@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($this->admin, 'web');
    expect(session('url.intended'))->toBeNull();
});

test('a School Admin is still returned to the admin page they were on before their session ended', function () {
    $this->get(route('settings.index'))->assertRedirect();

    $this->post(adminLogin($this->school), [
        'login' => 'head@example.com',
        'password' => 'password',
    ])->assertRedirect(route('settings.index'));
});

test('a School Admin is never sent on to a Super Admin page', function () {
    $this->get(route('super-admin.dashboard'))->assertRedirect();

    $this->post(adminLogin($this->school), [
        'login' => 'head@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));
});

test('a teacher lands on the staff dashboard even after a School Admin page was requested while signed out', function () {
    $this->get(route('settings.index'))->assertRedirect();

    $this->post(route('staff.login', ['school' => $this->school, 'token' => $this->school->portal_staff_token]), [
        'login' => 'STF-7001',
        'password' => 'password',
    ])->assertRedirect(route('staff.dashboard', $this->school, absolute: false));

    $this->assertAuthenticatedAs($this->staff, 'staff');
});

test('a teacher is still returned to the staff page they were on before their session ended', function () {
    $this->get(route('staff.settings.index', $this->school))->assertRedirect();

    $this->post(route('staff.login', ['school' => $this->school, 'token' => $this->school->portal_staff_token]), [
        'login' => 'STF-7001',
        'password' => 'password',
    ])->assertRedirect(route('staff.settings.index', $this->school));
});

test('the shared sign-in sends a School Admin to the admin dashboard, not a stale Staff Portal page', function () {
    $this->get(route('staff.dashboard', $this->school))->assertRedirect();

    $this->post(route('portal.attempt'), [
        'role' => 'web',
        'login' => 'head@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($this->admin, 'web');
});
