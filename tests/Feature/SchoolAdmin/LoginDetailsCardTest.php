<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The School Admin issuing someone their login details.
 *
 * Two fields, the username they sign in with, and the password, on each of
 * the three account types. Which column the username is depends on the
 * account: Staff ID for a teacher, Admission Number for a student, phone
 * number for a parent.
 */
function credentialsSchool(PlanKey $planKey = PlanKey::Standard): array
{
    $school = School::factory()->create();
    activateSchool($school, $planKey);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return [$school->fresh(), $admin];
}

// -----------------------------------------------------------------------------
// Teachers and staff, every plan
// -----------------------------------------------------------------------------

test('the staff page offers a login details card on every plan', function (PlanKey $planKey) {
    [$school, $admin] = credentialsSchool($planKey);
    $member = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher]);

    // Teachers sign in on every plan, they do the school's own work, so this
    // card is not plan-gated.
    $this->actingAs($admin)
        ->get(route('staff.show', $member))
        ->assertOk()
        ->assertSee('Login Details')
        ->assertSee('Staff ID')
        ->assertSee('Password');
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);

test('the school admin sets the password, and the id is left alone', function () {
    [$school, $admin] = credentialsSchool(PlanKey::Basic);
    $member = Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'STF-OLD']);

    $this->actingAs($admin)
        ->put(route('staff.credentials', $member), [
            // Sent anyway, as a browser or a script could. The ID is the
            // system's; a request that could change it would make "cannot be
            // edited" a statement about the interface rather than the rule.
            'username' => 'STF-HACKED',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $member->refresh();

    expect($member->staff_number)->toBe('STF-OLD')
        ->and(Hash::check('Str0ng-Passw0rd!', $member->password))->toBeTrue()

        // The flag exists to force a first-login password change, and users
        // are no longer permitted to change their own, leaving it set would
        // lock the account out with nowhere to go.
        ->and($member->must_change_password)->toBeFalse();
});

test('the password is never stored in readable form', function () {
    [$school, $admin] = credentialsSchool();
    $member = Staff::factory()->create(['school_id' => $school->id]);

    $this->actingAs($admin)->put(route('staff.credentials', $member), [
        'username' => 'STF-020',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ]);

    expect($member->fresh()->password)->not->toBe('Str0ng-Passw0rd!')
        ->and($member->fresh()->password)->not->toContain('Str0ng')
        ->and(Hash::check('Str0ng-Passw0rd!', $member->fresh()->password))->toBeTrue();
});

test('the two passwords have to match', function () {
    [$school, $admin] = credentialsSchool();
    $member = Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'STF-BEFORE']);
    $before = $member->password;

    $this->actingAs($admin)
        ->put(route('staff.credentials', $member), [
            'username' => 'STF-021',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Different-Passw0rd!',
        ])
        ->assertSessionHasErrors('password');

    // Nothing moved, not the password, and not the username either, which a
    // partial save would have changed on its way to failing.
    expect($member->fresh()->password)->toBe($before)
        ->and($member->fresh()->staff_number)->toBe('STF-BEFORE');
});

// -----------------------------------------------------------------------------
// Students and parents, Standard and Exclusive only
// -----------------------------------------------------------------------------

test('a student\'s login id is their admission number, shown but not editable', function () {
    [$school, $admin] = credentialsSchool();
    $student = Student::factory()->create(['school_id' => $school->id, 'admission_number' => 'ADM-2026-001']);

    $this->actingAs($admin)
        ->get(route('students.show', $student))
        ->assertOk()
        ->assertSee('Login Details')
        ->assertSee('Admission Number')
        ->assertSee('ADM-2026-001')
        ->assertSee('cannot be edited');

    $this->actingAs($admin)->put(route('students.credentials', $student), [
        'username' => 'ADM-SOMETHING-ELSE',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ])->assertSessionHasNoErrors();

    expect($student->fresh()->admission_number)->toBe('ADM-2026-001')
        ->and(Hash::check('Str0ng-Passw0rd!', $student->fresh()->password))->toBeTrue();
});

test('a guardian\'s login id is a generated parent id', function () {
    [$school, $admin] = credentialsSchool();
    $guardian = Guardian::factory()->create([
        'school_id' => $school->id,
        'guardian_number' => $school->school_code.'-PARENT-001',
        'phone' => '08000000000',
    ]);

    // A phone number is not the school's to control, it changes with the
    // handset, two parents may share one, and it cannot be issued at the
    // moment the account is created. So parents carry an ID like everybody
    // else.
    $this->actingAs($admin)
        ->get(route('guardians.show', $guardian))
        ->assertOk()
        ->assertSee('Login Details')
        ->assertSee('Parent ID')
        ->assertSee($school->school_code.'-PARENT-001');

    $this->actingAs($admin)->put(route('guardians.credentials', $guardian), [
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ])->assertSessionHasNoErrors();

    expect($guardian->fresh()->guardian_number)->toBe($school->school_code.'-PARENT-001')
        ->and(Hash::check('Str0ng-Passw0rd!', $guardian->fresh()->password))->toBeTrue();
});

