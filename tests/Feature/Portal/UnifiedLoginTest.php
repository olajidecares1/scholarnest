<?php

use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\PortalSession;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->school = School::factory()->create(['is_active' => true]);
    activateSchool($this->school);
});

// -----------------------------------------------------------------------------
// No school code, for every kind of account.
// -----------------------------------------------------------------------------

test('the shared sign-in page does not ask for a school code', function () {
    $this->get(route('portal.show'))
        ->assertOk()
        ->assertDontSee('School Code')
        ->assertDontSee('name="school_code"', false);
});

test('a school admin signs in on the shared page without a school code', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $response = $this->post(route('portal.attempt'), [
        'role' => 'web',
        'login' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin, 'web');
    $response->assertRedirect(route('dashboard', absolute: false));

    $portalSession = PortalSession::where('guard', 'web')->first();
    expect($portalSession)->not->toBeNull()
        ->and($portalSession->school_id)->toBe($this->school->id)
        ->and($portalSession->authenticatable_id)->toBe($admin->id)
        ->and(strlen($portalSession->token))->toBeGreaterThanOrEqual(22);
});

test('a student signs in on the shared page without a school code', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $response = $this->post(route('portal.attempt'), [
        'role' => 'student',
        'login' => $student->admission_number,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($student, 'student');
    $response->assertRedirect(route('student.dashboard', $this->school, absolute: false));
});

test('a staff member signs in on the shared page without a school code', function () {
    $staff = Staff::factory()->create(['school_id' => $this->school->id]);

    $response = $this->post(route('portal.attempt'), [
        'role' => 'staff',
        'login' => $staff->staff_number,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($staff, 'staff');
    $response->assertRedirect(route('staff.dashboard', $this->school, absolute: false));
});

test('a parent signs in on the shared page with a Parent ID or a phone number', function () {
    $guardian = Guardian::factory()->create([
        'school_id' => $this->school->id,
        'guardian_number' => 'PAR-UNIFIED-1',
        'phone' => '0803 123 4567',
    ]);

    $this->post(route('portal.attempt'), [
        'role' => 'guardian',
        'login' => 'PAR-UNIFIED-1',
        'password' => 'password',
    ])->assertRedirect(route('guardian.dashboard', $this->school, absolute: false));

    $this->assertAuthenticatedAs($guardian, 'guardian');

    auth('guardian')->logout();

    $this->post(route('portal.attempt'), [
        'role' => 'guardian',
        'login' => '+2348031234567',
        'password' => 'password',
    ])->assertRedirect(route('guardian.dashboard', $this->school, absolute: false));

    $this->assertAuthenticatedAs($guardian, 'guardian');
});

// -----------------------------------------------------------------------------
// The same identifier at two schools.
// -----------------------------------------------------------------------------

test('the password decides between two schools that share an admission number', function () {
    $other = School::factory()->create(['is_active' => true]);
    activateSchool($other);

    Student::factory()->create(['school_id' => $other->id, 'admission_number' => 'ADM-001']);
    $mine = Student::factory()->create([
        'school_id' => $this->school->id,
        'admission_number' => 'ADM-001',
        'password' => Hash::make('Mine@12345'),
    ]);

    $this->post(route('portal.attempt'), [
        'role' => 'student',
        'login' => 'ADM-001',
        'password' => 'Mine@12345',
    ])->assertRedirect(route('student.dashboard', $this->school, absolute: false));

    $this->assertAuthenticatedAs($mine, 'student');
});

test('a person with the same details at two schools is asked which school, and shown only those', function () {
    $second = School::factory()->create(['is_active' => true, 'name' => 'Second School']);
    activateSchool($second);
    $unrelated = School::factory()->create(['is_active' => true, 'name' => 'Unrelated School']);

    Staff::factory()->create(['school_id' => $this->school->id, 'staff_number' => 'TCH-100']);
    $atSecond = Staff::factory()->create(['school_id' => $second->id, 'staff_number' => 'TCH-100']);

    $this->post(route('portal.attempt'), [
        'role' => 'staff',
        'login' => 'TCH-100',
        'password' => 'password',
    ])->assertSessionHasErrors('school');

    $this->assertGuest('staff');

    $this->get(route('portal.show'))
        ->assertSee('Choose your school')
        ->assertSee('Second School')
        ->assertSee($this->school->name)
        ->assertDontSee('Unrelated School');

    $this->post(route('portal.attempt'), [
        'role' => 'staff',
        'login' => 'TCH-100',
        'password' => 'password',
        'school' => $second->portal_key,
    ])->assertRedirect(route('staff.dashboard', $second, absolute: false));

    $this->assertAuthenticatedAs($atSecond, 'staff');
});

test('a school that was not offered cannot be chosen', function () {
    $other = School::factory()->create(['is_active' => true]);
    activateSchool($other);
    Staff::factory()->create(['school_id' => $other->id, 'staff_number' => 'TCH-200']);

    $this->post(route('portal.attempt'), [
        'role' => 'staff',
        'login' => 'TCH-200',
        'password' => 'password',
        'school' => $other->portal_key,
    ])->assertSessionHasErrors('login');

    $this->assertGuest('staff');
});

// -----------------------------------------------------------------------------
// Failing safely.
// -----------------------------------------------------------------------------

test('a wrong password fails without saying whether the account exists', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $wrong = $this->post(route('portal.attempt'), [
        'role' => 'web',
        'login' => $admin->email,
        'password' => 'wrong-password',
    ]);

    $unknown = $this->post(route('portal.attempt'), [
        'role' => 'web',
        'login' => 'nobody@example.test',
        'password' => 'wrong-password',
    ]);

    $wrong->assertSessionHasErrors(['login' => trans('auth.failed')]);
    $unknown->assertSessionHasErrors(['login' => trans('auth.failed')]);
    $this->assertGuest('web');
    expect(PortalSession::count())->toBe(0);
});

test('the shared sign-in is rate limited after five failed attempts', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('portal.attempt'), [
            'role' => 'web',
            'login' => $admin->email,
            'password' => 'wrong-password',
        ]);
    }

    $this->post(route('portal.attempt'), [
        'role' => 'web',
        'login' => $admin->email,
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest('web');
});

test('a deactivated account at the only matching school is still refused', function () {
    $staff = Staff::factory()->create(['school_id' => $this->school->id, 'is_active' => false]);

    $this->post(route('portal.attempt'), [
        'role' => 'staff',
        'login' => $staff->staff_number,
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest('staff');
});

// -----------------------------------------------------------------------------
// What already worked keeps working.
// -----------------------------------------------------------------------------

test('a school code is still accepted when one is sent', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->post(route('portal.attempt'), [
        'role' => 'web',
        'school_code' => $this->school->school_code,
        'login' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin, 'web');
});

test('an unknown school code is refused without revealing whether it exists', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->post(route('portal.attempt'), [
        'role' => 'web',
        'school_code' => 'NOT-A-REAL-CODE',
        'login' => $admin->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['login' => trans('auth.failed')]);

    $this->assertGuest('web');
    expect(PortalSession::count())->toBe(0);
});

test('on a school address the school comes from the address', function () {
    config(['custom_domain.tenant_base_domain' => 'akademicnest-test.com']);
    config(['app.url' => 'https://akademicnest-test.com']);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->post("http://{$this->school->subdomain}.akademicnest-test.com/portal/sign-in", [
        'role' => 'web',
        'login' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin, 'web');
    expect(PortalSession::where('guard', 'web')->first()->school_id)->toBe($this->school->id);
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
