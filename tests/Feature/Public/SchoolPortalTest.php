<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function schoolAdminUser(School $school, array $overrides = []): User
{
    return User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $school->id,
        'password' => Hash::make('password'),
        ...$overrides,
    ]);
}

function adminLoginUrl(School $school): string
{
    return route('portal.admin.login', ['school' => $school, 'token' => $school->portal_admin_token]);
}

test('the portal hub renders all four tokenized login links on the default path', function () {
    $school = School::factory()->create();

    $response = $this->get(route('portal.index', $school));

    $response->assertOk()
        ->assertSee(adminLoginUrl($school), false)
        ->assertSee(route('staff.login', ['school' => $school, 'token' => $school->portal_staff_token]), false)
        ->assertSee(route('student.login', ['school' => $school, 'token' => $school->portal_student_token]), false)
        ->assertSee(route('guardian.login', ['school' => $school, 'token' => $school->portal_guardian_token]), false);
});

test('a portal login 404s without the correct token', function () {
    $school = School::factory()->create();

    $this->get(route('portal.admin.login', ['school' => $school, 'token' => 'not-the-right-token']))
        ->assertNotFound();

    $this->get(adminLoginUrl($school))->assertOk();
});

test('each school gets its own distinct, long tokens per portal', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();

    expect(strlen($schoolA->portal_admin_token))->toBeGreaterThanOrEqual(20);
    expect(strlen($schoolA->portal_staff_token))->toBeGreaterThanOrEqual(20);
    expect(strlen($schoolA->portal_student_token))->toBeGreaterThanOrEqual(20);
    expect(strlen($schoolA->portal_guardian_token))->toBeGreaterThanOrEqual(20);

    expect($schoolA->portal_admin_token)->not->toBe($schoolB->portal_admin_token);
    expect($schoolA->portal_admin_token)->not->toBe($schoolA->portal_staff_token);
});

test('a school\'s tokens stay the same across requests, not regenerated on refresh', function () {
    $school = School::factory()->create();
    $token = $school->portal_admin_token;

    $this->get(adminLoginUrl($school))->assertOk();
    $this->get(adminLoginUrl($school))->assertOk();

    expect($school->fresh()->portal_admin_token)->toBe($token);
});

test('the portal hub is also reachable via the school\'s subdomain', function () {
    config(['custom_domain.tenant_base_domain' => 'ednumest.com']);
    $school = School::factory()->create(['is_active' => true]);
    activateSchool($school, PlanKey::Standard);

    $this->get("http://{$school->slug}.ednumest.com/portal")
        ->assertOk()
        ->assertSee($school->name);
});

test('all four tokenized logins are reachable via the school\'s subdomain, not just the default path', function () {
    config(['custom_domain.tenant_base_domain' => 'ednumest.com']);
    $school = School::factory()->create(['is_active' => true]);
    activateSchool($school, PlanKey::Standard);

    $this->get("http://{$school->slug}.ednumest.com/portal/admin/{$school->portal_admin_token}/login")->assertOk();
    $this->get("http://{$school->slug}.ednumest.com/staff-portal/{$school->portal_staff_token}/login")->assertOk();
    $this->get("http://{$school->slug}.ednumest.com/portal/{$school->portal_student_token}/login")->assertOk();
    $this->get("http://{$school->slug}.ednumest.com/parent-portal/{$school->portal_guardian_token}/login")->assertOk();
});

test('the portal hub\'s links point at the subdomain with the correct tokens when the school has one', function () {
    config(['custom_domain.tenant_base_domain' => 'ednumest.com']);
    config(['app.url' => 'https://ednumest.com']);
    $school = School::factory()->create(['is_active' => true]);
    activateSchool($school, PlanKey::Standard);

    $this->get("http://{$school->slug}.ednumest.com/portal")
        ->assertOk()
        ->assertSee("https://{$school->slug}.ednumest.com/portal/admin/{$school->portal_admin_token}/login", false)
        ->assertSee("https://{$school->slug}.ednumest.com/staff-portal/{$school->portal_staff_token}/login", false)
        ->assertSee("https://{$school->slug}.ednumest.com/portal/{$school->portal_student_token}/login", false)
        ->assertSee("https://{$school->slug}.ednumest.com/parent-portal/{$school->portal_guardian_token}/login", false);
});

