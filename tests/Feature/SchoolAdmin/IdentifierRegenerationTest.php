<?php

use App\Enums\Gender;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\IdentifierChange;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\IdentifierGenerator;
use App\Services\IdentifierRegenerator;
use App\Support\PreviousIdentifier;

/**
 * Regenerating admission numbers and Staff IDs after a School Code is set or
 * changed. See App\Services\IdentifierRegenerator.
 *
 * Asks first, numbers in order of registration (oldest first), keeps numbers
 * typed by hand unless asked, never issues a duplicate, keeps every old number
 * on record, and the next registration continues the sequence.
 */
beforeEach(function () {
    $this->school = School::factory()->create([
        'school_code' => 'TMP',
        'current_session' => '2025/2026',
        'auto_generate_admission_numbers' => true,
        'auto_generate_staff_ids' => true,
    ]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);

    $primary = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'code' => 'PRY']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $primary->id, 'name' => 'Primary 1']);
});

function regenPupil(School $school, string $number, string $registeredOn): Student
{
    return Student::factory()->create([
        'school_id' => $school->id,
        'admission_number' => $number,
        'class_name' => 'Primary 1',
        'created_at' => $registeredOn,
    ]);
}

function regenStaff(School $school, string $number, string $addedOn): Staff
{
    return Staff::factory()->create([
        'school_id' => $school->id,
        'staff_number' => $number,
        'created_at' => $addedOn,
    ]);
}

function regenChangeCode($test, string $code)
{
    $school = $test->school->fresh();

    return $test->actingAs($test->admin)->put(route('settings.update'), [
        'name' => $school->name,
        'timezone' => $school->timezone ?: 'Africa/Lagos',
        'current_session' => $school->current_session,
        'contact_address' => $school->contact_address,
        'school_code' => $code,
        'auto_generate_admission_numbers' => '1',
        'auto_generate_staff_ids' => '1',
    ]);
}

function regenRun($test, string $type, bool $includeManual = false)
{
    return $test->actingAs($test->admin)->post(route('settings.identifiers.regenerate'), array_filter([
        'type' => $type,
        'include_manual' => $includeManual ? '1' : null,
    ]));
}

// -----------------------------------------------------------------------------
// Ask first
// -----------------------------------------------------------------------------

test('changing the school code asks before any number changes', function () {
    regenPupil($this->school, 'TMP-2025/2026-PRY-001', '2025-01-01 08:00:00');
    regenPupil($this->school, 'TMP-2025/2026-PRY-002', '2025-01-02 08:00:00');

    regenChangeCode($this, 'AVS')
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.identifiers'));

    $school = $this->school->fresh();

    expect($school->school_code)->toBe('AVS')
        ->and($school->previous_school_codes)->toBe(['TMP'])
        ->and(Student::where('school_id', $school->id)->orderBy('id')->pluck('admission_number')->all())
        ->toBe(['TMP-2025/2026-PRY-001', 'TMP-2025/2026-PRY-002']);

    $this->actingAs($this->admin)->get(route('settings.identifiers'))
        ->assertOk()
        ->assertSee('students already have admission numbers.')
        ->assertSee('Regenerate them using school code')
        ->assertSee('AVS');
});

test('a school with nobody registered yet is not sent to the regenerate page', function () {
    $response = regenChangeCode($this, 'AVS')->assertSessionHasNoErrors()->assertRedirect();

    expect($response->headers->get('Location'))->not->toBe(route('settings.identifiers'))
        ->and($this->school->fresh()->previous_school_codes)->toBe(['TMP']);
});

test('every old code is remembered when the code changes more than once', function () {
    regenPupil($this->school, 'TMP-2025/2026-PRY-001', '2025-01-01 08:00:00');

    regenChangeCode($this, 'AVS');
    regenChangeCode($this, 'AVX');

    expect($this->school->fresh()->previous_school_codes)->toBe(['TMP', 'AVS']);
});

test('nothing is regenerated without a school code', function () {
    regenPupil($this->school, 'OLD-1', '2025-01-01 08:00:00');
    School::whereKey($this->school->id)->update(['school_code' => null]);

    regenRun($this, IdentifierGenerator::ADMISSION)->assertSessionHasErrors('school_code');

    expect(Student::where('school_id', $this->school->id)->value('admission_number'))->toBe('OLD-1');
});

// -----------------------------------------------------------------------------
// Regenerating
// -----------------------------------------------------------------------------

test('new numbers are given in order of registration, oldest first', function () {
    $late = regenPupil($this->school, 'TMP-2025/2026-PRY-001', '2025-03-01 08:00:00');
    $early = regenPupil($this->school, 'TMP-2025/2026-PRY-002', '2025-01-01 08:00:00');
    $middle = regenPupil($this->school, 'TMP-2025/2026-PRY-003', '2025-02-01 08:00:00');

    regenChangeCode($this, 'AVS');
    regenRun($this, IdentifierGenerator::ADMISSION)
        ->assertRedirect(route('settings.identifiers'))
        ->assertSessionHas('status');

    expect($early->fresh()->admission_number)->toBe('AVS-2025/2026-PRY-001')
        ->and($middle->fresh()->admission_number)->toBe('AVS-2025/2026-PRY-002')
        ->and($late->fresh()->admission_number)->toBe('AVS-2025/2026-PRY-003');
});

