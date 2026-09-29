<?php

use App\Enums\Gender;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;

/**
 * IDs the system owns, and the handover that follows.
 *
 * Generated in the school's own sequence; a deleted staff member's ID is
 * issued to the next person added, and nobody else's ID ever changes; and
 * the details are handed over by sharing.
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
// A deleted Staff ID is issued again
// -----------------------------------------------------------------------------

test('deleting a staff member leaves everyone else\'s id alone', function () {
    [$school, $admin] = sequenceSchool();

    foreach (['One', 'Two', 'Three', 'Four'] as $name) {
        hireStaff($admin, $name);
    }

    $this->actingAs($admin)->delete(route('staff.destroy', Staff::where('staff_number', 'ZHTVVT-STAFF-001')->firstOrFail()))->assertRedirect();

    // Nobody is renamed: their Staff ID is what they sign in with.
    expect(Staff::where('school_id', $school->id)->orderBy('id')->pluck('staff_number')->all())
        ->toBe(['ZHTVVT-STAFF-002', 'ZHTVVT-STAFF-003', 'ZHTVVT-STAFF-004']);
});

test('the deleted staff id is given to the next staff member added', function (int $deleteIndex) {
    [$school, $admin] = sequenceSchool();

    foreach (['One', 'Two', 'Three', 'Four'] as $name) {
        hireStaff($admin, $name);
    }

    $this->actingAs($admin)->delete(route('staff.destroy', Staff::where('staff_number', sprintf('ZHTVVT-STAFF-%03d', $deleteIndex))->firstOrFail()));

    hireStaff($admin, 'New');

    expect(Staff::where('last_name', 'New')->firstOrFail()->staff_number)->toBe(sprintf('ZHTVVT-STAFF-%03d', $deleteIndex))
        ->and($school->fresh()->next_staff_sequence)->toBe(5);
})->with([
    'first' => 1,
    'second' => 2,
    'third' => 3,
    'last' => 4,
]);

test('several freed ids are reused lowest first, then the numbering carries on', function () {
    [$school, $admin] = sequenceSchool();

    foreach (['One', 'Two', 'Three', 'Four'] as $name) {
        hireStaff($admin, $name);
    }

    foreach (['ZHTVVT-STAFF-003', 'ZHTVVT-STAFF-001'] as $number) {
        $this->actingAs($admin)->delete(route('staff.destroy', Staff::where('staff_number', $number)->firstOrFail()));
    }

    hireStaff($admin, 'Five');
    hireStaff($admin, 'Six');
    hireStaff($admin, 'Seven');

    expect(Staff::whereIn('last_name', ['Five', 'Six', 'Seven'])->orderBy('id')->pluck('staff_number')->all())
        ->toBe(['ZHTVVT-STAFF-001', 'ZHTVVT-STAFF-003', 'ZHTVVT-STAFF-005']);
});

test('the school is told the freed id will be reused', function () {
    [$school, $admin] = sequenceSchool();
    hireStaff($admin, 'One');

    $this->actingAs($admin)
        ->delete(route('staff.destroy', Staff::where('staff_number', 'ZHTVVT-STAFF-001')->firstOrFail()))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'ZHTVVT-STAFF-001 is free'));
});

test('a school that numbers its own staff is left alone', function () {
    [$school, $admin] = sequenceSchool();

    // Not this system's format, so not part of its sequence.
    Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'LEGACY-A']);
    Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'LEGACY-B']);
    hireStaff($admin, 'Generated');

    $this->actingAs($admin)->delete(route('staff.destroy', Staff::where('staff_number', 'LEGACY-A')->firstOrFail()));
    hireStaff($admin, 'Next');

    expect(Staff::where('school_id', $school->id)->orderBy('id')->pluck('staff_number')->all())
        ->toBe(['LEGACY-B', 'ZHTVVT-STAFF-001', 'ZHTVVT-STAFF-002']);
});

test('one school\'s freed id is not given to another school', function () {
    [$schoolA, $adminA] = sequenceSchool();
    $schoolB = School::factory()->create(['school_code' => 'OTHER']);
    activateSchool($schoolB, PlanKey::Basic);
    $adminB = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $schoolB->id]);

    hireStaff($adminA, 'A One');
    hireStaff($adminB, 'B One');

    $this->actingAs($adminA)->delete(route('staff.destroy', Staff::where('staff_number', 'ZHTVVT-STAFF-001')->firstOrFail()));
    hireStaff($adminB, 'B Two');

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
