<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a query shorter than 2 characters returns empty results', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka']);

    $this->actingAs($this->admin)
        ->getJson(route('search.query', ['q' => 'a']))
        ->assertOk()
        ->assertJson(['students' => [], 'staff' => [], 'classes' => []]);
});

test('a school admin can find a student by name or admission number', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi', 'admission_number' => 'ADM-0001']);

    $this->actingAs($this->admin)
        ->getJson(route('search.query', ['q' => 'Amaka']))
        ->assertOk()
        ->assertJsonFragment(['title' => 'Amaka Obi', 'subtitle' => 'ADM-0001', 'url' => route('students.show', $student)]);
});

test('a school admin can find a staff member by name or staff number', function () {
    $member = Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Tunde', 'last_name' => 'Bello', 'staff_number' => 'STF-0001']);

    $this->actingAs($this->admin)
        ->getJson(route('search.query', ['q' => 'Tunde']))
        ->assertOk()
        ->assertJsonFragment(['title' => 'Tunde Bello', 'subtitle' => 'STF-0001', 'url' => route('staff.show', $member)]);
});

test('a school admin can find a class by name', function () {
    $class = SchoolClass::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS 1']);

    $this->actingAs($this->admin)
        ->getJson(route('search.query', ['q' => 'JSS 1']))
        ->assertOk()
        ->assertJsonFragment(['title' => $class->name, 'subtitle' => 'Class']);
});

test('search results are scoped to the current school', function () {
    $otherSchool = School::factory()->create();
    Student::factory()->create(['school_id' => $otherSchool->id, 'first_name' => 'Foreign', 'last_name' => 'Student']);

    $response = $this->actingAs($this->admin)
        ->getJson(route('search.query', ['q' => 'Foreign']))
        ->assertOk();

    expect($response->json('students'))->toBeEmpty();
});