test('the next student registered continues the sequence with no gap', function () {
    regenPupil($this->school, 'TMP-2025/2026-PRY-001', '2025-01-01 08:00:00');
    regenPupil($this->school, 'TMP-2025/2026-PRY-002', '2025-01-02 08:00:00');

    regenChangeCode($this, 'AVS');
    regenRun($this, IdentifierGenerator::ADMISSION);

    $this->actingAs($this->admin)->post(route('students.store'), [
        'first_name' => 'Newest',
        'last_name' => 'Pupil',
        'gender' => Gender::Female->value,
        'class_name' => 'Primary 1',
    ])->assertSessionHasNoErrors();

    expect(Student::where('first_name', 'Newest')->value('admission_number'))->toBe('AVS-2025/2026-PRY-003')
        ->and((int) $this->school->fresh()->next_admission_sequence)->toBe(4);
});

test('a new number another student still holds never causes a duplicate', function () {
    // Same code, numbers out of registration order: the two swap.
    $later = regenPupil($this->school, 'TMP-2025/2026-PRY-001', '2025-02-01 08:00:00');
    $earlier = regenPupil($this->school, 'TMP-2025/2026-PRY-002', '2025-01-01 08:00:00');

    regenRun($this, IdentifierGenerator::ADMISSION)->assertSessionHasNoErrors();

    expect($earlier->fresh()->admission_number)->toBe('TMP-2025/2026-PRY-001')
        ->and($later->fresh()->admission_number)->toBe('TMP-2025/2026-PRY-002')
        ->and(Student::where('school_id', $this->school->id)->pluck('admission_number')->unique())->toHaveCount(2);
});

test('numbers typed by hand are kept unless the admin chooses to replace them too', function () {
    $manual = regenPupil($this->school, 'MY-OWN-7', '2025-01-01 08:00:00');
    $generated = regenPupil($this->school, 'TMP-2025/2026-PRY-001', '2025-02-01 08:00:00');

    regenChangeCode($this, 'AVS');

    expect(app(IdentifierRegenerator::class)->summary($this->school->fresh(), IdentifierGenerator::ADMISSION))
        ->toBe(['total' => 2, 'generated' => 1, 'manual' => 1, 'outdated' => 1]);

    regenRun($this, IdentifierGenerator::ADMISSION);

    expect($manual->fresh()->admission_number)->toBe('MY-OWN-7')
        ->and($generated->fresh()->admission_number)->toBe('AVS-2025/2026-PRY-001');

    regenRun($this, IdentifierGenerator::ADMISSION, includeManual: true);

    expect($manual->fresh()->admission_number)->toBe('AVS-2025/2026-PRY-001')
        ->and($generated->fresh()->admission_number)->toBe('AVS-2025/2026-PRY-002');
});

// -----------------------------------------------------------------------------
// Old numbers stay on record
// -----------------------------------------------------------------------------

test('the old number is kept on record and still finds the student', function () {
    $student = regenPupil($this->school, 'TMP-2025/2026-PRY-001', '2025-01-01 08:00:00');

    regenChangeCode($this, 'AVS');
    regenRun($this, IdentifierGenerator::ADMISSION);

    $student->refresh();

    expect($student->admission_number)->toBe('AVS-2025/2026-PRY-001')
        ->and(IdentifierChange::previousFor($student))->toBe(['TMP-2025/2026-PRY-001'])
        ->and(PreviousIdentifier::current($this->school->fresh(), IdentifierGenerator::ADMISSION, 'TMP-2025/2026-PRY-001'))
        ->toBe('AVS-2025/2026-PRY-001')
        ->and(IdentifierChange::holdersMatching($this->school->id, IdentifierGenerator::ADMISSION, 'TMP-2025'))
        ->toBe([$student->id]);
});

test('an old number never leads to someone who holds that number today', function () {
    regenPupil($this->school, 'TMP-2025/2026-PRY-001', '2025-01-01 08:00:00');

    regenChangeCode($this, 'AVS');
    regenRun($this, IdentifierGenerator::ADMISSION);

    // Somebody is later given the old number by hand.
    regenPupil($this->school, 'TMP-2025/2026-PRY-001', '2025-06-01 08:00:00');

    expect(PreviousIdentifier::holder($this->school->fresh(), IdentifierGenerator::ADMISSION, 'TMP-2025/2026-PRY-001'))->toBeNull();
});

test('running it again when every number already follows the code changes nothing', function () {
    regenPupil($this->school, 'TMP-2025/2026-PRY-001', '2025-01-01 08:00:00');

    regenChangeCode($this, 'AVS');
    regenRun($this, IdentifierGenerator::ADMISSION);
    regenRun($this, IdentifierGenerator::ADMISSION)->assertSessionHas('status');

    expect(IdentifierChange::where('school_id', $this->school->id)->count())->toBe(1);
});

// -----------------------------------------------------------------------------
// Staff IDs work the same way
// -----------------------------------------------------------------------------

test('staff ids are regenerated oldest first and the next hire continues the sequence', function () {
    $later = regenStaff($this->school, 'TMP-STAFF-001', '2025-02-01 08:00:00');
    $earlier = regenStaff($this->school, 'TMP-STAFF-002', '2025-01-01 08:00:00');

    regenChangeCode($this, 'AVS')->assertRedirect(route('settings.identifiers'));
    regenRun($this, IdentifierGenerator::STAFF)->assertSessionHasNoErrors();

    expect($earlier->fresh()->staff_number)->toBe('AVS-STAFF-001')
        ->and($later->fresh()->staff_number)->toBe('AVS-STAFF-002')
        ->and(IdentifierChange::previousFor($earlier->fresh()))->toBe(['TMP-STAFF-002']);

    $this->actingAs($this->admin)->post(route('staff.store'), [
        'first_name' => 'Newest',
        'last_name' => 'Teacher',
        'gender' => Gender::Female->value,
        'role' => StaffRole::Teacher->value,
    ])->assertSessionHasNoErrors();

    expect(Staff::where('first_name', 'Newest')->value('staff_number'))->toBe('AVS-STAFF-003');
});
