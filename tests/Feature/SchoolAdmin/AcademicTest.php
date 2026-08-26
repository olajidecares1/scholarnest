<?php

use App\Enums\ClassStream;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
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

test('the sidebar links straight to the academics page instead of a dropdown', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'Senior Secondary School']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'SS 1']);

    $response = $this->actingAs($this->admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('SS 1');
    $response->assertSee(route('academics.index'), false);
});

test('a freshly seeded school gets SSS split into science, art, and commercial streams', function () {
    $this->actingAs($this->admin)->get(route('academics.index'))->assertOk();

    $sss = $this->school->academicLevels()->where('name', 'SSS')->firstOrFail();
    $classes = $sss->classes()->orderBy('sort_order')->get();

    expect($classes)->toHaveCount(9);
    expect($classes->where('stream', ClassStream::Science)->pluck('name')->all())
        ->toBe(['SS 1 Science', 'SS 2 Science', 'SS 3 Science']);
    expect($classes->where('stream', ClassStream::Art)->pluck('name')->all())
        ->toBe(['SS 1 Art', 'SS 2 Art', 'SS 3 Art']);
    expect($classes->where('stream', ClassStream::Commercial)->pluck('name')->all())
        ->toBe(['SS 1 Commercial', 'SS 2 Commercial', 'SS 3 Commercial']);

    $primary = $this->school->academicLevels()->where('name', 'Lower Primary')->firstOrFail();
    expect($primary->classes()->whereNotNull('stream')->count())->toBe(0);
});

test('a school admin can add a class with a stream', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'SSS']);

    $this->actingAs($this->admin)
        ->post(route('academics.classes.store', $level), ['name' => 'SS 1 Science', 'stream' => ClassStream::Science->value])
        ->assertRedirect();

    $class = SchoolClass::where('name', 'SS 1 Science')->firstOrFail();
    expect($class->stream)->toBe(ClassStream::Science);
});

test('a class added without a stream has no stream', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->post(route('academics.classes.store', $level), ['name' => 'Primary 1'])
        ->assertRedirect();

    $class = SchoolClass::where('name', 'Primary 1')->firstOrFail();
    expect($class->stream)->toBeNull();
});

test('an invalid stream value is rejected', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->post(route('academics.classes.store', $level), ['name' => 'SS 1', 'stream' => 'not-a-real-stream'])
        ->assertSessionHasErrors('stream');
});

test('a school admin can update a class\'s stream', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);
    $class = SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'stream' => ClassStream::Science]);

    $this->actingAs($this->admin)
        ->put(route('academics.classes.update', $class), ['name' => $class->name, 'stream' => ClassStream::Art->value])
        ->assertRedirect();

    expect($class->fresh()->stream)->toBe(ClassStream::Art);
});

test('registering a new school automatically seeds its default academic structure', function () {
    $response = $this->post(route('register'), [
        'school_name' => 'Brightstar Academy',
        'email' => 'admin@brightstar.example',
        'phone' => '+234 801 234 5678',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $school = School::where('name', 'Brightstar Academy')->firstOrFail();
    expect($school->academicLevels()->count())->toBe(6);
});
