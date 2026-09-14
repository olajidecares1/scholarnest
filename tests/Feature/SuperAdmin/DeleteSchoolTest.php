<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;

/**
 * Deleting a school.
 *
 * `schools` is the parent of 43 cascading foreign keys, so this is one of the
 * widest destructive actions in the application, and the only one with no
 * undo. The tests below are mostly about the guardrails rather than the
 * deletion itself: that it cannot be triggered by the wrong person, cannot be
 * triggered by accident, and leaves a record behind of what was destroyed.
 */
function deletableSchool(string $name = 'Doomed Academy'): School
{
    $school = School::factory()->create(['name' => $name]);

    Student::factory()->count(3)->create(['school_id' => $school->id]);
    Staff::factory()->count(2)->create(['school_id' => $school->id]);

    return $school;
}

function deletingAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
}

// -----------------------------------------------------------------------------
// It works, and it takes everything with it
// -----------------------------------------------------------------------------

test('a super admin can delete a school by confirming its name', function () {
    $school = deletableSchool();

    $this->actingAs(deletingAdmin())
        ->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => 'Doomed Academy'])
        ->assertRedirect(route('super-admin.schools.index'));

    expect(School::find($school->id))->toBeNull();
});

test('the school takes its students and staff with it', function () {
    $school = deletableSchool();

    expect(Student::where('school_id', $school->id)->count())->toBe(3)
        ->and(Staff::where('school_id', $school->id)->count())->toBe(2);

    $this->actingAs(deletingAdmin())
        ->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => 'Doomed Academy']);

    // Cascaded at the database level rather than left orphaned.
    expect(Student::where('school_id', $school->id)->count())->toBe(0)
        ->and(Staff::where('school_id', $school->id)->count())->toBe(0);
});

test('what was destroyed is recorded before it goes', function () {
    $school = deletableSchool();

    $this->actingAs(deletingAdmin())
        ->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => 'Doomed Academy']);

    $entry = AuditLog::where('action', 'school.deleted')->latest('id')->first();

    // The audit entry is the only surviving evidence any of it existed, so it
    // has to carry the counts, afterwards there is nothing left to count.
    expect($entry)->not->toBeNull()
        ->and($entry->description)->toContain('Doomed Academy')
        ->and($entry->description)->toContain('3 student(s)')
        ->and($entry->description)->toContain('2 staff');
});

// -----------------------------------------------------------------------------
// The guardrails
// -----------------------------------------------------------------------------

test('a mistyped name deletes nothing', function () {
    $school = deletableSchool();

    $this->actingAs(deletingAdmin())
        ->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => 'Doomed Acadmy'])
        ->assertSessionHasErrors('confirm_name');

    expect(School::find($school->id))->not->toBeNull()
        ->and(Student::where('school_id', $school->id)->count())->toBe(3);
});

test('an omitted confirmation deletes nothing', function () {
    $school = deletableSchool();

    $this->actingAs(deletingAdmin())
        ->delete(route('super-admin.schools.destroy', $school), [])
        ->assertSessionHasErrors('confirm_name');

    expect(School::find($school->id))->not->toBeNull();
});

test('another school name is not accepted as confirmation', function () {
    $target = deletableSchool('Doomed Academy');
    $bystander = deletableSchool('Innocent College');

    // Confirming with a name that is real, but belongs to a different row,
    // the mistake a list of near-identical Delete buttons invites.
    $this->actingAs(deletingAdmin())
        ->delete(route('super-admin.schools.destroy', $target), ['confirm_name' => 'Innocent College'])
        ->assertSessionHasErrors('confirm_name');

    expect(School::find($target->id))->not->toBeNull()
        ->and(School::find($bystander->id))->not->toBeNull();
});

test('a school admin cannot delete a school', function () {
    $school = deletableSchool();

    $schoolAdmin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $school->id,
    ]);

    // Not even their own.
    $this->actingAs($schoolAdmin)
        ->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => 'Doomed Academy'])
        ->assertForbidden();

    expect(School::find($school->id))->not->toBeNull();
});

test('a guest cannot delete a school', function () {
    $school = deletableSchool();

    $this->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => 'Doomed Academy'])
        ->assertRedirect();

    expect(School::find($school->id))->not->toBeNull();
});

test('deleting one school leaves the others alone', function () {
    $target = deletableSchool('Doomed Academy');
    $bystander = deletableSchool('Innocent College');

    $this->actingAs(deletingAdmin())
        ->delete(route('super-admin.schools.destroy', $target), ['confirm_name' => 'Doomed Academy']);

    expect(School::find($target->id))->toBeNull()
        ->and(School::find($bystander->id))->not->toBeNull()
        ->and(Student::where('school_id', $bystander->id)->count())->toBe(3);
});

test('the delete control appears on the schools list', function () {
    deletableSchool();

    $this->actingAs(deletingAdmin())
        ->get(route('super-admin.schools.index'))
        ->assertOk()
        ->assertSee('Delete')
        // The reversible option must still be the prominent one.
        ->assertSee('Suspend')
        ->assertSee('This cannot be undone.');
});

// -----------------------------------------------------------------------------
// The school's own admins go with it
// -----------------------------------------------------------------------------

test('deleting a school removes its admin accounts', function () {
    $school = deletableSchool();

    $schoolAdmin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $school->id,
    ]);

    $this->actingAs(deletingAdmin())
        ->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => 'Doomed Academy']);

    // users.school_id is SET NULL rather than CASCADE, so without deleting
    // them explicitly the admins survive pointing at nothing, still able to
    // sign in, and fatal on whatever they open.
    expect(User::find($schoolAdmin->id))->toBeNull();
});

test('the audit entry counts the admin accounts removed', function () {
    $school = deletableSchool();
    User::factory()->count(2)->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs(deletingAdmin())
        ->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => 'Doomed Academy']);

    expect(AuditLog::where('action', 'school.deleted')->latest('id')->first()->description)
        ->toContain('2 admin account(s)');
});

test('deleting a school leaves other schools admins alone', function () {
    $target = deletableSchool('Doomed Academy');
    $bystander = deletableSchool('Innocent College');

    $keep = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $bystander->id]);

    $this->actingAs(deletingAdmin())
        ->delete(route('super-admin.schools.destroy', $target), ['confirm_name' => 'Doomed Academy']);

    expect(User::find($keep->id))->not->toBeNull()
        ->and(User::find($keep->id)->school_id)->toBe($bystander->id);
});

test('an orphaned school admin is signed out rather than crashing', function () {
    // The state that already exists for accounts whose school was deleted
    // before the delete action cleaned them up.
    $orphan = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => null,
    ]);

    $this->actingAs($orphan)
        ->get(route('dashboard'))
        ->assertRedirect(route('portal.show'));

    $this->assertGuest();
});

test('an orphaned school admin cannot reach school pages either', function () {
    $orphan = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => null,
    ]);

    // Every school-side route sits behind the school_admin middleware, which
    // makes the same check, so no page is left that still fatals.
    $this->actingAs($orphan)
        ->get(route('students.index'))
        ->assertRedirect(route('portal.show'));
});

test('a school admin with a school is unaffected by the orphan check', function () {
    $school = deletableSchool();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    $this->assertAuthenticatedAs($admin);
});
