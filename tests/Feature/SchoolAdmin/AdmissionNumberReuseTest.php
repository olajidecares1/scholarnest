<?php

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\IdentifierGenerator;
use Illuminate\Http\UploadedFile;

/**
 * A deleted student's admission number is given to the next student
 * registered, instead of the numbering moving on and leaving it unused.
 */
beforeEach(function () {
    $this->school = School::factory()->create([
        'school_code' => 'RSE',
        'current_session' => '2025/2026',
        'auto_generate_admission_numbers' => true,
    ]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);

    $primary = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'code' => 'PRY']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $primary->id, 'name' => 'Primary 1']);
    $senior = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'code' => 'SEN']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $senior->id, 'name' => 'SS 1']);
});

function registerPupil($test, string $first, string $class = 'Primary 1'): Student
{
    $test->actingAs($test->admin)->post(route('students.store'), [
        'first_name' => $first,
        'last_name' => 'Pupil',
        'gender' => Gender::Female->value,
        'class_name' => $class,
    ])->assertSessionHasNoErrors()->assertSessionMissing('duplicate_students');

    return Student::where('first_name', $first)->firstOrFail();
}

test('a deleted admission number is issued to the next student registered', function () {
    registerPupil($this, 'One');
    $second = registerPupil($this, 'Two');
    registerPupil($this, 'Three');

    $this->actingAs($this->admin)->delete(route('students.destroy', $second))->assertRedirect();

    expect(registerPupil($this, 'Four')->admission_number)->toBe('RSE-2025/2026-PRY-002')
        // Reused, so the counter did not move.
        ->and(registerPupil($this, 'Five')->admission_number)->toBe('RSE-2025/2026-PRY-004');
});

test('several freed numbers are reused lowest first', function () {
    $students = collect(['A', 'B', 'C', 'D'])->map(fn ($name) => registerPupil($this, $name));

    $this->actingAs($this->admin)->delete(route('students.destroy', $students[2]));
    $this->actingAs($this->admin)->delete(route('students.destroy', $students[0]));

    expect(registerPupil($this, 'E')->admission_number)->toBe('RSE-2025/2026-PRY-001')
        ->and(registerPupil($this, 'F')->admission_number)->toBe('RSE-2025/2026-PRY-003')
        ->and(registerPupil($this, 'G')->admission_number)->toBe('RSE-2025/2026-PRY-005');
});

test('the freed sequence is reissued with the new student\'s own level code', function () {
    $first = registerPupil($this, 'One');
    registerPupil($this, 'Two');

    $this->actingAs($this->admin)->delete(route('students.destroy', $first));

    expect(registerPupil($this, 'Senior', 'SS 1')->admission_number)->toBe('RSE-2025/2026-SEN-001');
});

test('a bulk upload reuses freed numbers too', function () {
    registerPupil($this, 'One');
    $second = registerPupil($this, 'Two');
    $this->actingAs($this->admin)->delete(route('students.destroy', $second));

    $response = $this->actingAs($this->admin)->post(route('students.import.preview'), [
        'class_name' => 'Primary 1',
        'file' => UploadedFile::fake()->createWithContent('list.csv', "First Name,Last Name,Gender\nBulk,Alpha,F\nBulk,Beta,M\n"),
    ]);
    preg_match('#/([A-Za-z0-9]{40})$#', $response->headers->get('Location'), $m);
    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $m[1]]));

    expect(Student::where('last_name', 'Alpha')->value('admission_number'))->toBe('RSE-2025/2026-PRY-002')
        ->and(Student::where('last_name', 'Beta')->value('admission_number'))->toBe('RSE-2025/2026-PRY-003');
});

test('the add student form previews the number that will actually be issued', function () {
    registerPupil($this, 'One');
    $second = registerPupil($this, 'Two');
    $this->actingAs($this->admin)->delete(route('students.destroy', $second));

    expect(app(IdentifierGenerator::class)->peekAdmissionSequence($this->school->fresh()))->toBe(2);

    $this->actingAs($this->admin)->get(route('students.index'))
        ->assertOk()
        ->assertSee('nextAdmissionSequence: 2', false);
});

test('reserving numbers without saving them never hands one out twice', function () {
    $generator = app(IdentifierGenerator::class);

    $numbers = collect(range(1, 5))->map(fn () => $generator->nextAdmissionNumber($this->school, 'Primary 1'));

    expect($numbers->unique())->toHaveCount(5);
});

test('a number typed in by the school in its own format is not part of the pool', function () {
    $manual = Student::factory()->create(['school_id' => $this->school->id, 'admission_number' => 'MY-OWN-7']);
    $manual->delete();

    expect(registerPupil($this, 'Next')->admission_number)->toBe('RSE-2025/2026-PRY-001');
});
