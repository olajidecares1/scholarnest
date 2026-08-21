<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The password reset authority rule.
 *
 * Staff, Students and Parents/Guardians have NO self-service password
 * recovery. Only their own School Admin may reset their password, and only for
 * accounts belonging to that admin's own school.
 *
 * These tests exist because the interface hiding a button proves nothing. Every
 * restriction below is asserted against the server, the way an attacker would
 * reach it: by posting straight at the route.
 *
 * The full policy is documented in docs/PASSWORD-RESET-POLICY.md.
 */
beforeEach(function () {
    $this->school = School::factory()->create();
    activateSchool($this->school);

    $this->admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $this->school->id,
    ]);
});

// -----------------------------------------------------------------------------
// A School Admin may reset accounts in their own school
// -----------------------------------------------------------------------------

test('a school admin can reset a student password in their own school', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->put(route('students.update-password', $student), [
            'password' => 'Temp-Password-123!',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $student->refresh();

    expect(Hash::check('Temp-Password-123!', $student->password))->toBeTrue()
        ->and($student->must_change_password)->toBeTrue();
});

test('a school admin can reset a staff password in their own school', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->put(route('staff.update-password', $member), [
            'password' => 'Temp-Password-123!',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $member->refresh();

    expect(Hash::check('Temp-Password-123!', $member->password))->toBeTrue()
        ->and($member->must_change_password)->toBeTrue();
});

test('a school admin can reset a guardian password in their own school', function () {
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->put(route('guardians.update-password', $guardian), [
            'password' => 'Temp-Password-123!',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $guardian->refresh();

    expect(Hash::check('Temp-Password-123!', $guardian->password))->toBeTrue()
        ->and($guardian->must_change_password)->toBeTrue();
});

// -----------------------------------------------------------------------------
// A School Admin may NOT reach into another school
// -----------------------------------------------------------------------------

test('a school admin cannot reset a student belonging to another school', function () {
    $otherSchool = School::factory()->create();
    $victim = Student::factory()->create(['school_id' => $otherSchool->id]);
    $originalHash = $victim->password;

    $this->actingAs($this->admin)
        ->put(route('students.update-password', $victim), [
            'password' => 'Attacker-Pass-123!',
        ])
        ->assertForbidden();

    expect($victim->refresh()->password)->toBe($originalHash);
});

test('a school admin cannot reset a staff member belonging to another school', function () {
    $otherSchool = School::factory()->create();
    $victim = Staff::factory()->create(['school_id' => $otherSchool->id]);
    $originalHash = $victim->password;

    $this->actingAs($this->admin)
        ->put(route('staff.update-password', $victim), [
            'password' => 'Attacker-Pass-123!',
        ])
        ->assertForbidden();

    expect($victim->refresh()->password)->toBe($originalHash);
});

test('a school admin cannot reset a guardian belonging to another school', function () {
    $otherSchool = School::factory()->create();
    $victim = Guardian::factory()->create(['school_id' => $otherSchool->id]);
    $originalHash = $victim->password;

    $this->actingAs($this->admin)
        ->put(route('guardians.update-password', $victim), [
            'password' => 'Attacker-Pass-123!',
        ])
        ->assertForbidden();

    expect($victim->refresh()->password)->toBe($originalHash);
});

// -----------------------------------------------------------------------------
// Nobody else may use the reset endpoints, however they reach them
// -----------------------------------------------------------------------------

test('a signed-in student cannot reset any password through the admin endpoint', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $classmate = Student::factory()->create(['school_id' => $this->school->id]);
    $originalHash = $classmate->password;

    // Posting straight at the route, exactly as someone using developer tools
    // or a scripted request would. Being signed in to the student portal
    // carries no authority on the admin side: the request is refused outright.
    $this->actingAs($student, 'student')
        ->put(route('students.update-password', $classmate), [
            'password' => 'Student-Pass-123!',
        ])
        ->assertForbidden();

    $this->assertGuest('web');
    expect($classmate->refresh()->password)->toBe($originalHash);
});

test('a signed-in staff member cannot reset a student password', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $originalHash = $student->password;

    // Staff are explicitly NOT permitted to reset passwords. Only the School
    // Admin may, even for accounts in the same school.
    $this->actingAs($member, 'staff')
        ->put(route('students.update-password', $student), [
            'password' => 'Staff-Pass-123!',
        ])
        ->assertForbidden();

    expect($student->refresh()->password)->toBe($originalHash);
});

test('a signed-in guardian cannot reset a student password', function () {
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $guardian->students()->attach($student->id);
    $originalHash = $student->password;

    // Not even for their own child.
    $this->actingAs($guardian, 'guardian')
        ->put(route('students.update-password', $student), [
            'password' => 'Guardian-Pass-123!',
        ])
        ->assertForbidden();

    expect($student->refresh()->password)->toBe($originalHash);
});

test('a signed-out visitor cannot reset any password', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $originalHash = $student->password;

    $this->put(route('students.update-password', $student), [
        'password' => 'Anon-Pass-123!',
    ])->assertRedirect();

    expect($student->refresh()->password)->toBe($originalHash);
});

// -----------------------------------------------------------------------------
// A temporary password must be changed before the account can be used
// -----------------------------------------------------------------------------

test('a student given a temporary password is forced to change it before anything else', function () {
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'must_change_password' => true,
    ]);

    $this->actingAs($student, 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertRedirect(route('student.settings.index', $this->school));
});

