<?php

use App\Enums\Gender;
use App\Enums\RegistrationSource;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * A student/pupil whose name is already registered is refused, however the
 * new registration arrives: the Add Student form or a bulk upload. Nothing is
 * saved, and the School Admin is shown the existing record: its admission
 * number, its details, and whether it came from a single or bulk registration.
 */
beforeEach(function () {
    $this->school = School::factory()->create([
        'school_code' => 'DUP',
        'current_session' => '2025/2026',
        'auto_generate_admission_numbers' => true,
    ]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);

    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'Primary 3']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'JSS 1']);
});

function addStudent($test, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('students.store'), [
        'first_name' => 'Chinedu',
        'last_name' => 'Okafor',
        'gender' => Gender::Male->value,
        'class_name' => 'Primary 3',
        ...$overrides,
    ]);
}

function bulkUpload($test, string $csv, string $class = 'Primary 3'): string
{
    $response = $test->actingAs($test->admin)->post(route('students.import.preview'), [
        'class_name' => $class,
        'file' => UploadedFile::fake()->createWithContent('list.csv', $csv),
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();
    preg_match('#/([A-Za-z0-9]{40})$#', $response->headers->get('Location'), $m);

    return $m[1];
}

test('a single registration records how the student was registered', function () {
    addStudent($this)->assertSessionHasNoErrors();

    expect(Student::firstOrFail()->registration_source)->toBe(RegistrationSource::Single);
});

test('the same name added again is refused before anything is saved', function () {
    addStudent($this);
    $sequenceBefore = $this->school->fresh()->next_admission_sequence;

    $response = addStudent($this, ['class_name' => 'JSS 1', 'gender' => Gender::Female->value]);

    $response->assertRedirect()->assertSessionHas('duplicate_students');

    expect(Student::count())->toBe(1)
        // No admission number was used up by the refused registration.
        ->and($this->school->fresh()->next_admission_sequence)->toBe($sequenceBefore);
});

test('names are compared without case or extra spaces', function () {
    addStudent($this);

    addStudent($this, ['first_name' => '  chinedu ', 'last_name' => 'OKAFOR'])->assertSessionHas('duplicate_students');

    expect(Student::count())->toBe(1);
});

test('the notice carries the existing admission number, details and registration method', function () {
    addStudent($this, ['guardian_name' => 'Ngozi Okafor', 'guardian_phone' => '08012345678']);
    $existing = Student::firstOrFail();

    $notice = addStudent($this)->getSession()->get('duplicate_students')[0];

    expect($notice['admission_number'])->toBe($existing->admission_number)
        ->and($notice['student_id'])->toBe($existing->id)
        ->and($notice['registration_source'])->toBe('Single Registration')
        ->and($notice['is_bulk'])->toBeFalse()
        ->and($notice['details'])->toMatchArray([
            'Full name' => 'Chinedu Okafor',
            'Admission number' => $existing->admission_number,
            'Class' => 'Primary 3',
            'Parent/guardian' => 'Ngozi Okafor',
            'Guardian phone' => '08012345678',
            'Registered through' => 'Single Registration',
        ]);
});

test('the notice is shown prominently on the students page', function () {
    addStudent($this);
    $existing = Student::firstOrFail();

    $this->followingRedirects()
        ->actingAs($this->admin)
        ->from(route('students.index'))
        ->post(route('students.store'), ['first_name' => 'Chinedu', 'last_name' => 'Okafor', 'gender' => 'male', 'class_name' => 'JSS 1'])
        ->assertOk()
        ->assertSee('Student already registered. The new registration was not saved.')
        ->assertSee($existing->admission_number)
        ->assertSee('Single Registration')
        ->assertSee(route('students.show', $existing), false);
});

test('a student registered by bulk upload is refused on the single form, and says so', function () {
    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => bulkUpload($this, "First Name,Last Name,Gender\nAmaka,Obi,F\n")]));

    $existing = Student::firstOrFail();
    expect($existing->registration_source)->toBe(RegistrationSource::Bulk);

    $notice = addStudent($this, ['first_name' => 'Amaka', 'last_name' => 'Obi', 'gender' => 'female'])
        ->getSession()->get('duplicate_students')[0];

    expect(Student::count())->toBe(1)
        ->and($notice['registration_source'])->toBe('Bulk Registration')
        ->and($notice['is_bulk'])->toBeTrue()
        ->and($notice['admission_number'])->toBe($existing->admission_number);
});

test('a student added one at a time is skipped by a bulk upload, with the existing record shown', function () {
    addStudent($this);
    $existing = Student::firstOrFail();

    $token = bulkUpload($this, "First Name,Last Name,Gender\nChinedu,Okafor,M\nBola,Ade,F\n", 'JSS 1');

    $this->actingAs($this->admin)->get(route('students.import.review', ['token' => $token]))
        ->assertOk()
        ->assertSee('already registered and will not be saved again')
        ->assertSee($existing->admission_number)
        ->assertSee('Single Registration');

    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $token]));

    expect(Student::where('first_name', 'Chinedu')->count())->toBe(1)
        ->and(Student::where('first_name', 'Bola')->first()->registration_source)->toBe(RegistrationSource::Bulk);
});

test('a record from before the method was tracked says so', function () {
    $existing = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Old', 'last_name' => 'Record', 'registration_source' => null]);

    $notice = addStudent($this, ['first_name' => 'Old', 'last_name' => 'Record'])->getSession()->get('duplicate_students')[0];

    expect($notice['is_bulk'])->toBeNull()
        ->and($notice['registration_source'])->toContain('Not recorded')
        ->and($notice['admission_number'])->toBe($existing->admission_number);
});

test('another school\'s student with the same name is not a duplicate', function () {
    $other = School::factory()->create();
    Student::factory()->create(['school_id' => $other->id, 'first_name' => 'Chinedu', 'last_name' => 'Okafor']);

    addStudent($this)->assertSessionMissing('duplicate_students');

    expect(Student::where('school_id', $this->school->id)->count())->toBe(1);
});
