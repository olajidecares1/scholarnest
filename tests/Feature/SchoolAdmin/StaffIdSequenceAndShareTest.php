<?php

use App\Enums\Gender;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;

/**
 * IDs the system owns, and the handover that follows.
 *
 * Three rules here, and the middle one is worth naming plainly: closing the
 * gap in the staff numbering RENAMES people's login identifiers. Anyone whose
 * ID moves can no longer sign in with the one they were given, and has to be
 * told the new one. That is what the sharing is for.
 */
function sequenceSchool(PlanKey $planKey = PlanKey::Basic): array
{
    $school = School::factory()->create(['school_code' => 'ZHTVVT']);
    activateSchool($school, $planKey);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return [$school->fresh(), $admin];
}

function hireStaff(User $admin, string $lastName): void
{
    test()->actingAs($admin)->post(route('staff.store'), [
        'first_name' => 'Test',
        'last_name' => $lastName,
        'gender' => Gender::Female->value,
        'role' => StaffRole::Teacher->value,
    ])->assertSessionHasNoErrors();
}

// -----------------------------------------------------------------------------
// Generated, and not the School Admin's to set
// -----------------------------------------------------------------------------

test('staff ids are generated in the school\'s own sequence', function () {
    [$school, $admin] = sequenceSchool();

    foreach (['One', 'Two', 'Three'] as $name) {
        hireStaff($admin, $name);
    }

    expect(Staff::where('school_id', $school->id)->orderBy('id')->pluck('staff_number')->all())
        ->toBe(['ZHTVVT-STAFF-001', 'ZHTVVT-STAFF-002', 'ZHTVVT-STAFF-003']);
});

test('a posted staff id is ignored on create and on edit', function () {
    [$school, $admin] = sequenceSchool();
    hireStaff($admin, 'One');

    $member = Staff::where('school_id', $school->id)->firstOrFail();

    $this->actingAs($admin)->put(route('staff.update', $member), [
        'staff_number' => 'MINE-999',
        'first_name' => $member->first_name,
        'last_name' => $member->last_name,
        'gender' => $member->gender->value,
        'role' => $member->role->value,
    ])->assertSessionHasNoErrors();

    // Not refused, simply not consulted. A field the interface refuses to
    // show but the controller would still honour is not read-only.
    expect($member->fresh()->staff_number)->toBe('ZHTVVT-STAFF-001');
});

test('a parent gets a generated id of their own', function () {
    [$school, $admin] = sequenceSchool(PlanKey::Standard);

    foreach (['first@example.test', 'second@example.test'] as $email) {
        $this->actingAs($admin)->post(route('guardians.index'), [
            'name' => 'A Parent',
            'email' => $email,
        ])->assertSessionHasNoErrors();
    }

    // A phone number is not the school's to control; a Parent ID is.
    expect(Guardian::where('school_id', $school->id)->orderBy('id')->pluck('guardian_number')->all())
        ->toBe(['ZHTVVT-PARENT-001', 'ZHTVVT-PARENT-002']);
});

// -----------------------------------------------------------------------------
// Closing the gap after a deletion
// -----------------------------------------------------------------------------

test('deleting a staff member closes the gap in the numbering', function () {
    [$school, $admin] = sequenceSchool();

    foreach (['One', 'Two', 'Three', 'Four'] as $name) {
        hireStaff($admin, $name);
    }

    $first = Staff::where('staff_number', 'ZHTVVT-STAFF-001')->firstOrFail();

    $this->actingAs($admin)->delete(route('staff.destroy', $first))->assertRedirect();

    // 002 becomes 001, 003 becomes 002, 004 becomes 003.
    expect(Staff::where('school_id', $school->id)->orderBy('id')->pluck('staff_number')->all())
        ->toBe(['ZHTVVT-STAFF-001', 'ZHTVVT-STAFF-002', 'ZHTVVT-STAFF-003']);
});

test('the gap closes whichever number is deleted', function (int $deleteIndex) {
    [$school, $admin] = sequenceSchool();

    foreach (['One', 'Two', 'Three', 'Four'] as $name) {
        hireStaff($admin, $name);
    }

    $target = Staff::where('staff_number', sprintf('ZHTVVT-STAFF-%03d', $deleteIndex))->firstOrFail();

    $this->actingAs($admin)->delete(route('staff.destroy', $target));

    expect(Staff::where('school_id', $school->id)->orderBy('id')->pluck('staff_number')->all())
        ->toBe(['ZHTVVT-STAFF-001', 'ZHTVVT-STAFF-002', 'ZHTVVT-STAFF-003']);
})->with([
    'first' => 1,
    'second' => 2,
    'third' => 3,
    'last' => 4,
]);

