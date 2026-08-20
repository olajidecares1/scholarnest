<?php

use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\TeacherAssignment;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
    $this->teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
});

test('a school admin can assign a teacher as class teacher of a class', function () {
    $this->actingAs($this->admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $this->teacher->uuid,
        'type' => TeacherAssignmentType::ClassTeacher->value,
        'class_name' => 'JSS 1',
    ])->assertRedirect();

    $assignment = TeacherAssignment::where('staff_id', $this->teacher->id)->firstOrFail();
    expect($assignment->type)->toBe(TeacherAssignmentType::ClassTeacher);
    expect($assignment->class_name)->toBe('JSS 1');
    expect($assignment->subject)->toBeNull();
});

test('a teacher can be class teacher of multiple classes', function () {
    $this->actingAs($this->admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $this->teacher->uuid, 'type' => TeacherAssignmentType::ClassTeacher->value, 'class_name' => 'Primary 4',
    ]);
    $this->actingAs($this->admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $this->teacher->uuid, 'type' => TeacherAssignmentType::ClassTeacher->value, 'class_name' => 'JSS 1',
    ]);

    expect($this->teacher->classesAsClassTeacher()->all())->toEqualCanonicalizing(['Primary 4', 'JSS 1']);
});

test('assigning a class teacher replaces whoever previously held that class', function () {
    $previous = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    TeacherAssignment::factory()->create(['school_id' => $this->school->id, 'staff_id' => $previous->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $this->teacher->uuid, 'type' => TeacherAssignmentType::ClassTeacher->value, 'class_name' => 'JSS 1',
    ]);

    expect($this->teacher->classesAsClassTeacher()->all())->toBe(['JSS 1']);
    expect($previous->fresh()->classesAsClassTeacher()->all())->toBe([]);
    expect(TeacherAssignment::where('school_id', $this->school->id)->where('class_name', 'JSS 1')->where('type', TeacherAssignmentType::ClassTeacher)->count())->toBe(1);
});

test('a school admin can assign a teacher as subject teacher of a class', function () {
    $this->actingAs($this->admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $this->teacher->uuid,
        'type' => TeacherAssignmentType::SubjectTeacher->value,
        'class_name' => 'JSS 1',
        'subject' => 'Mathematics',
    ])->assertRedirect();

    $assignment = TeacherAssignment::where('staff_id', $this->teacher->id)->firstOrFail();
    expect($assignment->type)->toBe(TeacherAssignmentType::SubjectTeacher);
    expect($assignment->subject)->toBe('Mathematics');
});

test('a subject requires a subject name', function () {
    $this->actingAs($this->admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $this->teacher->uuid,
        'type' => TeacherAssignmentType::SubjectTeacher->value,
        'class_name' => 'JSS 1',
    ])->assertSessionHasErrors('subject');
});

test('a teacher can teach the same subject across multiple classes', function () {
    foreach (['JSS 1', 'JSS 2', 'JSS 3'] as $class) {
        $this->actingAs($this->admin)->post(route('teacher-assignments.store'), [
            'staff_uuid' => $this->teacher->uuid, 'type' => TeacherAssignmentType::SubjectTeacher->value, 'class_name' => $class, 'subject' => 'Mathematics',
        ]);
    }

    expect($this->teacher->subjectAssignments())->toHaveCount(3);
});

test('multiple teachers can be assigned to the same class and subject', function () {
    $otherTeacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);

    $this->actingAs($this->admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $this->teacher->uuid, 'type' => TeacherAssignmentType::SubjectTeacher->value, 'class_name' => 'JSS 1', 'subject' => 'Mathematics',
    ]);
    $this->actingAs($this->admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $otherTeacher->uuid, 'type' => TeacherAssignmentType::SubjectTeacher->value, 'class_name' => 'JSS 1', 'subject' => 'Mathematics',
    ]);

    expect(TeacherAssignment::where('school_id', $this->school->id)->where('class_name', 'JSS 1')->where('subject', 'Mathematics')->count())->toBe(2);
});

test('re-submitting an identical subject-teacher assignment does not create a duplicate', function () {
    $payload = [
        'staff_uuid' => $this->teacher->uuid, 'type' => TeacherAssignmentType::SubjectTeacher->value, 'class_name' => 'JSS 1', 'subject' => 'Mathematics',
    ];

    $this->actingAs($this->admin)->post(route('teacher-assignments.store'), $payload);
    $this->actingAs($this->admin)->post(route('teacher-assignments.store'), $payload);

    expect(TeacherAssignment::where('staff_id', $this->teacher->id)->count())->toBe(1);
});

test('a school admin can filter assignments by teacher, class, and subject', function () {
    TeacherAssignment::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id, 'class_name' => 'JSS 1']);
    $otherTeacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'first_name' => 'Zainab']);
    TeacherAssignment::factory()->subjectTeacher('Physics')->create(['school_id' => $this->school->id, 'staff_id' => $otherTeacher->id, 'class_name' => 'SSS 1']);

    $this->actingAs($this->admin)
        ->get(route('teacher-assignments.index', ['teacher' => $this->teacher->uuid]))
        ->assertOk()
        ->assertDontSee('Remove this assignment for Zainab', false);

    $this->actingAs($this->admin)
        ->get(route('teacher-assignments.index', ['subject' => 'Physics']))
        ->assertOk()
        ->assertSee('Zainab');
});

test('a school admin can remove an assignment', function () {
    $assignment = TeacherAssignment::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id]);

    $this->actingAs($this->admin)
        ->delete(route('teacher-assignments.destroy', $assignment))
        ->assertRedirect();

    expect(TeacherAssignment::find($assignment->id))->toBeNull();
});

test('a school admin cannot remove another school\'s assignment', function () {
    $otherSchool = School::factory()->create();
    $otherTeacher = Staff::factory()->create(['school_id' => $otherSchool->id, 'role' => StaffRole::Teacher]);
    $assignment = TeacherAssignment::factory()->create(['school_id' => $otherSchool->id, 'staff_id' => $otherTeacher->id]);

    $this->actingAs($this->admin)
        ->delete(route('teacher-assignments.destroy', $assignment))
        ->assertForbidden();

    expect(TeacherAssignment::find($assignment->id))->not->toBeNull();
});

test('the assignments page carries a class-to-offered-subjects map for configured classes', function () {
    $physics = Subject::factory()->create(['name' => 'Physics']);
    SubjectOffering::factory()->create(['school_id' => $this->school->id, 'class_name' => 'SS 1 Science', 'subject_id' => $physics->id]);

    $this->actingAs($this->admin)
        ->get(route('teacher-assignments.index'))
        ->assertOk()
        ->assertSee('SS 1 Science')
        ->assertSee('Physics');
});

test('a school admin cannot assign a staff member from another school', function () {
    $otherSchool = School::factory()->create();
    $otherTeacher = Staff::factory()->create(['school_id' => $otherSchool->id, 'role' => StaffRole::Teacher]);

    $this->actingAs($this->admin)->post(route('teacher-assignments.store'), [
        'staff_uuid' => $otherTeacher->uuid, 'type' => TeacherAssignmentType::ClassTeacher->value, 'class_name' => 'JSS 1',
    ])->assertSessionHasErrors('staff_uuid');
});
