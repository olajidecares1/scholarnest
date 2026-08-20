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

test('a school admin can link a new guardian to a student', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $response = $this->actingAs($this->admin)->post(route('students.guardians.store', $student), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '08012345678',
        'relationship' => 'Mother',
    ]);

    $response->assertRedirect();

    $guardian = Guardian::where('email', 'jane@example.com')->firstOrFail();
    expect($guardian->school_id)->toBe($this->school->id);
    expect($guardian->students()->where('students.id', $student->id)->exists())->toBeTrue();
    expect($guardian->students()->where('students.id', $student->id)->first()->pivot->relationship)->toBe('Mother');
});

test('linking a second child to the same guardian email attaches the existing guardian instead of duplicating', function () {
    $child1 = Student::factory()->create(['school_id' => $this->school->id]);
    $child2 = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('students.guardians.store', $child1), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
    $this->actingAs($this->admin)->post(route('students.guardians.store', $child2), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    expect(Guardian::where('email', 'jane@example.com')->count())->toBe(1);

    $guardian = Guardian::where('email', 'jane@example.com')->firstOrFail();
    expect($guardian->students()->count())->toBe(2);
});

test('a school admin can set and reset a guardian password', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $guardian->students()->attach($student->id);

    $this->actingAs($this->admin)
        ->put(route('students.guardians.update-password', $guardian), ['password' => 'NewPassword@123'])
        ->assertRedirect();

    $this->assertTrue(Hash::check('NewPassword@123', $guardian->fresh()->password));
});

test('a school admin can unlink a guardian from a student', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $guardian->students()->attach($student->id);

    $this->actingAs($this->admin)
        ->delete(route('students.guardians.destroy', [$student, $guardian]))
        ->assertRedirect();

    expect($guardian->students()->where('students.id', $student->id)->exists())->toBeFalse();
    expect(Guardian::find($guardian->id))->not->toBeNull();
});

test('a school admin cannot manage guardian password for another school', function () {
    $otherSchool = School::factory()->create();
    $guardian = Guardian::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->put(route('students.guardians.update-password', $guardian), ['password' => 'NewPassword@123'])
        ->assertForbidden();
});

test('a school admin cannot link a guardian to another school\'s student', function () {
    $otherSchool = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->post(route('students.guardians.store', $student), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ])
        ->assertForbidden();
});