test('a basic school is told why there are no student login details', function () {
    [$school, $admin] = credentialsSchool(PlanKey::Basic);
    $student = Student::factory()->create(['school_id' => $school->id]);

    // Credentials for a portal the plan does not have would be an account that
    // exists and cannot be used. The page says what to do instead rather than
    // simply omitting the card.
    $this->actingAs($admin)
        ->get(route('students.show', $student))
        ->assertOk()
        ->assertSee('no student portal')
        ->assertSee('exam token')
        ->assertDontSee('Admission Number', false);
});

// -----------------------------------------------------------------------------
// The identifiers stay unique, per school
// -----------------------------------------------------------------------------

test('one person\'s id cannot be taken by another through the credentials route', function () {
    [$school, $admin] = credentialsSchool();
    Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'STF-001']);
    $member = Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'STF-002']);

    // Not refused with a validation error, simply ignored. The route sets a
    // password and nothing else, so there is no id collision to have.
    $this->actingAs($admin)
        ->put(route('staff.credentials', $member), [
            'username' => 'STF-001',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->staff_number)->toBe('STF-002')
        ->and(Staff::where('school_id', $school->id)->where('staff_number', 'STF-001')->count())->toBe(1);
});

test('two schools number their staff independently', function () {
    [$schoolA, $adminA] = credentialsSchool();
    [$schoolB, $adminB] = credentialsSchool();

    // Uniqueness is per school, because the sign-in is resolved per school.
    // Each school's numbering starts at 001 and neither can block the other.
    foreach ([[$schoolA, $adminA], [$schoolB, $adminB]] as [$school, $admin]) {
        $this->actingAs($admin)->post(route('staff.store'), [
            'first_name' => 'First',
            'last_name' => 'Hire',
            'gender' => 'female',
            'role' => StaffRole::Teacher->value,
        ])->assertSessionHasNoErrors();
    }

    expect(Staff::where('school_id', $schoolA->id)->value('staff_number'))->toBe($schoolA->school_code.'-STAFF-001')
        ->and(Staff::where('school_id', $schoolB->id)->value('staff_number'))->toBe($schoolB->school_code.'-STAFF-001');
});

test('keeping the same login id while resetting the password is allowed', function () {
    [$school, $admin] = credentialsSchool();
    $member = Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'STF-007']);

    // The uniqueness rule has to ignore the account being edited, or resetting
    // a password without renaming the account would fail against itself.
    $this->actingAs($admin)
        ->put(route('staff.credentials', $member), [
            'username' => 'STF-007',
            'password' => 'An0ther-Passw0rd!',
            'password_confirmation' => 'An0ther-Passw0rd!',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('An0ther-Passw0rd!', $member->fresh()->password))->toBeTrue();
});

// -----------------------------------------------------------------------------
// And only for the school's own people
// -----------------------------------------------------------------------------

test('a school admin cannot issue credentials to another school\'s accounts', function () {
    [, $admin] = credentialsSchool();
    [$otherSchool] = credentialsSchool();

    $theirStaff = Staff::factory()->create(['school_id' => $otherSchool->id]);
    $theirStudent = Student::factory()->create(['school_id' => $otherSchool->id]);
    $theirGuardian = Guardian::factory()->create(['school_id' => $otherSchool->id]);

    $payload = [
        'username' => 'TAKEN-001',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ];

    $before = [$theirStaff->password, $theirStudent->password, $theirGuardian->password];

    $this->actingAs($admin)->put(route('staff.credentials', $theirStaff), $payload)->assertForbidden();
    $this->actingAs($admin)->put(route('students.credentials', $theirStudent), $payload)->assertForbidden();
    $this->actingAs($admin)->put(route('guardians.credentials', $theirGuardian), $payload)->assertForbidden();

    // Untouched, including the login ids, a refusal that still renamed the
    // account would be worse than one that did nothing.
    expect([$theirStaff->fresh()->password, $theirStudent->fresh()->password, $theirGuardian->fresh()->password])->toBe($before)
        ->and($theirStaff->fresh()->staff_number)->not->toBe('TAKEN-001');
});