test('a staff member given a temporary password is forced to change it before anything else', function () {
    $member = Staff::factory()->create([
        'school_id' => $this->school->id,
        'must_change_password' => true,
    ]);

    $this->actingAs($member, 'staff')
        ->get(route('staff.dashboard', $this->school))
        ->assertRedirect(route('staff.settings.index', $this->school));
});

test('a guardian given a temporary password is forced to change it before anything else', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $guardian = Guardian::factory()->create([
        'school_id' => $this->school->id,
        'must_change_password' => true,
    ]);
    $guardian->students()->attach($student->id);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard', $this->school))
        ->assertRedirect(route('guardian.settings.index', $this->school));
});

test('the settings page stays reachable so the password can actually be changed', function () {
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'must_change_password' => true,
    ]);

    // If this redirected too, the user would be trapped in a loop with no way
    // to comply with the very thing being demanded of them.
    $this->actingAs($student, 'student')
        ->get(route('student.settings.index', $this->school))
        ->assertOk();
});

test('the portal opens up again once the student sets their own password', function () {
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'password' => Hash::make('Temp-FromAdmin-123!'),
        'must_change_password' => true,
    ]);

    $this->actingAs($student, 'student')
        ->put(route('student.settings.update-password', $this->school), [
            'current_password' => 'Temp-FromAdmin-123!',
            'password' => 'Students-Own-456!',
            'password_confirmation' => 'Students-Own-456!',
        ])
        ->assertSessionDoesntHaveErrors();

    expect($student->refresh()->must_change_password)->toBeFalse();

    $this->actingAs($student, 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertOk();
});

// -----------------------------------------------------------------------------
// Every reset is recorded
// -----------------------------------------------------------------------------

test('resetting a password records who did it, to whom, and when', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->put(route('students.update-password', $student), [
            'password' => 'Temp-Password-123!',
        ]);

    $entry = AuditLog::where('action', 'password.reset')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($this->admin->id)          // which administrator
        ->and($entry->subject_type)->toBe($student->getMorphClass())
        ->and($entry->subject_id)->toBe($student->id)           // which account
        ->and($entry->created_at)->not->toBeNull();             // when

    // The new password must never appear anywhere in the log.
    expect($entry->description)->not->toContain('Temp-Password-123!');
});

// -----------------------------------------------------------------------------
// The login pages must say what to do instead
// -----------------------------------------------------------------------------

test('the student login page tells the user to contact their school admin', function () {
    $this->get(route('student.login', [$this->school, $this->school->portal_student_token]))
        ->assertOk()
        ->assertSee('contact your School Admin', false);
});

test('the staff login page tells the user to contact their school admin', function () {
    $this->get(route('staff.login', [$this->school, $this->school->portal_staff_token]))
        ->assertOk()
        ->assertSee('contact your School Admin', false);
});

test('the guardian login page tells the user to contact their school admin', function () {
    $this->get(route('guardian.login', [$this->school, $this->school->portal_guardian_token]))
        ->assertOk()
        ->assertSee('contact your School Admin', false);
});

// -----------------------------------------------------------------------------
// The second guardian reset endpoint, reached from a student's own page
// -----------------------------------------------------------------------------
//
// A guardian's password can be reset from two places: the guardians list, and
// from the page of a student they are linked to. Both are separate routes, so
// both need their own check - it would be easy to harden one and forget the
// other.

test('a school admin can reset a guardian password from a student page', function () {
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->put(route('students.guardians.update-password', $guardian), [
            'password' => 'Temp-Password-123!',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $guardian->refresh();

    expect(Hash::check('Temp-Password-123!', $guardian->password))->toBeTrue()
        ->and($guardian->must_change_password)->toBeTrue();
});

test('the student-page guardian reset also refuses another school', function () {
    $otherSchool = School::factory()->create();
    $victim = Guardian::factory()->create(['school_id' => $otherSchool->id]);
    $originalHash = $victim->password;

    $this->actingAs($this->admin)
        ->put(route('students.guardians.update-password', $victim), [
            'password' => 'Attacker-Pass-123!',
        ])
        ->assertForbidden();

    expect($victim->refresh()->password)->toBe($originalHash);
});

test('a guardian cannot reset their own password through the student-page endpoint', function () {
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $originalHash = $guardian->password;

    $this->actingAs($guardian, 'guardian')
        ->put(route('students.guardians.update-password', $guardian), [
            'password' => 'Guardian-Pass-123!',
        ])
        ->assertForbidden();

    expect($guardian->refresh()->password)->toBe($originalHash);
});
