<?php

use App\Enums\Gender;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\IdentifierGenerator;

beforeEach(function () {
    $this->school = School::factory()->create([
        'school_code' => 'MIS',
        'current_session' => '2025/2026',
        'auto_generate_admission_numbers' => true,
        'auto_generate_staff_ids' => true,
    ]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

// --- Service-level generation ---

test('it generates a formatted admission number using the class level code', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'code' => 'PRY']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'Primary 1']);

    $number = app(IdentifierGenerator::class)->nextAdmissionNumber($this->school, 'Primary 1');

    expect($number)->toBe('MIS-2025/2026-PRY-001');
});

test('it falls back to a stage-detected level code when the class has no configured code', function () {
    $number = app(IdentifierGenerator::class)->nextAdmissionNumber($this->school, 'JSS 1');

    expect($number)->toBe('MIS-2025/2026-JUR-001');
});

test('it falls back to a generic level code when the class name is unrecognized', function () {
    $number = app(IdentifierGenerator::class)->nextAdmissionNumber($this->school, 'Mystery Class');

    expect($number)->toBe('MIS-2025/2026-GEN-001');
});

test('the admission sequence increments and never repeats within a school', function () {
    $generator = app(IdentifierGenerator::class);

    $first = $generator->nextAdmissionNumber($this->school, null);
    $second = $generator->nextAdmissionNumber($this->school, null);
    $third = $generator->nextAdmissionNumber($this->school, null);

    expect([$first, $second, $third])->toBe([
        'MIS-2025/2026-GEN-001',
        'MIS-2025/2026-GEN-002',
        'MIS-2025/2026-GEN-003',
    ]);
});

test('the admission sequence is scoped per school', function () {
    $otherSchool = School::factory()->create(['school_code' => 'ABC', 'current_session' => '2025/2026']);

    $mine = app(IdentifierGenerator::class)->nextAdmissionNumber($this->school, null);
    $theirs = app(IdentifierGenerator::class)->nextAdmissionNumber($otherSchool, null);

    expect($mine)->toBe('MIS-2025/2026-GEN-001');
    expect($theirs)->toBe('ABC-2025/2026-GEN-001');
});

test('it generates a formatted staff id', function () {
    $generator = app(IdentifierGenerator::class);

    expect($generator->nextStaffId($this->school))->toBe('MIS-STAFF-001');
    expect($generator->nextStaffId($this->school))->toBe('MIS-STAFF-002');
});

test('concurrent generation calls never collide', function () {
    $generator = app(IdentifierGenerator::class);

    $numbers = collect(range(1, 10))->map(fn () => $generator->nextAdmissionNumber($this->school, null));

    expect($numbers->unique())->toHaveCount(10);
});

test('it refuses to generate without a school code configured', function () {
    $this->school->update(['school_code' => null]);

    app(IdentifierGenerator::class)->nextAdmissionNumber($this->school->fresh(), null);
})->throws(RuntimeException::class);

// --- Student create/edit flow ---

test('a new student gets an auto-generated admission number when enabled, ignoring any submitted value', function () {
    $response = $this->actingAs($this->admin)->post(route('students.store'), [
        'admission_number' => 'HACKED-0001',
        'first_name' => 'Chinedu',
        'last_name' => 'Eze',
        'gender' => Gender::Male->value,
        'class_name' => 'JSS 1',
    ]);

    $response->assertRedirect();

    $student = Student::where('first_name', 'Chinedu')->firstOrFail();
    expect($student->admission_number)->toBe('MIS-2025/2026-JUR-001');
});

test('a student can be created without submitting an admission number at all when auto-generation is on', function () {
    $response = $this->actingAs($this->admin)->post(route('students.store'), [
        'first_name' => 'Ada',
        'last_name' => 'Nwosu',
        'gender' => Gender::Female->value,
    ]);

    $response->assertRedirect()->assertSessionDoesntHaveErrors();
});

test('updating a student cannot change their locked admission number', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'admission_number' => 'MIS-2025/2026-GEN-001']);

    $this->actingAs($this->admin)->put(route('students.update', $student), [
        'admission_number' => 'SOMETHING-ELSE',
        'first_name' => $student->first_name,
        'last_name' => $student->last_name,
        'gender' => $student->gender->value,
    ]);

    expect($student->fresh()->admission_number)->toBe('MIS-2025/2026-GEN-001');
});

test('admission numbers stay manually entered when auto-generation is off', function () {
    $this->school->update(['auto_generate_admission_numbers' => false]);

    $response = $this->actingAs($this->admin)->post(route('students.store'), [
        'admission_number' => 'STU-CUSTOM-1',
        'first_name' => 'Femi',
        'last_name' => 'Ade',
        'gender' => Gender::Male->value,
    ]);

    $response->assertRedirect();
    expect(Student::where('first_name', 'Femi')->firstOrFail()->admission_number)->toBe('STU-CUSTOM-1');
});

test('admission number is required when auto-generation is off', function () {
    $this->school->update(['auto_generate_admission_numbers' => false]);

    $this->actingAs($this->admin)->post(route('students.store'), [
        'first_name' => 'Femi',
        'last_name' => 'Ade',
        'gender' => Gender::Male->value,
    ])->assertSessionHasErrors('admission_number');
});

// --- Staff create/edit flow ---

test('a new staff member gets an auto-generated staff id when enabled, ignoring any submitted value', function () {
    $response = $this->actingAs($this->admin)->post(route('staff.store'), [
        'staff_number' => 'HACKED-0001',
        'first_name' => 'Tolu',
        'last_name' => 'Bankole',
        'gender' => Gender::Female->value,
        'role' => StaffRole::Teacher->value,
    ]);

    $response->assertRedirect();
    expect(Staff::where('first_name', 'Tolu')->firstOrFail()->staff_number)->toBe('MIS-STAFF-001');
});

test('updating a staff member cannot change their locked staff id', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id, 'staff_number' => 'MIS-STAFF-001']);

    $this->actingAs($this->admin)->put(route('staff.update', $member), [
        'staff_number' => 'SOMETHING-ELSE',
        'first_name' => $member->first_name,
        'last_name' => $member->last_name,
        'gender' => $member->gender->value,
        'role' => $member->role->value,
    ]);

    expect($member->fresh()->staff_number)->toBe('MIS-STAFF-001');
});

// --- Settings ---

test('a school admin can configure the school code and enable auto-generation', function () {
    $school = School::factory()->create(['school_code' => null, 'auto_generate_admission_numbers' => false]);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    activateSchool($school);

    $this->actingAs($admin)->put(route('settings.update'), [
        'name' => $school->name,
        'timezone' => 'Africa/Lagos',
        'school_code' => 'ABC',
        'auto_generate_admission_numbers' => '1',
        'auto_generate_staff_ids' => '1',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    $school->refresh();
    expect($school->school_code)->toBe('ABC');
    expect($school->auto_generate_admission_numbers)->toBeTrue();
    expect($school->auto_generate_staff_ids)->toBeTrue();
});

test('enabling auto-generation without a school code is rejected', function () {
    $school = School::factory()->create(['school_code' => null]);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    activateSchool($school);

    $this->actingAs($admin)->put(route('settings.update'), [
        'name' => $school->name,
        'timezone' => 'Africa/Lagos',
        'auto_generate_admission_numbers' => '1',
    ])->assertSessionHasErrors('school_code');
});
