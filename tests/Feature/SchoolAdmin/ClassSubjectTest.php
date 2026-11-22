<?php

use App\Enums\SubjectCategory;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use App\Services\DefaultClassSubjects;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

/**
 * The subjects a class offers beyond its locked defaults: the ones the
 * School Admin chose.
 */
function ownSubjectsOf(School $school, string $className): array
{
    return $school->offeredSubjectsFor($className)
        ->reject(fn ($name) => in_array($name, DefaultClassSubjects::namesForGroup(DefaultClassSubjects::groupFor($className)), true))
        ->values()
        ->all();
}

test('the catalogue seeder produces a real subject list grouped by category', function () {
    $this->artisan('db:seed', ['--class' => 'SubjectSeeder', '--force' => true]);

    expect(Subject::count())->toBeGreaterThan(30);
    expect(Subject::where('name', 'Mathematics')->value('category'))->toBe(SubjectCategory::General);
    expect(Subject::where('name', 'Physics')->value('category'))->toBe(SubjectCategory::Science);
    expect(Subject::where('name', 'Commerce')->value('category'))->toBe(SubjectCategory::Commercial);
});

test('a school admin can configure the subjects a class offers', function () {
    $drawing = Subject::factory()->create(['name' => 'Technical Drawing', 'category' => SubjectCategory::Science]);
    $visual = Subject::factory()->create(['name' => 'Visual Arts', 'category' => SubjectCategory::Arts]);

    $this->actingAs($this->admin)->post(route('class-subjects.store'), [
        'class_name' => 'SS 1 Science',
        'subject_ids' => [$drawing->id, $visual->id],
    ])->assertRedirect();

    expect(ownSubjectsOf($this->school, 'SS 1 Science'))->toEqualCanonicalizing(['Technical Drawing', 'Visual Arts']);
});

test('saving subjects for a class replaces the previous set rather than appending', function () {
    $drawing = Subject::factory()->create(['name' => 'Technical Drawing']);
    $visual = Subject::factory()->create(['name' => 'Visual Arts']);
    SubjectOffering::factory()->create(['school_id' => $this->school->id, 'class_name' => 'SS 1 Science', 'subject_id' => $drawing->id]);

    $this->actingAs($this->admin)->post(route('class-subjects.store'), [
        'class_name' => 'SS 1 Science',
        'subject_ids' => [$visual->id],
    ]);

    expect(ownSubjectsOf($this->school, 'SS 1 Science'))->toBe(['Visual Arts']);
});

test('a school admin can add a custom subject not in the catalogue', function () {
    $this->actingAs($this->admin)->post(route('class-subjects.store'), [
        'class_name' => 'SS 1 Science',
        'custom_subject' => 'Robotics',
    ])->assertRedirect();

    expect(Subject::where('name', 'Robotics')->exists())->toBeTrue();
    expect(ownSubjectsOf($this->school, 'SS 1 Science'))->toBe(['Robotics']);
});

test('a class with no configured offerings returns an empty collection', function () {
    expect($this->school->offeredSubjectsFor('JSS 1')->isEmpty())->toBeTrue();
});

test('the class subjects page shows the catalogue grouped by category', function () {
    Subject::factory()->create(['name' => 'Biology', 'category' => SubjectCategory::Science]);
    Subject::factory()->create(['name' => 'Commerce', 'category' => SubjectCategory::Commercial]);

    $this->actingAs($this->admin)
        ->get(route('class-subjects.index'))
        ->assertOk()
        ->assertSee('Science')
        ->assertSee('Biology')
        ->assertSee('Commerce');
});
