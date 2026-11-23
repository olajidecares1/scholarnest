<?php

use App\Enums\Gender;
use App\Enums\RegistrationSource;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Invoice;
use App\Models\RepositoryResult;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Registering a student/pupil who may already be on the school's records,
 * through the Add Student form or a bulk upload.
 *
 * Nothing is created straight away. The School Admin is shown the existing
 * record(s): admission number, details, and how they were registered. They
 * then confirm either that it is the same child, whose record is UPDATED
 * (moved to the right class, admission number kept, results, attendance and
 * payments untouched), or that it is a different child with the same name,
 * who is registered as new. A name alone is never treated as proof.
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

/** The file line number the review page gives a pupil's row. */
function lineFor($test, string $token, string $firstName): int
{
    $html = $test->actingAs($test->admin)->get(route('students.import.review', ['token' => $token]))->getContent();
    preg_match_all('/name="decisions\[(\d+)\]"/', $html, $m);

    // Rows are listed by first name; the matched rows carry decision boxes.
    foreach ($m[1] as $line) {
        return (int) $line;
    }

    throw new RuntimeException("No decision box for {$firstName}.");
}

/** Follows the Add Student form to its confirmation page; returns the token. */
function confirmToken($response): string
{
    $response->assertRedirect();
    preg_match('#/([A-Za-z0-9]{40})$#', (string) $response->headers->get('Location'), $m);
    expect($m[1] ?? null)->not->toBeNull();

    return $m[1];
}

// ---------------------------------------------------------------------------
// Add Student form
// ---------------------------------------------------------------------------

test('the same name added again is not saved; the admin is asked whether it is the same child', function () {
    addStudent($this);
    $existing = Student::firstOrFail();
    $sequenceBefore = $this->school->fresh()->next_admission_sequence;

    $token = confirmToken(addStudent($this, ['class_name' => 'JSS 1']));

    expect(Student::count())->toBe(1)
        // No admission number is used up while the question is open.
        ->and($this->school->fresh()->next_admission_sequence)->toBe($sequenceBefore);

    $this->actingAs($this->admin)
        ->get(route('students.existing.show', ['token' => $token]))
        ->assertOk()
        ->assertSee('Nothing has been saved yet')
        ->assertSee('Yes, this is Chinedu Okafor. Update their existing record.')
        ->assertSee($existing->admission_number)
        ->assertSee('Single Registration')
        ->assertSeeInOrder(['What will change', 'Class', 'Primary 3', 'JSS 1'])
        ->assertSee('No, this is a different child with the same name.')
        ->assertSee(route('students.show', $existing), false);
});

test('names are compared without case, extra spaces or word order', function (string $first, string $last) {
    addStudent($this);

    confirmToken(addStudent($this, ['first_name' => $first, 'last_name' => $last]));

    expect(Student::count())->toBe(1);
})->with([
    ['  chinedu ', 'OKAFOR'],
    ['Okafor', 'Chinedu'],
]);

