<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Support\PortalNavigation;
use Illuminate\Support\Facades\Gate;
use Tests\Support\UploadFixtures;

/**
 * Class Teachers may add pupils to their own class and edit their details.
 * Nobody but a School Admin may delete a student, change an admission number,
 * set login details or change active status, from any screen or by any
 * request.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['auto_generate_admission_numbers' => false]), PlanKey::Basic);
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);

    foreach (['Basic 5', 'Basic 6', 'JSS 1'] as $order => $name) {
        SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => $name, 'sort_order' => $order]);
    }

    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
    TeacherAssignment::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'Basic 5',
    ]);

    $this->mine = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'Basic 5', 'first_name' => 'Ade', 'last_name' => 'Bello', 'is_active' => true]);
    $this->notMine = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'Basic 6', 'first_name' => 'Kemi', 'last_name' => 'Ojo', 'is_active' => true]);
});

/**
 * @return array<string, string>
 */
function newPupil(array $overrides = []): array
{
    return [
        'class_name' => 'Basic 5',
        'first_name' => 'Tola',
        'last_name' => 'Adewale',
        'gender' => 'female',
        'admission_number' => 'B5/2026/101',
        ...$overrides,
    ];
}

/**
 * What the edit form posts for a pupil: every editable detail, as it stands,
 * with the given changes.
 *
 * @return array<string, mixed>
 */
function editForm(Student $student, array $changes = []): array
{
    return [
        'first_name' => $student->first_name,
        'last_name' => $student->last_name,
        'gender' => $student->gender?->value,
        'class_name' => $student->class_name,
        ...$changes,
    ];
}

test('a Class Teacher sees their own class, with no delete anywhere on the page', function () {
    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.students.index', $this->school))
        ->assertOk()
        ->assertSee('Ade Bello')
        ->assertDontSee('Kemi Ojo')
        ->assertSee('Add Student/Pupil')
        ->assertSee('Edit details')
        ->assertSee('Parent/Guardian')
        ->assertDontSee('Delete')
        ->assertDontSee('fa-trash', false);
});

test('a Class Teacher adds a pupil to their own class', function () {
    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil())
        ->assertRedirect(route('staff.students.index', ['school' => $this->school, 'class' => 'Basic 5']))
        ->assertSessionHasNoErrors();

    $added = Student::where('admission_number', 'B5/2026/101')->sole();

    expect($added->school_id)->toBe($this->school->id)
        ->and($added->class_name)->toBe('Basic 5')
        ->and($added->fullName())->toBe('Tola Adewale')
        ->and(AuditLog::where('action', 'students.added_by_class_teacher')->exists())->toBeTrue();
});

test('the admission number is given automatically where the school does that', function () {
    $this->school->update(['auto_generate_admission_numbers' => true]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil(['admission_number' => null]))
        ->assertSessionHasNoErrors();

    expect(Student::where('first_name', 'Tola')->sole()->admission_number)->not->toBeEmpty();
});

test('a Class Teacher cannot add a pupil to another class', function () {
    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil(['class_name' => 'Basic 6']))
        ->assertSessionHasErrors('class_name');

    expect(Student::where('first_name', 'Tola')->exists())->toBeFalse();
});

test('a teacher who is not a Class Teacher cannot add anyone', function () {
    $subjectOnly = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $this->actingAs($subjectOnly, 'staff')
        ->get(route('staff.students.index', $this->school))
        ->assertOk()
        ->assertSee('You haven\'t been assigned as a Class Teacher yet', false);

    $this->actingAs($subjectOnly, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil())
        ->assertForbidden();

    expect(Student::where('first_name', 'Tola')->exists())->toBeFalse();
});

test('a duplicate admission number is refused', function () {
    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil(['admission_number' => $this->mine->admission_number]))
        ->assertSessionHasErrors('admission_number');
});

test('a Class Teacher adds a pupil with all their details, photo included', function () {
    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil([
            'date_of_birth' => '2015-04-02',
            'house' => 'Blue House',
            'guardian_name' => 'Mrs Adewale',
            'guardian_phone' => '08031234567',
            'guardian_email' => 'parent@example.com',
            'address' => '12 Allen Avenue, Ikeja',
            'admission_date' => '2026-09-14',
            'notes' => 'Allergic to peanuts',
            'photo' => UploadFixtures::cameraJpeg(),
        ]))
        ->assertSessionHasNoErrors();

    $added = Student::where('admission_number', 'B5/2026/101')->sole();

    expect($added->house)->toBe('Blue House')
        ->and($added->guardian_name)->toBe('Mrs Adewale')
        ->and($added->guardian_phone)->toBe('08031234567')
        ->and($added->address)->toBe('12 Allen Avenue, Ikeja')
        ->and($added->date_of_birth->format('Y-m-d'))->toBe('2015-04-02')
        ->and($added->photo_path)->not->toBeNull();
});

