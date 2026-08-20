<?php

use App\Enums\CbtTestStatus;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\CbtTest;
use App\Models\CbtTestQuestion;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can list all teacher-created CBT tests', function () {
    $teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $teacher->id, 'title' => 'My CBT Test']);

    $this->actingAs($this->admin)
        ->get(route('cbt-tests.index'))
        ->assertStatus(200)
        ->assertSee('My CBT Test');
});

test('a school admin can view a single test\'s detail', function () {
    $teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $teacher->id]);

    $this->actingAs($this->admin)
        ->get(route('cbt-tests.show', $test))
        ->assertStatus(200)
        ->assertSee($test->title);
});

test('a school admin cannot view another school\'s CBT test', function () {
    $otherSchool = School::factory()->create();
    $teacher = Staff::factory()->create(['school_id' => $otherSchool->id, 'role' => StaffRole::Teacher]);
    $test = CbtTest::factory()->create(['school_id' => $otherSchool->id, 'staff_id' => $teacher->id]);

    $this->actingAs($this->admin)
        ->get(route('cbt-tests.show', $test))
        ->assertForbidden();
});

test('a school admin can edit a teacher\'s test details', function () {
    $teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $teacher->id, 'title' => 'Old Title']);

    $this->actingAs($this->admin)
        ->put(route('cbt-tests.update', $test), [
            'title' => 'New Title',
            'subject' => $test->subject,
            'class_name' => $test->class_name,
            'duration_minutes' => $test->duration_minutes,
            'pass_mark' => $test->pass_mark,
        ])
        ->assertRedirect();

    expect($test->fresh()->title)->toBe('New Title');
});

test('a school admin can lock, publish, and archive a teacher\'s test', function () {
    $teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    $test = CbtTest::factory()->create(['school_id' => $this->school->id, 'staff_id' => $teacher->id]);
    CbtTestQuestion::factory()->create(['cbt_test_id' => $test->id]);

    $this->actingAs($this->admin)
        ->post(route('cbt-tests.status', $test), ['status' => 'published'])
        ->assertRedirect();

    expect($test->fresh()->status)->toBe(CbtTestStatus::Published);

    $this->actingAs($this->admin)
        ->post(route('cbt-tests.status', $test), ['status' => 'archived'])
        ->assertRedirect();

    expect($test->fresh()->status)->toBe(CbtTestStatus::Archived);
});

test('a school admin cannot edit or change status of another school\'s test', function () {
    $otherSchool = School::factory()->create();
    $teacher = Staff::factory()->create(['school_id' => $otherSchool->id, 'role' => StaffRole::Teacher]);
    $test = CbtTest::factory()->create(['school_id' => $otherSchool->id, 'staff_id' => $teacher->id]);

    $this->actingAs($this->admin)
        ->put(route('cbt-tests.update', $test), [
            'title' => 'Hacked',
            'subject' => $test->subject,
            'class_name' => $test->class_name,
            'duration_minutes' => $test->duration_minutes,
            'pass_mark' => $test->pass_mark,
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->post(route('cbt-tests.status', $test), ['status' => 'published'])
        ->assertForbidden();
});