test('confirming it is the same child moves them to the correct class and keeps everything else', function () {
    addStudent($this, ['class_name' => 'Primary 3', 'guardian_phone' => '08012345678', 'house' => 'Blue']);
    $student = Student::firstOrFail();
    $number = $student->admission_number;

    // History attached to the student before the correction.
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'Primary 3']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Mathematics']);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $student->id, 'score' => 74]);
    AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'class_name' => 'Primary 3']);
    Invoice::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'amount' => 25000]);
    RepositoryResult::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'examination_id' => $examination->id, 'class_name' => 'Primary 3']);

    // Registered again, in the right class, without a phone number this time.
    $token = confirmToken(addStudent($this, ['class_name' => 'JSS 1', 'date_of_birth' => '2014-05-02']));

    $this->actingAs($this->admin)
        ->post(route('students.existing.confirm', ['token' => $token]), ['choice' => 'update:'.$student->id])
        ->assertRedirect(route('students.show', $student))
        ->assertSessionHas('status', fn ($status) => str_contains($status, "Admission number {$number} is unchanged") && str_contains($status, 'Moved from Primary 3 to JSS 1'));

    $student->refresh();

    expect(Student::count())->toBe(1)
        ->and($student->class_name)->toBe('JSS 1')
        ->and($student->admission_number)->toBe($number)
        ->and($student->date_of_birth->toDateString())->toBe('2014-05-02')
        // A detail left blank does not wipe what the record already had.
        ->and($student->guardian_phone)->toBe('08012345678')
        ->and($student->house)->toBe('Blue')
        ->and($student->registration_source)->toBe(RegistrationSource::Single)
        // Everything attached to the student is still attached, unchanged.
        ->and(ExaminationScore::where('student_id', $student->id)->value('score'))->toEqual(74)
        ->and(AttendanceRecord::where('student_id', $student->id)->value('class_name'))->toBe('Primary 3')
        ->and(Invoice::where('student_id', $student->id)->count())->toBe(1)
        ->and(RepositoryResult::where('student_id', $student->id)->value('class_name'))->toBe('Primary 3');
});

test('with typed admission numbers, the number entered again is ignored and the old one kept', function () {
    $this->school->update(['auto_generate_admission_numbers' => false]);
    addStudent($this, ['admission_number' => 'P3-007']);
    $student = Student::firstOrFail();

    // The same number typed again does not fail as "taken": it is this child.
    $token = confirmToken(addStudent($this, ['admission_number' => 'P3-007', 'class_name' => 'JSS 1']));

    $this->actingAs($this->admin)->post(route('students.existing.confirm', ['token' => $token]), ['choice' => 'update:'.$student->id]);

    expect($student->fresh()->admission_number)->toBe('P3-007')
        ->and($student->fresh()->class_name)->toBe('JSS 1');
});

test('an admission number belonging to a child with a different name is refused, not offered', function () {
    $this->school->update(['auto_generate_admission_numbers' => false]);
    addStudent($this, ['admission_number' => 'P3-001']);

    addStudent($this, ['first_name' => 'Bola', 'last_name' => 'Ade', 'admission_number' => 'P3-001'])
        ->assertSessionHasErrors('admission_number');

    expect(Student::count())->toBe(1)
        ->and(Student::firstOrFail()->first_name)->toBe('Chinedu');
});

test('saying it is a different child registers them as new, with their own admission number', function () {
    addStudent($this);
    $existing = Student::firstOrFail();

    $token = confirmToken(addStudent($this, ['class_name' => 'JSS 1']));

    $this->actingAs($this->admin)
        ->post(route('students.existing.confirm', ['token' => $token]), ['choice' => 'new'])
        ->assertRedirect();

    $new = Student::whereKeyNot($existing->id)->sole();

    expect(Student::count())->toBe(2)
        ->and($new->class_name)->toBe('JSS 1')
        ->and($new->admission_number)->not->toBe($existing->admission_number)
        ->and($existing->fresh()->class_name)->toBe('Primary 3');
});

test('only a record that was offered can be updated', function () {
    addStudent($this);
    $other = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Someone', 'last_name' => 'Else', 'class_name' => 'Primary 3']);

    $token = confirmToken(addStudent($this, ['class_name' => 'JSS 1']));

    $this->actingAs($this->admin)
        ->post(route('students.existing.confirm', ['token' => $token]), ['choice' => 'update:'.$other->id])
        ->assertSessionHasErrors('choice');

    expect($other->fresh()->class_name)->toBe('Primary 3')
        ->and($other->fresh()->first_name)->toBe('Someone');
});