test('a Class Teacher edits a pupil\'s details in their class', function () {
    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.students.update', [$this->school, $this->mine]), editForm($this->mine, [
            'first_name' => 'Adebayo',
            'last_name' => 'Bello-Smith',
            'gender' => 'male',
            'guardian_name' => 'Mr Bello',
            'guardian_phone' => '08099998888',
            'house' => 'Green House',
            'notes' => 'Moved from another school',
        ]))
        ->assertSessionHasNoErrors();

    $fresh = $this->mine->fresh();

    expect($fresh->fullName())->toBe('Adebayo Bello-Smith')
        ->and($fresh->guardian_name)->toBe('Mr Bello')
        ->and($fresh->guardian_phone)->toBe('08099998888')
        ->and($fresh->house)->toBe('Green House')
        ->and(AuditLog::where('action', 'students.updated_by_class_teacher')->latest('id')->first()->description)
        ->toContain('first name')->toContain('guardian name');
});

test('editing cannot touch the admission number, login, active status or school', function () {
    $before = $this->mine->only(['admission_number', 'is_active', 'school_id', 'password', 'registration_source']);

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.students.update', [$this->school, $this->mine]), editForm($this->mine, [
            'admission_number' => 'HACKED/1',
            'is_active' => false,
            'school_id' => 999,
            'password' => 'NewPassword123!',
            'registration_source' => 'bulk',
        ]))
        ->assertSessionHasNoErrors();

    expect($this->mine->fresh()->only(['admission_number', 'is_active', 'school_id', 'password', 'registration_source']))->toBe($before);
});

test('a pupil can be moved only between the teacher\'s own classes', function () {
    TeacherAssignment::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'JSS 1',
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.students.update', [$this->school, $this->mine]), editForm($this->mine, ['class_name' => 'Basic 6']))
        ->assertSessionHasErrors('class_name');
    expect($this->mine->fresh()->class_name)->toBe('Basic 5');

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.students.update', [$this->school, $this->mine]), editForm($this->mine, ['class_name' => 'JSS 1']))
        ->assertSessionHasNoErrors();
    expect($this->mine->fresh()->class_name)->toBe('JSS 1');
});

test('a Class Teacher cannot edit a pupil outside their class or school', function () {
    $elsewhere = Student::factory()->create(['class_name' => 'Basic 5']);

    foreach ([$this->notMine, $elsewhere] as $student) {
        $this->actingAs($this->teacher, 'staff')
            ->put(route('staff.students.update', [$this->school, $student]), editForm($student, ['first_name' => 'X', 'class_name' => 'Basic 5']))
            ->assertForbidden();

        expect($student->fresh()->first_name)->not->toBe('X');
    }
});

test('there is no way for staff to delete a student', function () {
    // No delete route in the staff portal at all.
    expect(collect(app('router')->getRoutes())->filter(
        fn ($route) => str_starts_with((string) $route->getName(), 'staff.') && in_array('DELETE', $route->methods(), true)
            && str_contains($route->uri(), 'my-class'),
    ))->toBeEmpty();

    // The School Admin's delete routes refuse a member of staff.
    // (Refused, either as forbidden or sent to sign in: staff are not School
    // Admins and never get past the School Admin's front door.)
    $refused = fn ($response) => expect($response->status())->toBeIn([302, 403]);
    $refused($this->actingAs($this->teacher, 'staff')->delete(route('students.destroy', $this->mine)));
    $refused($this->actingAs($this->teacher, 'staff')->delete(route('students.bulk-destroy'), ['students' => [$this->mine->uuid]]));

    expect($this->mine->fresh())->not->toBeNull();

    // And the rule itself says no, whatever the staff role or assignment.
    expect(Gate::forUser($this->teacher)->allows('delete', $this->mine))->toBeFalse();
});

test('a School Admin can still delete, but only their own school\'s students', function () {
    expect(Gate::forUser($this->admin)->allows('delete', $this->mine))->toBeTrue();

    $this->actingAs($this->admin)->delete(route('students.destroy', $this->mine))->assertSessionHasNoErrors();
    expect($this->mine->fresh())->toBeNull();

    $elsewhere = Student::factory()->create();
    expect(Gate::forUser($this->admin)->allows('delete', $elsewhere))->toBeFalse();
    $this->actingAs($this->admin)->delete(route('students.destroy', $elsewhere))->assertForbidden();
    expect($elsewhere->fresh())->not->toBeNull();
});

test('My Class appears in the staff menu only for Class Teachers', function () {
    $menu = fn (Staff $staff) => collect(PortalNavigation::forStaff($staff)['categories'])->flatten(1)->pluck('route')->all();

    expect($menu($this->teacher))->toContain('staff.students.index');

    $subjectOnly = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
    expect($menu($subjectOnly))->not->toContain('staff.students.index');
});
