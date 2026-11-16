<?php

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\ClassUpdateBatch;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\ClassRoster\ClassRosterFileParser;
use App\Services\StudentImport\StudentImportReader;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->school = School::factory()->create(['auto_generate_admission_numbers' => false]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);

    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);
    $this->basic5 = SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'Basic 5']);
    $this->basic6 = SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'Basic 6']);

    $make = fn (string $number, string $first, string $last, string $gender, array $extra = []) => Student::factory()->create([
        'school_id' => $this->school->id,
        'admission_number' => $number,
        'first_name' => $first,
        'last_name' => $last,
        'gender' => $gender,
        'class_name' => 'Basic 5',
        'date_of_birth' => '2015-03-14',
        'house' => 'Blue',
        'guardian_name' => "Parent {$last}",
        'guardian_phone' => '08011111111',
        'guardian_email' => strtolower($first).'@example.com',
        'phone' => null,
        'email' => null,
        'address' => '1 School Road',
        'admission_date' => '2022-09-09',
        'notes' => null,
        ...$extra,
    ]);

    $this->ada = $make('B5-001', 'Ada', 'Okafor', 'female');
    $this->bola = $make('B5-002', 'Bola', 'Adeyemi', 'male');
    $this->chidi = $make('B5-003', 'Chidi', 'Eze', 'male');

    // Someone in another class, never touched by an update to Basic 5.
    $this->other = $make('B6-001', 'Dayo', 'Bello', 'male', ['class_name' => 'Basic 6']);
});

function rosterCsv(array $rows): UploadedFile
{
    $lines = array_map(fn ($row) => implode(',', array_map(fn ($cell) => '"'.str_replace('"', '""', (string) $cell).'"', $row)), $rows);

    return UploadedFile::fake()->createWithContent('basic-5.csv', implode("\n", $lines)."\n");
}

/** Upload a revised list and return the review token. */
function uploadRoster($test, UploadedFile $file, array $extra = []): string
{
    $response = $test->actingAs($test->admin)->post(route('classes.update.upload', $test->basic5), ['file' => $file, ...$extra]);
    $response->assertSessionHasNoErrors()->assertRedirect();
    preg_match('#/([A-Za-z0-9]{40})$#', $response->headers->get('Location'), $m);

    return $m[1];
}

function rosterHeader(): array
{
    return ['Admission Number', 'First Name', 'Last Name', 'Gender', 'House', 'Guardian Phone'];
}

test('the students list offers Edit Class for the class being viewed', function () {
    $this->actingAs($this->admin)
        ->get(route('students.index', ['class' => 'Basic 5']))
        ->assertOk()
        ->assertSee('Edit Class')
        ->assertSee(route('classes.update.show', $this->basic5), false);
});

test('the update page offers both ways to update the class', function () {
    $this->actingAs($this->admin)
        ->get(route('classes.update.show', $this->basic5))
        ->assertOk()
        ->assertSee('Upload File to Update Class')
        ->assertSee('Edit Class Within the System')
        ->assertSee('Download Class List')
        ->assertSee('Update history and backups');
});

test('another school cannot open, export or update the class', function () {
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => activateSchool(School::factory()->create())->id]);

    $this->actingAs($otherAdmin)->get(route('classes.update.show', $this->basic5))->assertForbidden();
    $this->actingAs($otherAdmin)->get(route('classes.update.export', $this->basic5))->assertForbidden();
    $this->actingAs($otherAdmin)->get(route('classes.update.edit', $this->basic5))->assertForbidden();
    $this->actingAs($otherAdmin)->post(route('classes.update.upload', $this->basic5), ['file' => rosterCsv([rosterHeader()])])->assertForbidden();
});

test('the exported class list reads straight back, in every format, with no changes detected', function (string $format) {
    $response = $this->actingAs($this->admin)->get(route('classes.update.export', ['class' => $this->basic5, 'format' => $format]));
    $response->assertOk();

    $file = UploadedFile::fake()->createWithContent("basic-5.{$format}", $response->getContent());
    $token = uploadRoster($this, $file);

    $this->actingAs($this->admin)
        ->get(route('classes.update.review', ['class' => $this->basic5, 'token' => $token]))
        ->assertOk()
        ->assertSee('No changes found');
})->with(['xlsx', 'csv', 'docx']);