test('the next hire takes the number after the last one in use', function () {
    [$school, $admin] = sequenceSchool();

    foreach (['One', 'Two', 'Three'] as $name) {
        hireStaff($admin, $name);
    }

    $this->actingAs($admin)->delete(route('staff.destroy', Staff::where('staff_number', 'ZHTVVT-STAFF-002')->firstOrFail()));

    hireStaff($admin, 'Four');

    // The sequence stays closed rather than resuming past the gap.
    expect(Staff::where('school_id', $school->id)->orderBy('id')->pluck('staff_number')->all())
        ->toBe(['ZHTVVT-STAFF-001', 'ZHTVVT-STAFF-002', 'ZHTVVT-STAFF-003']);
});

test('the school is told that ids moved, and it is recorded', function () {
    [$school, $admin] = sequenceSchool();

    foreach (['One', 'Two', 'Three'] as $name) {
        hireStaff($admin, $name);
    }

    $this->actingAs($admin)
        ->delete(route('staff.destroy', Staff::where('staff_number', 'ZHTVVT-STAFF-001')->firstOrFail()))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'moved up to close the gap'));

    // Renaming somebody's login identifier is not something to do quietly:
    // they can no longer sign in with the ID they were given.
    $this->assertDatabaseHas('audit_logs', ['action' => 'staff.ids.resequenced']);

    expect(AuditLog::where('action', 'staff.ids.resequenced')->first()->description)
        ->toContain('ZHTVVT-STAFF-002 → ZHTVVT-STAFF-001');
});

test('deleting the last staff member says nothing about renumbering', function () {
    [$school, $admin] = sequenceSchool();

    foreach (['One', 'Two'] as $name) {
        hireStaff($admin, $name);
    }

    // Nothing moved, so there is nothing to report.
    $this->actingAs($admin)
        ->delete(route('staff.destroy', Staff::where('staff_number', 'ZHTVVT-STAFF-002')->firstOrFail()))
        ->assertSessionHas('status', fn (string $status) => ! str_contains($status, 'moved up'));

    expect(Staff::where('school_id', $school->id)->pluck('staff_number')->all())->toBe(['ZHTVVT-STAFF-001']);
});

test('a school that numbers its own staff is left alone', function () {
    [$school, $admin] = sequenceSchool();

    // Not this system's format, so not this system's to renumber. A school
    // with its own scheme has a reason for it.
    Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'LEGACY-A']);
    Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'LEGACY-B']);
    hireStaff($admin, 'Generated');

    $generated = Staff::where('staff_number', 'like', 'ZHTVVT-STAFF-%')->firstOrFail();
    $this->actingAs($admin)->delete(route('staff.destroy', $generated));

    expect(Staff::where('school_id', $school->id)->orderBy('id')->pluck('staff_number')->all())
        ->toBe(['LEGACY-A', 'LEGACY-B']);
});

test('one school\'s deletion does not renumber another\'s', function () {
    [$schoolA, $adminA] = sequenceSchool();
    $schoolB = School::factory()->create(['school_code' => 'OTHER']);
    activateSchool($schoolB, PlanKey::Basic);
    $adminB = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $schoolB->id]);

    hireStaff($adminA, 'A One');
    hireStaff($adminA, 'A Two');
    hireStaff($adminB, 'B One');
    hireStaff($adminB, 'B Two');

    $this->actingAs($adminA)->delete(route('staff.destroy', Staff::where('staff_number', 'ZHTVVT-STAFF-001')->firstOrFail()));

    expect(Staff::where('school_id', $schoolB->id)->orderBy('id')->pluck('staff_number')->all())
        ->toBe(['OTHER-STAFF-001', 'OTHER-STAFF-002']);
});

// -----------------------------------------------------------------------------
// Handing the details over
// -----------------------------------------------------------------------------

