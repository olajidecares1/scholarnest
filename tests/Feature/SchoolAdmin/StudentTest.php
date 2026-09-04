<?php

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can view their students list', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);

    $this->actingAs($this->admin)
        ->get(route('students.index'))
        ->assertStatus(200)
        ->assertSee('Amaka Obi');
});

test('a school admin only sees students from their own school', function () {
    $otherSchool = School::factory()->create();
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);
    Student::factory()->create(['school_id' => $otherSchool->id, 'first_name' => 'Tunde', 'last_name' => 'Bello']);

    $this->actingAs($this->admin)
        ->get(route('students.index'))
        ->assertSee('Amaka Obi')
        ->assertDontSee('Tunde Bello');
});

test('a school admin can add a student', function () {
    $response = $this->actingAs($this->admin)->post(route('students.store'), [
        'admission_number' => 'STU-0001',
        'first_name' => 'Chinedu',
        'last_name' => 'Eze',
        'gender' => Gender::Male->value,
        'class_name' => 'JSS 1',
        'guardian_name' => 'Ngozi Eze',
        'guardian_phone' => '08012345678',
    ]);

    $response->assertRedirect();

    $student = Student::where('admission_number', 'STU-0001')->firstOrFail();
    expect($student->school_id)->toBe($this->school->id);
    expect($student->fullName())->toBe('Chinedu Eze');
    expect($student->is_active)->toBeTrue();
});

test('a school admin can set a student\'s house', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)
        ->put(route('students.update', $student), [
            'admission_number' => $student->admission_number,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'gender' => $student->gender->value,
            'class_name' => $student->class_name,
            'house' => 'Blue House',
        ])
        ->assertRedirect();

    expect($student->fresh()->house)->toBe('Blue House');
});

test('admission numbers must be unique within a school', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'admission_number' => 'STU-0001']);

    $this->actingAs($this->admin)
        ->post(route('students.store'), [
            'admission_number' => 'STU-0001',
            'first_name' => 'Second',
            'last_name' => 'Student',
            'gender' => Gender::Female->value,
        ])
        ->assertSessionHasErrors('admission_number');
});

test('two different schools can reuse the same admission number', function () {
    $otherSchool = School::factory()->create();
    Student::factory()->create(['school_id' => $otherSchool->id, 'admission_number' => 'STU-0001']);

    $this->actingAs($this->admin)
        ->post(route('students.store'), [
            'admission_number' => 'STU-0001',
            'first_name' => 'Chinedu',
            'last_name' => 'Eze',
            'gender' => Gender::Male->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

test('a school admin can upload a student photo', function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->actingAs($this->admin)->post(route('students.store'), [
        'admission_number' => 'STU-0002',
        'first_name' => 'Bisi',
        'last_name' => 'Ade',
        'gender' => Gender::Female->value,
        'photo' => UploadedFile::fake()->image('bisi.jpg', 400, 400),
    ]);

    $student = Student::where('admission_number', 'STU-0002')->firstOrFail();
    expect($student->photo_path)->not->toBeNull();
    Storage::disk('local')->assertExists($student->photo_path);
});

test('a school admin can view a student profile', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);

    $this->actingAs($this->admin)
        ->get(route('students.show', $student))
        ->assertStatus(200)
        ->assertSee('Amaka Obi');
});

test('a school admin cannot view another school\'s student', function () {
    $otherSchool = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->get(route('students.show', $student))
        ->assertForbidden();
});

test('a school admin can update a student', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)
        ->put(route('students.update', $student), [
            'admission_number' => $student->admission_number,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'gender' => $student->gender->value,
            'class_name' => 'JSS 2',
        ])
        ->assertRedirect();

    expect($student->fresh()->class_name)->toBe('JSS 2');
});

test('a school admin cannot update another school\'s student', function () {
    $otherSchool = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->put(route('students.update', $student), [
            'admission_number' => $student->admission_number,
            'first_name' => 'Hacked',
            'last_name' => 'Name',
            'gender' => $student->gender->value,
        ])
        ->assertForbidden();
});

test('a school admin can toggle a student\'s active status', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);

    $this->actingAs($this->admin)
        ->post(route('students.toggle-active', $student))
        ->assertRedirect();

    expect($student->fresh()->is_active)->toBeFalse();
});

test('a school admin can delete a student', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('students.destroy', $student))
        ->assertRedirect();

    expect(Student::find($student->id))->toBeNull();
});

test('a school admin cannot delete another school\'s student', function () {
    $otherSchool = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->delete(route('students.destroy', $student))
        ->assertForbidden();

    expect(Student::find($student->id))->not->toBeNull();
});

test('students can be searched by name or admission number', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi', 'admission_number' => 'STU-0001']);
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Tunde', 'last_name' => 'Bello', 'admission_number' => 'STU-0002']);

    $this->actingAs($this->admin)
        ->get(route('students.index', ['search' => 'Amaka']))
        ->assertSee('Amaka Obi')
        ->assertDontSee('Tunde Bello');
});

test('students can be filtered by class', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi', 'class_name' => 'JSS 1']);
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Tunde', 'last_name' => 'Bello', 'class_name' => 'SS 2']);

    $this->actingAs($this->admin)
        ->get(route('students.index', ['class' => 'JSS 1']))
        ->assertSee('Amaka Obi')
        ->assertDontSee('Tunde Bello');
});

test('the dashboard shows the real total students count', function () {
    Student::factory()->count(3)->create(['school_id' => $this->school->id, 'is_active' => true]);
    Student::factory()->create(['school_id' => $this->school->id, 'is_active' => false]);

    $response = $this->actingAs($this->admin)->get(route('overview.index'));

    $response->assertSeeInOrder(['Total Students', '4']);
});