test('a change to one student is detected as affecting one student, and only that field is applied', function () {
    $token = uploadRoster($this, rosterCsv([
        rosterHeader(),
        ['B5-001', 'Ada', 'Okafor', 'F', 'Blue', '08011111111'],
        ['B5-002', 'Bola', 'Adeyemi', 'M', 'Blue', '08099999999'],
        ['B5-003', 'Chidi', 'Eze', 'M', 'Blue', '08011111111'],
    ]));

    $this->actingAs($this->admin)
        ->get(route('classes.update.review', ['class' => $this->basic5, 'token' => $token]))
        ->assertOk()
        ->assertSee('One student/pupil is affected')
        ->assertSee('Bola Adeyemi')
        ->assertSee('08099999999')
        ->assertDontSee('Ada Okafor</span>', false);

    $updatedAt = $this->ada->fresh()->updated_at;

    $this->actingAs($this->admin)
        ->post(route('classes.update.apply', ['class' => $this->basic5, 'token' => $token]), [
            'updates' => [$this->bola->uuid => ['guardian_phone']],
        ])
        ->assertRedirect(route('classes.update.show', $this->basic5));

    expect($this->bola->fresh()->guardian_phone)->toBe('08099999999')
        ->and($this->bola->fresh()->house)->toBe('Blue')
        ->and($this->ada->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();

    $batch = ClassUpdateBatch::sole();
    expect($batch->summary['updated'])->toBe(1)
        ->and($batch->summary['fields'])->toBe(1)
        ->and($batch->snapshot['updated'][$this->bola->id]['before']['guardian_phone'])->toBe('08011111111');
});

test('when every student has changed the whole class can be updated at once', function () {
    $token = uploadRoster($this, rosterCsv([
        ['Admission No', 'Name', 'Sex', 'House'],
        ['B5-001', 'Okafor Ada', 'F', 'Green'],
        ['B5-002', 'Adeyemi Bola', 'M', 'Green'],
        ['B5-003', 'Eze Chidi', 'M', 'Red'],
    ]));

    $this->actingAs($this->admin)
        ->get(route('classes.update.review', ['class' => $this->basic5, 'token' => $token]))
        ->assertOk()
        ->assertSee('The entire class has been updated')
        ->assertSee('Apply to Entire Class');

    $this->actingAs($this->admin)
        ->post(route('classes.update.apply', ['class' => $this->basic5, 'token' => $token]), ['apply_all' => 1])
        ->assertRedirect();

    expect($this->ada->fresh()->house)->toBe('Green')
        ->and($this->bola->fresh()->house)->toBe('Green')
        ->and($this->chidi->fresh()->house)->toBe('Red')
        // Names written surname first in one column are the same names.
        ->and($this->ada->fresh()->first_name)->toBe('Ada')
        ->and($this->other->fresh()->house)->toBe('Blue')
        ->and(ClassUpdateBatch::sole()->summary['scope'])->toBe('all');
});

test('unticked students and details are left exactly as they are', function () {
    $token = uploadRoster($this, rosterCsv([
        rosterHeader(),
        ['B5-001', 'Ada', 'Okafor', 'F', 'Green', '08022222222'],
        ['B5-002', 'Bola', 'Adeyemi', 'M', 'Green', '08011111111'],
    ]));

    $this->actingAs($this->admin)->post(route('classes.update.apply', ['class' => $this->basic5, 'token' => $token]), [
        'updates' => [$this->ada->uuid => ['house']],
    ]);

    expect($this->ada->fresh()->house)->toBe('Green')
        ->and($this->ada->fresh()->guardian_phone)->toBe('08011111111')
        ->and($this->bola->fresh()->house)->toBe('Blue');
});

test('rows are matched by name when there is no admission number, and capitals are not a change', function () {
    $token = uploadRoster($this, rosterCsv([
        ['Surname', 'First Name', 'House'],
        ['OKAFOR', 'ADA', 'Yellow'],
        ['Eze', 'Chidi', 'Blue'],
    ]));

    $diff = cache()->get('class-update:'.$this->admin->id.':'.$this->basic5->id.':'.hash('sha256', $token));

    expect($diff['updates'])->toHaveKey($this->ada->uuid)
        ->and(array_keys($diff['updates'][$this->ada->uuid]['changes']))->toBe(['house'])
        ->and($diff['unchanged'])->toBe(1)
        ->and(collect($diff['not_in_file'])->pluck('name')->all())->toBe(['Bola Adeyemi']);
});

test('empty cells keep the value on record unless the school chose to clear them', function () {
    $file = fn () => rosterCsv([rosterHeader(), ['B5-001', 'Ada', 'Okafor', 'F', '', '08011111111']]);

    $keep = uploadRoster($this, $file());
    $diff = cache()->get('class-update:'.$this->admin->id.':'.$this->basic5->id.':'.hash('sha256', $keep));
    expect($diff['updates'])->toBe([]);

    $clear = uploadRoster($this, $file(), ['blanks' => 'clear']);
    $diff = cache()->get('class-update:'.$this->admin->id.':'.$this->basic5->id.':'.hash('sha256', $clear));
    expect($diff['updates'][$this->ada->uuid]['changes']['house'])->toBe(['old' => 'Blue', 'new' => null, 'kind' => 'cleared']);
});

test('a new row can be added as a new student and a new heading as a new column', function () {
    $token = uploadRoster($this, rosterCsv([
        [...rosterHeader(), 'Bus Stop'],
        ['B5-001', 'Ada', 'Okafor', 'F', 'Blue', '08011111111', 'Ikeja'],
        ['B5-009', 'Efe', 'Ojo', 'F', 'Red', '08033333333', 'Yaba'],
    ]));

    $this->actingAs($this->admin)
        ->get(route('classes.update.review', ['class' => $this->basic5, 'token' => $token]))
        ->assertOk()
        ->assertSee('New columns')
        ->assertSee('Bus Stop')
        ->assertSee('Not in Basic 5 yet');

    $this->actingAs($this->admin)->post(route('classes.update.apply', ['class' => $this->basic5, 'token' => $token]), [
        'updates' => [$this->ada->uuid => ['x:Bus Stop']],
        'additions' => ['line-3'],
        'new_columns' => ['Bus Stop'],
    ])->assertSessionHasNoErrors();

    $efe = Student::where('admission_number', 'B5-009')->sole();

    expect($efe->class_name)->toBe('Basic 5')
        ->and($efe->gender)->toBe(Gender::Female)
        ->and($efe->extra_fields)->toBe(['Bus Stop' => 'Yaba'])
        ->and($this->ada->fresh()->extra_fields)->toBe(['Bus Stop' => 'Ikeja'])
        ->and($this->basic5->fresh()->extra_columns)->toBe(['Bus Stop']);
});

test('problems are shown and never applied: unknown values, another class\'s student, a taken admission number', function () {
    $token = uploadRoster($this, rosterCsv([
        rosterHeader(),
        ['B5-001', 'Ada', 'Okafor', 'Robot', 'Blue', '08011111111'],
        ['B6-001', 'Dayo', 'Bello', 'M', 'Green', '08011111111'],
    ]));

    $this->actingAs($this->admin)
        ->get(route('classes.update.review', ['class' => $this->basic5, 'token' => $token]))
        ->assertOk()
        ->assertSee('Need attention')
        ->assertSee('is not recognised')
        ->assertSee('who is in Basic 6');

    $this->actingAs($this->admin)->post(route('classes.update.apply', ['class' => $this->basic5, 'token' => $token]), ['apply_all' => 1]);

    expect($this->ada->fresh()->gender)->toBe(Gender::Female)
        ->and($this->other->fresh()->house)->toBe('Blue');
});

test('a record edited after the review is not overwritten', function () {
    $token = uploadRoster($this, rosterCsv([rosterHeader(), ['B5-001', 'Ada', 'Okafor', 'F', 'Green', '08011111111']]));

    $this->ada->update(['house' => 'Purple']);

    $this->actingAs($this->admin)
        ->post(route('classes.update.apply', ['class' => $this->basic5, 'token' => $token]), ['apply_all' => 1])
        ->assertSessionHasErrors('apply');

    expect($this->ada->fresh()->house)->toBe('Purple');
});

test('an update can be rolled back from its backup, keeping later edits', function () {
    $token = uploadRoster($this, rosterCsv([
        rosterHeader(),
        ['B5-001', 'Ada', 'Okafor', 'F', 'Green', '08022222222'],
        ['B5-002', 'Bola', 'Adeyemi', 'M', 'Green', '08011111111'],
        ['B5-010', 'Femi', 'Ade', 'M', 'Red', ''],
    ]));

    $this->actingAs($this->admin)->post(route('classes.update.apply', ['class' => $this->basic5, 'token' => $token]), ['apply_all' => 1]);

    expect(Student::where('admission_number', 'B5-010')->exists())->toBeTrue();

    // A correction made after the update survives its rollback.
    $this->bola->fresh()->update(['house' => 'Orange']);

    $batch = ClassUpdateBatch::sole();

    $this->actingAs($this->admin)
        ->get(route('classes.update.backup', ['class' => $this->basic5, 'batch' => $batch]))
        ->assertOk()
        ->assertSee('08022222222');

    $this->actingAs($this->admin)
        ->post(route('classes.update.rollback', ['class' => $this->basic5, 'batch' => $batch]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('roster_notes');

    expect($this->ada->fresh()->house)->toBe('Blue')
        ->and($this->ada->fresh()->guardian_phone)->toBe('08011111111')
        ->and($this->bola->fresh()->house)->toBe('Orange')
        ->and(Student::where('admission_number', 'B5-010')->exists())->toBeFalse()
        ->and($batch->fresh()->isRolledBack())->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('classes.update.rollback', ['class' => $this->basic5, 'batch' => $batch]))
        ->assertSessionHasErrors('rollback');
});

test('the class editor shows every student and saves edits, new students, removals and new columns', function () {
    $this->actingAs($this->admin)
        ->get(route('classes.update.edit', $this->basic5))
        ->assertOk()
        ->assertSee('Save Changes')
        ->assertSee('Ada')
        ->assertDontSee('Dayo');

    $values = fn (Student $s, array $changes = []) => [
        'admission_number' => $s->admission_number,
        'first_name' => $s->first_name,
        'last_name' => $s->last_name,
        'gender' => $s->gender->value,
        'date_of_birth' => '2015-03-14',
        'class_name' => 'Basic 5',
        'house' => 'Blue',
        'blood_group' => '',
        'guardian_name' => $s->guardian_name,
        'guardian_phone' => '08011111111',
        'guardian_email' => $s->guardian_email,
        'phone' => '',
        'email' => '',
        'address' => '1 School Road',
        'admission_date' => '2022-09-09',
        'notes' => '',
        'is_active' => '1',
        ...$changes,
    ];

    $roster = [
        ['key' => $this->ada->uuid, 'uuid' => $this->ada->uuid, 'removed' => false, 'values' => $values($this->ada, ['house' => 'Green', 'notes' => 'Prefect', 'x:Locker' => 'L12'])],
        ['key' => $this->bola->uuid, 'uuid' => $this->bola->uuid, 'removed' => false, 'values' => $values($this->bola, ['class_name' => 'Basic 6', 'x:Locker' => ''])],
        ['key' => $this->chidi->uuid, 'uuid' => $this->chidi->uuid, 'removed' => true, 'values' => $values($this->chidi)],
        ['key' => 'new-1', 'uuid' => null, 'removed' => false, 'values' => ['admission_number' => 'B5-020', 'first_name' => 'Gift', 'last_name' => 'Nwosu', 'gender' => 'female', 'class_name' => 'Basic 5', 'is_active' => '1', 'x:Locker' => 'L13']],
    ];

    $this->actingAs($this->admin)
        ->post(route('classes.update.save', $this->basic5), ['roster' => json_encode($roster), 'columns' => json_encode(['Locker'])])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('classes.update.edit', $this->basic5));

    $gift = Student::where('admission_number', 'B5-020')->sole();

    expect($this->ada->fresh()->house)->toBe('Green')
        ->and($this->ada->fresh()->notes)->toBe('Prefect')
        ->and($this->ada->fresh()->extra_fields)->toBe(['Locker' => 'L12'])
        ->and($this->bola->fresh()->class_name)->toBe('Basic 6')
        ->and($this->chidi->fresh()->class_name)->toBeNull()
        ->and($gift->class_name)->toBe('Basic 5')
        ->and($gift->extra_fields)->toBe(['Locker' => 'L13'])
        ->and($this->basic5->fresh()->extra_columns)->toBe(['Locker']);

    $batch = ClassUpdateBatch::sole();
    expect($batch->source)->toBe('editor')
        ->and($batch->summary)->toMatchArray(['updated' => 2, 'added' => 1, 'removed' => 1, 'columns' => 1]);

    // And it all comes back with a rollback.
    $this->actingAs($this->admin)->post(route('classes.update.rollback', ['class' => $this->basic5, 'batch' => $batch]))->assertSessionHasNoErrors();

    expect($this->ada->fresh()->house)->toBe('Blue')
        ->and($this->ada->fresh()->extra_fields)->toBeNull()
        ->and($this->bola->fresh()->class_name)->toBe('Basic 5')
        ->and($this->chidi->fresh()->class_name)->toBe('Basic 5')
        ->and(Student::where('admission_number', 'B5-020')->exists())->toBeFalse()
        ->and($this->basic5->fresh()->extra_columns)->toBeNull();
});

test('the class editor refuses invalid cells and keeps the edits for correcting', function () {
    $roster = [
        ['key' => $this->ada->uuid, 'uuid' => $this->ada->uuid, 'removed' => false, 'values' => ['first_name' => '', 'last_name' => 'Okafor', 'gender' => 'female', 'class_name' => 'Basic 5', 'is_active' => '1', 'admission_number' => 'B5-001', 'guardian_email' => 'not-an-email']],
    ];

    $this->actingAs($this->admin)
        ->from(route('classes.update.edit', $this->basic5))
        ->post(route('classes.update.save', $this->basic5), ['roster' => json_encode($roster), 'columns' => '[]'])
        ->assertRedirect(route('classes.update.edit', $this->basic5))
        ->assertSessionHasErrors('roster')
        ->assertSessionHas('roster_errors', fn ($errors) => collect($errors)->pluck('field')->sort()->values()->all() === ['first_name', 'guardian_email']);

    expect($this->ada->fresh()->first_name)->toBe('Ada')
        ->and(ClassUpdateBatch::count())->toBe(0);
});

test('a new column cannot be named after a standard detail', function () {
    $this->actingAs($this->admin)
        ->from(route('classes.update.edit', $this->basic5))
        ->post(route('classes.update.save', $this->basic5), ['roster' => '[]', 'columns' => json_encode(['Phone Number'])])
        ->assertSessionHasErrors('roster');
});

test('the file parser understands a register with a single name column and other headings', function () {
    $rows = (new StudentImportReader)->readDelimited("S/N,Names of Pupils,Admission No.,Blood Group,Status,Locker\n1,OKAFOR Ada,B5-001,O+,Active,L1\n");
    $parsed = app(ClassRosterFileParser::class)->parse($rows);

    expect($parsed['columns'])->toBe(['admission_number', 'first_name', 'last_name', 'blood_group', 'is_active', 'x:Locker'])
        ->and($parsed['has_full_name'])->toBeTrue()
        ->and($parsed['rows'][0]['full_name'])->toBe('OKAFOR Ada');
});
