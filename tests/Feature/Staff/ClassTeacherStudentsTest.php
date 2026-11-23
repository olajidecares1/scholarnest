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
    // A school that numbers by hand on the School Admin's side: a Class
    // Teacher's pupils are numbered automatically all the same.
    $this->school = activateSchool(School::factory()->create([
        'auto_generate_admission_numbers' => false,
        'school_code' => 'BFA',
        'current_session' => '2026/2027',
    ]), PlanKey::Basic);
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'code' => 'PRY']);

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

    $added = Student::where('first_name', 'Tola')->sole();

    expect($added->school_id)->toBe($this->school->id)
        ->and($added->class_name)->toBe('Basic 5')
        ->and($added->fullName())->toBe('Tola Adewale')
        ->and(AuditLog::where('action', 'students.added_by_class_teacher')->exists())->toBeTrue();
});

test('the admission number is generated automatically, in the school\'s format', function () {
    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil())
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', fn ($status) => str_contains($status, 'BFA-2026/2027-PRY-001'));

    expect(Student::where('first_name', 'Tola')->sole()->admission_number)->toBe('BFA-2026/2027-PRY-001');
});

test('the Class Teacher and the School Admin share one sequence and one format', function () {
    $this->school->update(['auto_generate_admission_numbers' => true]);

    // Teacher, then School Admin, then teacher again.
    $this->actingAs($this->teacher, 'staff')->post(route('staff.students.store', $this->school), newPupil(['first_name' => 'One']));

    $this->actingAs($this->admin)->post(route('students.store'), [
        'first_name' => 'Two', 'last_name' => 'Admin', 'gender' => 'male', 'class_name' => 'Basic 6',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->teacher, 'staff')->post(route('staff.students.store', $this->school), newPupil(['first_name' => 'Three']));

    expect(Student::whereIn('first_name', ['One', 'Two', 'Three'])->orderBy('id')->pluck('admission_number')->all())
        ->toBe(['BFA-2026/2027-PRY-001', 'BFA-2026/2027-PRY-002', 'BFA-2026/2027-PRY-003']);
});

test('a generated number never repeats one somebody already holds', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'Basic 5', 'admission_number' => 'BFA-2026/2027-PRY-001']);

    $this->actingAs($this->teacher, 'staff')->post(route('staff.students.store', $this->school), newPupil())->assertSessionHasNoErrors();

    expect(Student::where('first_name', 'Tola')->sole()->admission_number)->toBe('BFA-2026/2027-PRY-002')
        ->and(Student::where('school_id', $this->school->id)->pluck('admission_number')->duplicates())->toBeEmpty();
});

test('a number freed by a deletion is reused, exactly as for the School Admin', function () {
    $this->actingAs($this->teacher, 'staff')->post(route('staff.students.store', $this->school), newPupil(['first_name' => 'Gone']));
    $gone = Student::where('first_name', 'Gone')->sole();
    expect($gone->admission_number)->toBe('BFA-2026/2027-PRY-001');

    $this->actingAs($this->admin)->delete(route('students.destroy', $gone))->assertSessionHasNoErrors();

    $this->actingAs($this->teacher, 'staff')->post(route('staff.students.store', $this->school), newPupil(['first_name' => 'Next']));

    expect(Student::where('first_name', 'Next')->sole()->admission_number)->toBe('BFA-2026/2027-PRY-001');
});

test('a Class Teacher cannot choose an admission number', function () {
    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil(['admission_number' => 'MY-OWN-001']))
        ->assertSessionHasErrors('admission_number');

    expect(Student::where('first_name', 'Tola')->exists())->toBeFalse();

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.students.index', $this->school))
        ->assertSee('The admission number is generated automatically')
        ->assertDontSee('name="admission_number"', false);
});