test('issuing credentials is recorded', function () {
    [$school, $admin] = credentialsSchool();
    $member = Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'STF-099']);

    $this->actingAs($admin)->put(route('staff.credentials', $member), [
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ]);

    // The password is never in the log; the fact that credentials were issued,
    // and to whom, is.
    $this->assertDatabaseHas('audit_logs', ['action' => 'portal.credentials.issued']);

    expect(AuditLog::latest('id')->first()->description)
        ->toContain('STF-099')
        ->not->toContain('Str0ng-Passw0rd!');
});

// -----------------------------------------------------------------------------
// The same card, inside the Add/Edit form
// -----------------------------------------------------------------------------

test('the add and edit form carries the login details card', function () {
    [$school, $admin] = credentialsSchool(PlanKey::Basic);

    // At the foot of the form, above Save, because creating the account and
    // creating its login are one act for a School Admin.
    $this->actingAs($admin)
        ->get(route('staff.index'))
        ->assertOk()
        ->assertSee('Login Details')
        ->assertSee('Username (Staff ID)')
        ->assertSee('cannot be edited')
        ->assertSee('login_password', false)

        // No input for it. A read-only-looking field that still posted a
        // value would not be read-only.
        ->assertDontSee('name="login_username"', false);
});

test('login details can be set while creating a staff member', function () {
    [$school, $admin] = credentialsSchool(PlanKey::Basic);

    $this->actingAs($admin)->post(route('staff.store'), [
        // Sent, and ignored: the ID comes from the generator.
        'staff_number' => 'WHATEVER',
        'first_name' => 'Ada',
        'last_name' => 'Nwosu',
        'gender' => 'female',
        'role' => StaffRole::Teacher->value,
        'login_password' => 'Str0ng-Passw0rd!',
    ])->assertSessionHasNoErrors();

    $member = Staff::where('school_id', $school->id)->firstOrFail();

    expect($member->staff_number)->toBe($school->school_code.'-STAFF-001')
        ->and(Hash::check('Str0ng-Passw0rd!', $member->password))->toBeTrue();
});

test('a staff member can be recorded without any login details', function () {
    [$school, $admin] = credentialsSchool(PlanKey::Basic);

    // A school records a teacher before it decides to give them portal
    // access. A form that refused to save one without a password would make
    // the two inseparable.
    $this->actingAs($admin)->post(route('staff.store'), [
        'first_name' => 'Bola',
        'last_name' => 'Ade',
        'gender' => 'male',
        'role' => StaffRole::Teacher->value,
    ])->assertSessionHasNoErrors();

    expect(Staff::where('school_id', $school->id)->firstOrFail()->password)->toBeNull();
});

test('editing a record with a blank password leaves the existing one alone', function () {
    [$school, $admin] = credentialsSchool(PlanKey::Basic);
    $member = Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'STF-102']);

    $this->actingAs($admin)->put(route('staff.credentials', $member), [
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ])->assertSessionHasNoErrors();

    // Now change something unrelated, with the Login Details fields empty.
    $this->actingAs($admin)->put(route('staff.update', $member), [
        'staff_number' => 'STF-102',
        'first_name' => $member->first_name,
        'last_name' => $member->last_name,
        'gender' => $member->gender->value,
        'role' => $member->role->value,
        'phone' => '08099999999',
        'login_password' => '',
    ])->assertSessionHasNoErrors();

    // Editing a phone number must not silently revoke somebody's login.
    expect(Hash::check('Str0ng-Passw0rd!', $member->fresh()->password))->toBeTrue()
        ->and($member->fresh()->phone)->toBe('08099999999');
});

test('a basic school gets no student login fields in the form', function () {
    [$school, $admin] = credentialsSchool(PlanKey::Basic);

    $this->actingAs($admin)
        ->get(route('students.index'))
        ->assertOk()
        ->assertDontSee('login_password', false);
});

test('a standard school does get them', function () {
    [$school, $admin] = credentialsSchool(PlanKey::Standard);

    $this->actingAs($admin)
        ->get(route('students.index'))
        ->assertOk()
        ->assertSee('Username (Admission Number)')
        ->assertSee('login_password', false);
});

test('a guardian can be created with login details in one go', function () {
    [$school, $admin] = credentialsSchool(PlanKey::Standard);

    $this->actingAs($admin)->post(route('guardians.index'), [
        'name' => 'Ngozi Adeyemi',
        'email' => 'ngozi@example.test',
        'phone' => '08011112222',
        'login_password' => 'Str0ng-Passw0rd!',
    ])->assertSessionHasNoErrors();

    $guardian = Guardian::where('email', 'ngozi@example.test')->firstOrFail();

    // The Parent ID is generated on creation; the phone is just a phone.
    expect($guardian->guardian_number)->toBe($school->school_code.'-PARENT-001')
        ->and($guardian->phone)->toBe('08011112222')
        ->and(Hash::check('Str0ng-Passw0rd!', $guardian->password))->toBeTrue();
});
