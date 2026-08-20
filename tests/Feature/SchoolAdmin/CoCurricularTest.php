<?php

use App\Enums\UserRole;
use App\Models\CoCurricularActivity;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can add a co-curricular activity', function () {
    $response = $this->actingAs($this->admin)->post(route('co-curricular.store'), [
        'name' => 'Debate Club',
        'category' => 'Academic',
        'schedule_text' => 'Every Friday, 2:00 PM',
    ]);

    $response->assertRedirect();

    $activity = CoCurricularActivity::where('name', 'Debate Club')->firstOrFail();
    expect($activity->school_id)->toBe($this->school->id);
});

test('a school admin only sees activities from their own school', function () {
    CoCurricularActivity::factory()->create(['school_id' => $this->school->id, 'name' => 'Own Club']);
    $otherSchool = School::factory()->create();
    CoCurricularActivity::factory()->create(['school_id' => $otherSchool->id, 'name' => 'Other Club']);

    $this->actingAs($this->admin)
        ->get(route('co-curricular.index'))
        ->assertSee('Own Club')
        ->assertDontSee('Other Club');
});

test('a school admin can update an activity', function () {
    $activity = CoCurricularActivity::factory()->create(['school_id' => $this->school->id, 'name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->put(route('co-curricular.update', $activity), ['name' => 'New Name'])
        ->assertRedirect();

    expect($activity->fresh()->name)->toBe('New Name');
});

test('a school admin cannot modify another school\'s activity', function () {
    $otherSchool = School::factory()->create();
    $activity = CoCurricularActivity::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->put(route('co-curricular.update', $activity), ['name' => 'Hacked'])
        ->assertForbidden();
});

test('a school admin can delete an activity', function () {
    $activity = CoCurricularActivity::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('co-curricular.destroy', $activity))
        ->assertRedirect();

    expect(CoCurricularActivity::find($activity->id))->toBeNull();
});
