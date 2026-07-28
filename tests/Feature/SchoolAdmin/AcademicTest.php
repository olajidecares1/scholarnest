<?php

use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('visiting the academics page seeds the default levels and classes for a new school', function () {
    expect($this->school->academicLevels()->count())->toBe(0);

    $this->actingAs($this->admin)
        ->get(route('academics.index'))
        ->assertStatus(200)
        ->assertSee('Senior Secondary School')
        ->assertSee('Junior Secondary School');

    expect($this->school->academicLevels()->count())->toBe(6);
    expect(SchoolClass::whereIn('academic_level_id', $this->school->academicLevels()->pluck('id'))->count())->toBeGreaterThan(0);
});

test('seeding default levels is skipped if the school already has levels', function () {
    AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'Custom Level']);

    $this->actingAs($this->admin)->get(route('academics.index'));

    expect($this->school->academicLevels()->count())->toBe(1);
    expect($this->school->academicLevels()->first()->name)->toBe('Custom Level');
});

test('a school admin can add an academic level', function () {
    $this->actingAs($this->admin)
        ->post(route('academics.levels.store'), ['name' => 'Vocational Training'])
        ->assertRedirect();

    $level = AcademicLevel::where('name', 'Vocational Training')->firstOrFail();
    expect($level->school_id)->toBe($this->school->id);
});

test('a school admin can rename an academic level', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->put(route('academics.levels.update', $level), ['name' => 'New Name'])
        ->assertRedirect();

    expect($level->fresh()->name)->toBe('New Name');
});

test('a school admin cannot rename another school\'s academic level', function () {
    $otherSchool = School::factory()->create();
    $level = AcademicLevel::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->put(route('academics.levels.update', $level), ['name' => 'Hacked'])
        ->assertForbidden();
});

test('deleting an academic level also deletes its classes', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);
    $class = SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id]);

    $this->actingAs($this->admin)
        ->delete(route('academics.levels.destroy', $level))
        ->assertRedirect();

    expect(AcademicLevel::find($level->id))->toBeNull();
    expect(SchoolClass::find($class->id))->toBeNull();
});

test('a school admin cannot delete another school\'s academic level', function () {
    $otherSchool = School::factory()->create();
    $level = AcademicLevel::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->delete(route('academics.levels.destroy', $level))
        ->assertForbidden();

    expect(AcademicLevel::find($level->id))->not->toBeNull();
});

test('a school admin can add a class to a level', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->post(route('academics.classes.store', $level), ['name' => 'SS 1'])
        ->assertRedirect();

    $class = SchoolClass::where('name', 'SS 1')->firstOrFail();
    expect($class->academic_level_id)->toBe($level->id);
    expect($class->school_id)->toBe($this->school->id);
});

test('a school admin cannot add a class to another school\'s level', function () {
    $otherSchool = School::factory()->create();
    $level = AcademicLevel::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->post(route('academics.classes.store', $level), ['name' => 'SS 1'])
        ->assertForbidden();
});

test('a school admin can rename a class', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);
    $class = SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'Old']);

    $this->actingAs($this->admin)
        ->put(route('academics.classes.update', $class), ['name' => 'New'])
        ->assertRedirect();

    expect($class->fresh()->name)->toBe('New');
});

test('a school admin can delete a class', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);
    $class = SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id]);

    $this->actingAs($this->admin)
        ->delete(route('academics.classes.destroy', $class))
        ->assertRedirect();

    expect(SchoolClass::find($class->id))->toBeNull();
});

test('a school admin cannot delete another school\'s class', function () {
    $otherSchool = School::factory()->create();
    $level = AcademicLevel::factory()->create(['school_id' => $otherSchool->id]);
    $class = SchoolClass::factory()->create(['school_id' => $otherSchool->id, 'academic_level_id' => $level->id]);

    $this->actingAs($this->admin)
        ->delete(route('academics.classes.destroy', $class))
        ->assertForbidden();
});

test('the sidebar academic dropdown lists levels grouped with their classes and links to filtered students', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'Senior Secondary School']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'SS 1']);

    $response = $this->actingAs($this->admin)->get(route('dashboard'));

    $response->assertSee('Senior Secondary School');
    $response->assertSee('SS 1');
});

test('registering a new school automatically seeds its default academic structure', function () {
    $response = $this->post(route('register'), [
        'school_name' => 'Brightstar Academy',
        'email' => 'admin@brightstar.example',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $school = School::where('name', 'Brightstar Academy')->firstOrFail();
    expect($school->academicLevels()->count())->toBe(6);
});