test('logging out of a session started on the subdomain returns there, not the default path', function () {
    config(['custom_domain.tenant_base_domain' => 'ednumest.com']);
    config(['app.url' => 'https://ednumest.com']);
    $school = School::factory()->create(['is_active' => true]);
    activateSchool($school, PlanKey::Standard);
    $admin = schoolAdminUser($school, ['email' => 'admin@subdomain-school.test']);

    $this->post("http://{$school->slug}.ednumest.com/portal/admin/{$school->portal_admin_token}/login", [
        'login' => 'admin@subdomain-school.test',
        'password' => 'password',
    ]);
    $this->assertAuthenticatedAs($admin, 'web');

    $response = $this->post(route('portal.admin.logout', $school));

    $response->assertRedirect("https://{$school->slug}.ednumest.com/portal/admin/{$school->portal_admin_token}/login");
});

test('a school admin can log in through their own school\'s portal', function () {
    $school = School::factory()->create();
    schoolAdminUser($school, ['email' => 'admin@school-a.test']);

    $response = $this->post(adminLoginUrl($school), [
        'login' => 'admin@school-a.test',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated('web');
});

test('a school admin cannot log in through a different school\'s portal', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    schoolAdminUser($schoolA, ['email' => 'admin@school-a.test']);

    $response = $this->post(adminLoginUrl($schoolB), [
        'login' => 'admin@school-a.test',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest('web');
});

test('an inactive school admin cannot log in through the portal', function () {
    $school = School::factory()->create();
    schoolAdminUser($school, ['email' => 'admin@school.test', 'is_active' => false]);

    $response = $this->post(adminLoginUrl($school), [
        'login' => 'admin@school.test',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest('web');
});

test('a school admin of a deactivated school cannot log in through the portal', function () {
    $school = School::factory()->create(['is_active' => false]);
    schoolAdminUser($school, ['email' => 'admin@school.test']);

    $response = $this->post(adminLoginUrl($school), [
        'login' => 'admin@school.test',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest('web');
});

test('logging out from the dashboard returns the admin to their own school\'s portal login', function () {
    $school = School::factory()->create();
    $admin = schoolAdminUser($school);

    $response = $this->actingAs($admin)->post(route('portal.admin.logout', $school));

    $response->assertRedirect(adminLoginUrl($school));
    $this->assertGuest('web');
});

test('super admin logout is unaffected, still redirecting to the global login', function () {
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $response = $this->actingAs($superAdmin)->post(route('logout'));

    $response->assertRedirect(route('login'));
});

test('failed portal login attempts are throttled across schools, not per school', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    schoolAdminUser($schoolA, ['email' => 'shared@admin.test']);

    // 5 failed attempts (MAX_ATTEMPTS), split across two different schools'
    // portal login pages, all for the same email+IP.
    foreach ([$schoolA, $schoolA, $schoolA, $schoolB, $schoolB] as $school) {
        $this->post(adminLoginUrl($school), [
            'login' => 'shared@admin.test',
            'password' => 'wrong-password',
        ]);
    }

    // A 6th attempt, even against a THIRD school's page, should now be
    // throttled rather than get the generic "invalid credentials" message -
    // proving the lockout isn't scoped per-school.
    $schoolC = School::factory()->create();

    $response = $this->post(adminLoginUrl($schoolC), [
        'login' => 'shared@admin.test',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('login');
    expect(session()->get('errors')->get('login')[0])->toContain('Too many login attempts');
});