test('cancelling saves nothing and changes nothing', function () {
    addStudent($this);
    $token = confirmToken(addStudent($this, ['class_name' => 'JSS 1']));

    $this->actingAs($this->admin)
        ->delete(route('students.existing.cancel', ['token' => $token]))
        ->assertRedirect(route('students.index'));

    expect(Student::count())->toBe(1)
        ->and(Student::firstOrFail()->class_name)->toBe('Primary 3');

    // The question cannot be answered after it was cancelled.
    $this->actingAs($this->admin)
        ->post(route('students.existing.confirm', ['token' => $token]), ['choice' => 'new'])
        ->assertRedirect(route('students.index'));

    expect(Student::count())->toBe(1);
});

test('another admin cannot answer someone else\'s question', function () {
    addStudent($this);
    $token = confirmToken(addStudent($this, ['class_name' => 'JSS 1']));

    $colleague = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->actingAs($colleague)
        ->post(route('students.existing.confirm', ['token' => $token]), ['choice' => 'update:'.Student::firstOrFail()->id])
        ->assertRedirect(route('students.index'));

    expect(Student::firstOrFail()->class_name)->toBe('Primary 3');
});

test('two children with the same name are both offered, with what else matches', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Chinedu', 'last_name' => 'Okafor', 'class_name' => 'Primary 3', 'date_of_birth' => '2016-01-01']);
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Chinedu', 'last_name' => 'Okafor', 'class_name' => 'JSS 1', 'date_of_birth' => '2013-09-09']);

    $token = confirmToken(addStudent($this, ['class_name' => 'JSS 2', 'date_of_birth' => '2013-09-09']));

    $this->actingAs($this->admin)
        ->get(route('students.existing.show', ['token' => $token]))
        ->assertOk()
        ->assertSee('2 students/pupils with this name')
        // The child with the same birthday is listed first.
        ->assertSeeInOrder(['Same date of birth', 'Different date of birth']);
});

test('a student registered by bulk upload says so when offered', function () {
    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => bulkUpload($this, "First Name,Last Name,Gender\nAmaka,Obi,F\n")]));

    $existing = Student::firstOrFail();
    expect($existing->registration_source)->toBe(RegistrationSource::Bulk);

    $token = confirmToken(addStudent($this, ['first_name' => 'Amaka', 'last_name' => 'Obi', 'gender' => 'female']));

    $this->actingAs($this->admin)
        ->get(route('students.existing.show', ['token' => $token]))
        ->assertSee('Bulk Registration')
        ->assertSee($existing->admission_number);

    expect(Student::count())->toBe(1);
});

test('a record from before the method was tracked says so', function () {
    $existing = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Old', 'last_name' => 'Record', 'registration_source' => null]);

    $token = confirmToken(addStudent($this, ['first_name' => 'Old', 'last_name' => 'Record']));

    $this->actingAs($this->admin)
        ->get(route('students.existing.show', ['token' => $token]))
        ->assertSee('Not recorded')
        ->assertSee($existing->admission_number);
});

// ---------------------------------------------------------------------------
// Bulk upload
// ---------------------------------------------------------------------------

test('a bulk upload shows a student already registered, with the existing record, and asks what to do', function () {
    addStudent($this);
    $existing = Student::firstOrFail();

    $token = bulkUpload($this, "First Name,Last Name,Gender\nChinedu,Okafor,M\nBola,Ade,F\n", 'JSS 1');

    $this->actingAs($this->admin)->get(route('students.import.review', ['token' => $token]))
        ->assertOk()
        ->assertSee('May already be registered')
        ->assertSee($existing->admission_number)
        ->assertSee('name="decisions[', false)
        ->assertSee('Different child: add as a new student');

    // Without an answer for the row, nothing is imported.
    $this->actingAs($this->admin)
        ->post(route('students.import.store', ['token' => $token]))
        ->assertSessionHasErrors('decisions');

    expect(Student::count())->toBe(1)
        ->and($existing->fresh()->class_name)->toBe('Primary 3');
});

