<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\SchoolFacility;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can add a facility', function () {
    $response = $this->actingAs($this->admin)->post(route('facilities.store'), [
        'name' => 'Science Laboratory',
        'category' => 'Academic',
        'description' => 'Fully equipped for practicals.',
    ]);

    $response->assertRedirect();

    $facility = SchoolFacility::where('name', 'Science Laboratory')->firstOrFail();
    expect($facility->school_id)->toBe($this->school->id);
});

test('a school admin only sees facilities from their own school', function () {
    SchoolFacility::factory()->create(['school_id' => $this->school->id, 'name' => 'Own Facility']);
    $otherSchool = School::factory()->create();
    SchoolFacility::factory()->create(['school_id' => $otherSchool->id, 'name' => 'Other Facility']);

    $this->actingAs($this->admin)
        ->get(route('facilities.index'))
        ->assertSee('Own Facility')
        ->assertDontSee('Other Facility');
});

test('a school admin can update a facility', function () {
    $facility = SchoolFacility::factory()->create(['school_id' => $this->school->id, 'name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->put(route('facilities.update', $facility), ['name' => 'New Name'])
        ->assertRedirect();

    expect($facility->fresh()->name)->toBe('New Name');
});

test('a school admin cannot modify another school\'s facility', function () {
    $otherSchool = School::factory()->create();
    $facility = SchoolFacility::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->put(route('facilities.update', $facility), ['name' => 'Hacked'])
        ->assertForbidden();
});

test('a school admin can delete a facility', function () {
    $facility = SchoolFacility::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('facilities.destroy', $facility))
        ->assertRedirect();

    expect(SchoolFacility::find($facility->id))->toBeNull();
});
