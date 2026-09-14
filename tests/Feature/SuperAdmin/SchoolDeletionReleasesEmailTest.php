<?php

use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a school has to give its email back.
 *
 * A school deleted from the Super Admin panel and then registered again with
 * the same address was refused: "the school email address has already been
 * taken". The school row was gone, but the account that held the email was
 * not, and an email nobody can sign in with, attached to a school that no
 * longer exists, is not a record worth keeping. It is a lock on an empty room.
 */
function deletionSuperAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
}

function registerSchoolForDeletion(string $email, string $name = 'Greenfield College'): void
{
    test()->post(route('register'), [
        'school_name' => $name,
        'email' => $email,
        'phone' => '08012345678',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
        'terms' => '1',
    ]);
}

test('a school email can be registered again after the school is deleted', function () {
    // 1-2. A school exists, registered with this address.
    registerSchoolForDeletion('school@example.com');

    $school = School::where('name', 'Greenfield College')->firstOrFail();
    expect(User::where('email', 'school@example.com')->exists())->toBeTrue();

    auth()->logout();

    // 3-4. The Super Admin deletes it.
    $this->actingAs(deletionSuperAdmin())
        ->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => 'Greenfield College'])
        ->assertRedirect(route('super-admin.schools.index'));

    expect(School::find($school->id))->toBeNull()
        ->and(User::where('email', 'school@example.com')->exists())->toBeFalse();

    auth()->logout();

    // 5-9. The same address registers a brand new school.
    registerSchoolForDeletion('school@example.com', 'Greenfield College II');

    $this->assertDatabaseHas('users', ['email' => 'school@example.com']);

    $replacement = School::where('name', 'Greenfield College II')->first();

    expect($replacement)->not->toBeNull()
        ->and($replacement->id)->not->toBe($school->id);
});

test('the registration is refused while the school still exists', function () {
    registerSchoolForDeletion('school@example.com');
    auth()->logout();

    // The constraint is not being loosened, a live school still owns its
    // address, and this is the assertion that would catch it if the fix had
    // been to weaken the rule instead of to complete the deletion.
    registerSchoolForDeletion('school@example.com', 'An Impostor');

    expect(School::where('name', 'An Impostor')->exists())->toBeFalse();
    expect(User::where('email', 'school@example.com')->count())->toBe(1);
});

test('deleting a school releases every account that belonged to it', function () {
    registerSchoolForDeletion('admin@example.com');
    $school = School::where('name', 'Greenfield College')->firstOrFail();

    // A second admin, a teacher, a student and a guardian, every kind of
    // account that holds an address of its own.
    User::factory()->create(['school_id' => $school->id, 'role' => UserRole::SchoolAdmin, 'email' => 'second@example.com']);
    Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher, 'email' => 'teacher@example.com']);
    $student = Student::factory()->create(['school_id' => $school->id, 'email' => 'student@example.com']);
    Guardian::factory()->create(['school_id' => $school->id, 'email' => 'guardian@example.com']);

    auth()->logout();

    $this->actingAs(deletionSuperAdmin())
        ->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => $school->name]);

    expect(User::whereIn('email', ['admin@example.com', 'second@example.com'])->exists())->toBeFalse()
        ->and(Staff::where('email', 'teacher@example.com')->exists())->toBeFalse()
        ->and(Student::find($student->id))->toBeNull()
        ->and(Guardian::where('email', 'guardian@example.com')->exists())->toBeFalse();
});

test('deleting one school leaves another school\'s accounts alone', function () {
    registerSchoolForDeletion('first@example.com', 'First School');
    auth()->logout();

    registerSchoolForDeletion('second@example.com', 'Second School');
    auth()->logout();

    $first = School::where('name', 'First School')->firstOrFail();

    $this->actingAs(deletionSuperAdmin())
        ->delete(route('super-admin.schools.destroy', $first), ['confirm_name' => 'First School']);

    // "Remove the school's records" must not become "remove records".
    expect(User::where('email', 'second@example.com')->exists())->toBeTrue()
        ->and(School::where('name', 'Second School')->exists())->toBeTrue()
        ->and(User::where('email', 'first@example.com')->exists())->toBeFalse();
});

test('a school removed by any route takes its accounts with it', function () {
    registerSchoolForDeletion('stale@example.com');
    $school = School::where('name', 'Greenfield College')->firstOrFail();
    $admin = User::where('email', 'stale@example.com')->firstOrFail();

    auth()->logout();

    // A query-builder delete, which fires no model events and runs none of the
    // controller's tidying. This is the case that was broken: the constraint
    // was ON DELETE SET NULL, so the school vanished and its admin was cut
    // loose, still holding the address, unable to sign in, and blocking the
    // school from ever registering again. The rule belongs on the constraint,
    // where no code path can forget it.
    School::where('id', $school->id)->delete();

    expect(User::find($admin->id))->toBeNull();

    registerSchoolForDeletion('stale@example.com', 'Back Again');

    expect(School::where('name', 'Back Again')->exists())->toBeTrue();
});

test('a super admin is never caught by the cascade', function () {
    registerSchoolForDeletion('school@example.com');
    $school = School::where('name', 'Greenfield College')->firstOrFail();

    // Super Admins have no school by design, which is the same NULL school_id
    // the orphan cleanup looks for. They must be untouchable by both.
    $superAdmin = deletionSuperAdmin();

    auth()->logout();

    $this->actingAs(deletionSuperAdmin())
        ->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => $school->name]);

    expect(User::find($superAdmin->id))->not->toBeNull();
});

test('the sign-in residue of a deleted school goes with it', function () {
    registerSchoolForDeletion('school@example.com');
    $school = School::where('name', 'Greenfield College')->firstOrFail();
    $admin = User::where('email', 'school@example.com')->firstOrFail();

    // Neither of these has a foreign key, so no cascade reaches them: a
    // session row would keep a deleted account signed in, and a reset token
    // would be a live way back into an account that is gone, handed to
    // whoever registers that address next.
    DB::table('sessions')->insert([
        'id' => 'test-session-id',
        'user_id' => $admin->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'x',
        'last_activity' => now()->timestamp,
    ]);
    DB::table('password_reset_tokens')->insert([
        'email' => 'school@example.com',
        'token' => 'hashed-token',
        'created_at' => now(),
    ]);

    auth()->logout();

    $this->actingAs(deletionSuperAdmin())
        ->delete(route('super-admin.schools.destroy', $school), ['confirm_name' => $school->name]);

    expect(DB::table('sessions')->where('user_id', $admin->id)->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', 'school@example.com')->exists())->toBeFalse();
});