test('a bulk upload in the correct class moves a child registered in the wrong class, keeping their admission number and history', function () {
    addStudent($this, ['class_name' => 'Primary 3']);
    $student = Student::firstOrFail();
    $number = $student->admission_number;
    AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'class_name' => 'Primary 3']);
    Invoice::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id]);

    $token = bulkUpload($this, "First Name,Last Name,Gender,Guardian Phone\nChinedu,Okafor,M,08055556666\nBola,Ade,F,\n", 'JSS 1');
    $line = lineFor($this, $token, 'Chinedu');

    $this->actingAs($this->admin)
        ->post(route('students.import.store', ['token' => $token]), ['decisions' => [$line => 'update:'.$student->id]])
        ->assertRedirect()
        ->assertSessionHas('status', fn ($status) => str_contains($status, '1 student/pupil imported') && str_contains($status, '1 already registered was updated'))
        ->assertSessionHas('moved_students', fn ($moved) => str_contains($moved[0], 'Primary 3 → JSS 1'));

    $student->refresh();

    expect(Student::count())->toBe(2)
        ->and($student->class_name)->toBe('JSS 1')
        ->and($student->admission_number)->toBe($number)
        ->and($student->guardian_phone)->toBe('08055556666')
        ->and($student->registration_source)->toBe(RegistrationSource::Single)
        ->and(AttendanceRecord::where('student_id', $student->id)->count())->toBe(1)
        ->and(Invoice::where('student_id', $student->id)->count())->toBe(1)
        ->and(Student::where('first_name', 'Bola')->first()->registration_source)->toBe(RegistrationSource::Bulk);
});

test('a bulk row confirmed as a different child is added as new', function () {
    addStudent($this);
    $existing = Student::firstOrFail();

    $token = bulkUpload($this, "First Name,Last Name,Gender\nChinedu,Okafor,M\n", 'JSS 1');

    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $token]), ['decisions' => [lineFor($this, $token, 'Chinedu') => 'new']]);

    expect(Student::count())->toBe(2)
        ->and($existing->fresh()->class_name)->toBe('Primary 3')
        ->and(Student::whereKeyNot($existing->id)->sole()->class_name)->toBe('JSS 1');
});

test('a bulk row can only update a record offered for it', function () {
    addStudent($this);
    $other = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Zara', 'last_name' => 'Musa', 'class_name' => 'Primary 3']);

    $token = bulkUpload($this, "First Name,Last Name,Gender\nChinedu,Okafor,M\n", 'JSS 1');

    $this->actingAs($this->admin)
        ->post(route('students.import.store', ['token' => $token]), ['decisions' => [lineFor($this, $token, 'Chinedu') => 'update:'.$other->id]])
        ->assertSessionHasErrors('decisions');

    expect($other->fresh()->class_name)->toBe('Primary 3')
        ->and($other->fresh()->first_name)->toBe('Zara');
});

test('a single strong match is preselected; a name-only match is left for the admin to choose', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada', 'last_name' => 'Eze', 'class_name' => 'Primary 3', 'date_of_birth' => '2015-02-03']);
    $nameOnly = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Musa', 'last_name' => 'Bello', 'class_name' => 'Primary 3', 'date_of_birth' => null, 'guardian_phone' => null]);

    $token = bulkUpload($this, "First Name,Last Name,Gender,Date of Birth\nAda,Eze,F,03/02/2015\nMusa,Bello,M,\n", 'JSS 1');

    $html = $this->actingAs($this->admin)->get(route('students.import.review', ['token' => $token]))->getContent();
    $ada = Student::where('first_name', 'Ada')->value('id');

    expect($html)->toMatch('/value="update:'.$ada.'"\s+selected/')
        ->not->toMatch('/value="update:'.$nameOnly->id.'"\s+selected/');
});

test('another school\'s student with the same name is not a duplicate', function () {
    $other = School::factory()->create();
    Student::factory()->create(['school_id' => $other->id, 'first_name' => 'Chinedu', 'last_name' => 'Okafor']);

    addStudent($this)->assertSessionMissing('duplicate_students');

    expect(Student::where('school_id', $this->school->id)->count())->toBe(1);
});