test('without a school code nothing is added, and the teacher is told why', function () {
    $this->school->update(['school_code' => null]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil())
        ->assertSessionHasErrors(['admission_number' => 'Admission numbers cannot be generated yet because your school has no school code. Ask your School Admin to set one under Settings, then add the student/pupil again.']);

    expect(Student::where('first_name', 'Tola')->exists())->toBeFalse();

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.students.index', $this->school))
        ->assertSee('does not have one. Ask your School Admin to set it under Settings.');
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

    $added = Student::where('first_name', 'Tola')->sole();

    expect($added->admission_number)->toBe('BFA-2026/2027-PRY-001')
        ->and($added->house)->toBe('Blue House')
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

// ---------------------------------------------------------------------------
// Adding a pupil who may already be registered
// ---------------------------------------------------------------------------

function staffConfirmToken($response): string
{
    $response->assertRedirect();
    preg_match('#/existing/([A-Za-z0-9]{40})$#', (string) $response->headers->get('Location'), $m);
    expect($m[1] ?? null)->not->toBeNull();

    return $m[1];
}

test('adding a pupil with a name already registered asks the teacher first and saves nothing', function () {
    $token = staffConfirmToken($this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil(['first_name' => 'Ade', 'last_name' => 'Bello'])));

    expect(Student::where('first_name', 'Ade')->count())->toBe(1);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.students.existing.show', [$this->school, $token]))
        ->assertOk()
        ->assertSee('Is this the same pupil?')
        ->assertSee('Yes, this is Ade Bello. Update their existing record.')
        ->assertSee($this->mine->admission_number)
        // The School Admin's student page is not offered to a teacher.
        ->assertDontSee(route('students.show', $this->mine), false);
});

test('a teacher can update a matching pupil in their own class, keeping the admission number', function () {
    $number = $this->mine->admission_number;

    $token = staffConfirmToken($this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil(['first_name' => 'Ade', 'last_name' => 'Bello', 'guardian_phone' => '08033334444'])));

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.existing.confirm', [$this->school, $token]), ['choice' => 'update:'.$this->mine->id])
        ->assertRedirect()
        ->assertSessionHas('status', fn ($status) => str_contains($status, "Admission number {$number} is unchanged"));

    expect(Student::where('first_name', 'Ade')->count())->toBe(1)
        ->and($this->mine->fresh()->admission_number)->toBe($number)
        ->and($this->mine->fresh()->guardian_phone)->toBe('08033334444')
        ->and(AuditLog::where('action', 'students.re-registered')->exists())->toBeTrue();
});

test('a matching pupil in another class is shown but cannot be updated by the teacher', function () {
    $token = staffConfirmToken($this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil(['first_name' => 'Kemi', 'last_name' => 'Ojo'])));

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.students.existing.show', [$this->school, $token]))
        ->assertOk()
        ->assertSee('Kemi Ojo is already registered.')
        ->assertSee('This pupil is in Basic 6, which is not one of your classes. Only the School Admin can move them.');

    // Even a hand-made request cannot move the pupil into the teacher's class.
    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.existing.confirm', [$this->school, $token]), ['choice' => 'update:'.$this->notMine->id])
        ->assertSessionHasErrors('choice');

    expect($this->notMine->fresh()->class_name)->toBe('Basic 6');
});

test('the teacher can say it is a different child and add them as new', function () {
    $token = staffConfirmToken($this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil(['first_name' => 'Kemi', 'last_name' => 'Ojo'])));

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.existing.confirm', [$this->school, $token]), ['choice' => 'new'])
        ->assertRedirect();

    // The new pupil is numbered by the School Admin's generator.
    $added = Student::where('first_name', 'Kemi')->whereKeyNot($this->notMine->id)->sole();

    expect($added->class_name)->toBe('Basic 5')
        ->and($added->admission_number)->toBe('BFA-2026/2027-PRY-001')
        ->and($this->notMine->fresh()->class_name)->toBe('Basic 6');
});

test('a teacher\'s pending question belongs to that teacher only', function () {
    $token = staffConfirmToken($this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil(['first_name' => 'Ade', 'last_name' => 'Bello'])));

    $colleague = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
    TeacherAssignment::factory()->create(['school_id' => $this->school->id, 'staff_id' => $colleague->id, 'type' => TeacherAssignmentType::ClassTeacher, 'class_name' => 'Basic 5']);

    $this->actingAs($colleague, 'staff')
        ->post(route('staff.students.existing.confirm', [$this->school, $token]), ['choice' => 'new'])
        ->assertRedirect(route('staff.students.index', ['school' => $this->school]));

    expect(Student::where('first_name', 'Ade')->count())->toBe(1);
});

test('cancelling on the teacher\'s side saves nothing', function () {
    $token = staffConfirmToken($this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.store', $this->school), newPupil(['first_name' => 'Ade', 'last_name' => 'Bello'])));

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.students.existing.cancel', [$this->school, $token]))
        ->assertRedirect();

    expect(Student::where('first_name', 'Ade')->count())->toBe(1)
        ->and(Student::where('admission_number', 'like', 'BFA-%')->exists())->toBeFalse();
});
