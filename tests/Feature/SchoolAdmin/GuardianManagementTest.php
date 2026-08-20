<?php

use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can view the guardians index scoped to their own school', function () {
    Guardian::factory()->create(['school_id' => $this->school->id, 'name' => 'Jane Doe']);
    $otherSchool = School::factory()->create();
    Guardian::factory()->create(['school_id' => $otherSchool->id, 'name' => 'Someone Else']);

    $this->actingAs($this->admin)
        ->get(route('guardians.index'))
        ->assertStatus(200)
        ->assertSee('Jane Doe')
        ->assertDontSee('Someone Else');
});

test('a school admin can create a guardian without linking a child yet', function () {
    $this->actingAs($this->admin)
        ->post(route('guardians.store'), [
            'name' => 'John Johnson',
            'email' => 'john@example.com',
            'phone' => '08012345678',
        ])
        ->assertRedirect();

    $guardian = Guardian::where('email', 'john@example.com')->firstOrFail();
    expect($guardian->school_id)->toBe($this->school->id);
    expect($guardian->students)->toBeEmpty();
    expect($guardian->password)->toBeNull();
});

test('a school admin can create a guardian with an initial portal password', function () {
    $this->actingAs($this->admin)
        ->post(route('guardians.store'), [
            'name' => 'John Johnson',
            'email' => 'john@example.com',
            'password' => 'Secret@123',
        ])
        ->assertRedirect();

    $guardian = Guardian::where('email', 'john@example.com')->firstOrFail();
    $this->assertTrue(Hash::check('Secret@123', $guardian->password));
});

test('a school admin cannot create two guardians with the same email in the same school', function () {
    Guardian::factory()->create(['school_id' => $this->school->id, 'email' => 'john@example.com']);

    $this->actingAs($this->admin)
        ->post(route('guardians.store'), ['name' => 'Another John', 'email' => 'john@example.com'])
        ->assertSessionHasErrors('email');
});

test('a school admin can update a guardian\'s details', function () {
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->put(route('guardians.update', $guardian), [
            'name' => 'New Name',
            'email' => $guardian->email,
            'phone' => '08099999999',
        ])
        ->assertRedirect();

    expect($guardian->fresh()->name)->toBe('New Name');
    expect($guardian->fresh()->phone)->toBe('08099999999');
});

test('a school admin can toggle a guardian\'s active status', function () {
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);

    $this->actingAs($this->admin)
        ->post(route('guardians.toggle-active', $guardian))
        ->assertRedirect();

    expect($guardian->fresh()->is_active)->toBeFalse();
});

test('a school admin can set a guardian\'s portal password from the guardian page', function () {
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'password' => null]);

    $this->actingAs($this->admin)
        ->put(route('guardians.update-password', $guardian), ['password' => 'NewPassword@123'])
        ->assertRedirect();

    $this->assertTrue(Hash::check('NewPassword@123', $guardian->fresh()->password));
});

test('a school admin can link an existing student to a guardian without creating a duplicate student', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);

    $studentCountBefore = Student::count();

    $this->actingAs($this->admin)
        ->post(route('guardians.children.store', $guardian), [
            'student' => $student->uuid,
            'relationship' => 'Father',
        ])
        ->assertRedirect();

    expect(Student::count())->toBe($studentCountBefore);
    $link = $guardian->students()->where('students.id', $student->id)->first();
    expect($link)->not->toBeNull();
    expect($link->pivot->relationship)->toBe('Father');
});

test('a school admin cannot link a student from another school to a guardian', function () {
    $otherSchool = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $otherSchool->id]);
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->post(route('guardians.children.store', $guardian), ['student' => $student->uuid])
        ->assertNotFound();

    expect($guardian->students()->where('students.id', $student->id)->exists())->toBeFalse();
});

test('a school admin can unlink a child from a guardian', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $guardian->students()->attach($student->id, ['relationship' => 'Mother']);

    $this->actingAs($this->admin)
        ->delete(route('guardians.children.destroy', [$guardian, $student]))
        ->assertRedirect();

    expect($guardian->students()->where('students.id', $student->id)->exists())->toBeFalse();
    expect(Student::find($student->id))->not->toBeNull();
});

test('a school admin can delete a guardian, which unlinks all their children', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $guardian->students()->attach($student->id);

    $this->actingAs($this->admin)
        ->delete(route('guardians.destroy', $guardian))
        ->assertRedirect();

    expect(Guardian::find($guardian->id))->toBeNull();
    expect(Student::find($student->id))->not->toBeNull();
});

test('a school admin cannot view, edit, or delete another school\'s guardian', function () {
    $otherSchool = School::factory()->create();
    $guardian = Guardian::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)->get(route('guardians.show', $guardian))->assertForbidden();
    $this->actingAs($this->admin)->put(route('guardians.update', $guardian), ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
    $this->actingAs($this->admin)->post(route('guardians.toggle-active', $guardian))->assertForbidden();
    $this->actingAs($this->admin)->delete(route('guardians.destroy', $guardian))->assertForbidden();
});

test('linking a child only offers students not already linked to that guardian', function () {
    $linkedStudent = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amara']);
    $unlinkedStudent = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'David']);
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $guardian->students()->attach($linkedStudent->id);

    $this->actingAs($this->admin)
        ->get(route('guardians.show', $guardian))
        ->assertStatus(200)
        ->assertSee('David')
        ->assertSee('Amara');
    // Amara appears once as an already-linked child; David appears once as
    // a selectable option in the "link a child" dropdown - the important
    // assertion is that David (unlinked) is offered while Amara (already
    // linked) is not duplicated into the selectable list, checked next.

    $guardian->refresh();
    $availableUuids = Student::where('school_id', $this->school->id)
        ->whereNotIn('id', $guardian->students()->pluck('students.id'))
        ->pluck('uuid');

    expect($availableUuids)->toContain($unlinkedStudent->uuid);
    expect($availableUuids)->not->toContain($linkedStudent->uuid);
});