test('saving login details offers a whatsapp share', function () {
    [$school, $admin] = sequenceSchool();
    hireStaff($admin, 'One');
    $member = Staff::where('school_id', $school->id)->firstOrFail();
    $member->update(['phone' => '08012345678']);

    $response = $this->actingAs($admin)
        ->put(route('staff.credentials', $member), [
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])
        ->assertSessionHas('credential_share');

    $share = session('credential_share');

    // Everything the brief asks the message to carry.
    expect($share['message'])
        ->toContain($school->name)
        ->toContain('Test One')
        ->toContain('ZHTVVT-STAFF-001')
        ->toContain('Str0ng-Passw0rd!')
        ->toContain($school->portalLoginUrl('staff'))
        ->and($share['url'])->toStartWith('https://wa.me/')

        // A local number is sent in international form, or wa.me opens the
        // wrong chat.
        ->and($share['phone'])->toBe('2348012345678');

    $this->actingAs($admin)
        ->get(route('staff.show', $member))
        ->assertOk();
});

test('the share stays available, but stops carrying the password', function () {
    [$school, $admin] = sequenceSchool();
    hireStaff($admin, 'One');
    $member = Staff::where('school_id', $school->id)->firstOrFail();

    $this->actingAs($admin)->put(route('staff.credentials', $member), [
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ]);

    // Spend the one-time banner.
    $this->actingAs($admin)->get(route('staff.show', $member));

    $response = $this->actingAs($admin)->get(route('staff.show', $member))->assertOk();

    // The button is permanent, it lives in the Login Details card, where
    // credentials are managed. Only the password is transient: it exists in
    // readable form for one page view, and a share link that kept carrying it
    // would be a password sitting in the session.
    $response->assertSee('Share via WhatsApp')
        ->assertDontSee('Str0ng-Passw0rd!');

    expect($response->viewData('credentialShare')['message'])
        ->toContain('ZHTVVT-STAFF-001')
        ->toContain($school->name)
        ->toContain('issued separately by the school office')
        ->not->toContain('Str0ng-Passw0rd!');
});

test('the login details card offers a working whatsapp link before any password is set', function () {
    [$school, $admin] = sequenceSchool();
    hireStaff($admin, 'One');
    $member = Staff::where('school_id', $school->id)->firstOrFail();

    // The icon must never be a decoration that does nothing, this is the
    // state the School Admin sees most often, on a record they have not yet
    // issued credentials for.
    $response = $this->actingAs($admin)->get(route('staff.show', $member))->assertOk();

    $response->assertSee('Share via WhatsApp')
        ->assertSee('https://wa.me/', false);

    expect($response->viewData('credentialShare')['url'])->toStartWith('https://wa.me/');
});

test('students and parents get the same permanent share button', function () {
    [$school, $admin] = sequenceSchool(PlanKey::Standard);

    $student = Student::factory()->create(['school_id' => $school->id, 'admission_number' => 'ADM-77']);
    $guardian = Guardian::factory()->create(['school_id' => $school->id, 'guardian_number' => 'ZHTVVT-PARENT-001']);

    foreach ([['students.show', $student, 'ADM-77'], ['guardians.show', $guardian, 'ZHTVVT-PARENT-001']] as [$route, $account, $identifier]) {
        $response = $this->actingAs($admin)->get(route($route, $account))->assertOk();

        $response->assertSee('Share via WhatsApp');

        expect($response->viewData('credentialShare')['message'])->toContain($identifier);
    }
});

test('a share is offered for students and parents too', function () {
    [$school, $admin] = sequenceSchool(PlanKey::Standard);

    $student = Student::factory()->create(['school_id' => $school->id, 'admission_number' => 'ADM-001']);
    $guardian = Guardian::factory()->create(['school_id' => $school->id, 'guardian_number' => 'ZHTVVT-PARENT-001']);

    foreach ([
        ['students.credentials', $student, 'ADM-001'],
        ['guardians.credentials', $guardian, 'ZHTVVT-PARENT-001'],
    ] as [$route, $account, $identifier]) {
        $this->actingAs($admin)->put(route($route, $account), [
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertSessionHas('credential_share');

        expect(session('credential_share')['message'])->toContain($identifier);
    }
});

test('a share still works when no phone number is on file', function () {
    [$school, $admin] = sequenceSchool();
    hireStaff($admin, 'One');
    $member = Staff::where('school_id', $school->id)->firstOrFail();

    $this->actingAs($admin)->put(route('staff.credentials', $member), [
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ]);

    // wa.me with no number opens the contact picker, which is better than
    // hiding the button: the School Admin can still choose the right chat.
    expect(session('credential_share')['phone'])->toBeNull()
        ->and(session('credential_share')['url'])->toStartWith('https://wa.me/?text=');
});
