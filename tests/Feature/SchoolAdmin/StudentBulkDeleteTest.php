<?php

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;

/**
 * Ticking several students/pupils on the list and deleting them together,
 * instead of one after another.
 */
beforeEach(function () {
    $this->school = School::factory()->create([
        'school_code' => 'BLK',
        'current_session' => '2025/2026',
        'auto_generate_admission_numbers' => true,
    ]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);

    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'code' => 'PRY']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'Primary 1']);
});

function enrol($test, string $first): Student
{
    $test->actingAs($test->admin)->post(route('students.store'), [
        'first_name' => $first,
        'last_name' => 'Pupil',
        'gender' => Gender::Male->value,
        'class_name' => 'Primary 1',
    ])->assertSessionHasNoErrors();

    return Student::where('first_name', $first)->firstOrFail();
}

test('the list offers a checkbox for each student and a delete selected action', function () {
    $student = enrol($this, 'One');

    $this->actingAs($this->admin)->get(route('students.index'))
        ->assertOk()
        ->assertSee('Select every student on this page')
        ->assertSee('value="'.$student->uuid.'"', false)
        ->assertSee('Delete selected')
        ->assertSee(route('students.bulk-destroy'), false);
});

test('several selected students are deleted at once', function () {
    $a = enrol($this, 'Alpha');
    $b = enrol($this, 'Bravo');
    $c = enrol($this, 'Charlie');

    $this->actingAs($this->admin)
        ->delete(route('students.bulk-destroy'), ['students' => [$a->uuid, $c->uuid]])
        ->assertRedirect()
        ->assertSessionHas('status', fn ($status) => str_contains($status, '2 students/pupils were deleted'));

    expect(Student::pluck('first_name')->all())->toBe(['Bravo']);

    $this->assertDatabaseHas('audit_logs', ['action' => 'students.bulk_deleted']);
    expect(AuditLog::where('action', 'students.bulk_deleted')->value('description'))->toContain('Alpha Pupil')->toContain('Charlie Pupil');
});

test('the deleted admission numbers are reused by the next registrations', function () {
    $a = enrol($this, 'Alpha');
    enrol($this, 'Bravo');
    $c = enrol($this, 'Charlie');

    $this->actingAs($this->admin)->delete(route('students.bulk-destroy'), ['students' => [$a->uuid, $c->uuid]]);

    expect(enrol($this, 'Delta')->admission_number)->toBe('BLK-2025/2026-PRY-001')
        ->and(enrol($this, 'Echo')->admission_number)->toBe('BLK-2025/2026-PRY-003');
});

test('another school\'s students cannot be deleted through the selection', function () {
    $mine = enrol($this, 'Mine');
    $theirs = Student::factory()->create(['school_id' => School::factory()->create()->id]);

    $this->actingAs($this->admin)->delete(route('students.bulk-destroy'), ['students' => [$mine->uuid, $theirs->uuid]]);

    expect(Student::whereKey($theirs->id)->exists())->toBeTrue()
        ->and(Student::whereKey($mine->id)->exists())->toBeFalse();
});

test('nothing selected is refused', function () {
    enrol($this, 'One');

    $this->actingAs($this->admin)
        ->delete(route('students.bulk-destroy'), ['students' => []])
        ->assertSessionHasErrors('students');

    expect(Student::count())->toBe(1);
});

test('more students can be shown per page to select a larger batch', function () {
    foreach (range(1, 20) as $i) {
        Student::factory()->create(['school_id' => $this->school->id]);
    }

    $this->actingAs($this->admin)->get(route('students.index', ['per_page' => 50]))
        ->assertOk()
        ->assertViewHas('students', fn ($page) => $page->perPage() === 50 && $page->count() === 20);

    // Anything else falls back to the usual page size.
    $this->actingAs($this->admin)->get(route('students.index', ['per_page' => 9999]))
        ->assertViewHas('students', fn ($page) => $page->perPage() === 15);
});
