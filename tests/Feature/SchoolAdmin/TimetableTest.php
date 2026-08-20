<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\TimetableEntry;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can add a timetable entry', function () {
    $response = $this->actingAs($this->admin)->post(route('timetable.store'), [
        'class_name' => 'JSS 1',
        'day_of_week' => 1,
        'start_time' => '08:00',
        'end_time' => '08:45',
        'subject' => 'Mathematics',
        'room' => 'Room 4',
    ]);

    $response->assertRedirect();

    $entry = TimetableEntry::where('subject', 'Mathematics')->firstOrFail();
    expect($entry->school_id)->toBe($this->school->id);
    expect($entry->class_name)->toBe('JSS 1');
});

test('a school admin only sees timetable entries from their own school', function () {
    TimetableEntry::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'day_of_week' => 1, 'subject' => 'Own Subject']);
    $otherSchool = School::factory()->create();
    TimetableEntry::factory()->create(['school_id' => $otherSchool->id, 'class_name' => 'JSS 1', 'day_of_week' => 1, 'subject' => 'Other Subject']);

    $this->actingAs($this->admin)
        ->get(route('timetable.index', ['class' => 'JSS 1']))
        ->assertSee('Own Subject')
        ->assertDontSee('Other Subject');
});

test('a school admin can update a timetable entry', function () {
    $entry = TimetableEntry::factory()->create(['school_id' => $this->school->id, 'subject' => 'Old Subject']);

    $this->actingAs($this->admin)
        ->put(route('timetable.update', $entry), [
            'class_name' => $entry->class_name,
            'day_of_week' => $entry->day_of_week,
            'start_time' => '09:00',
            'end_time' => '09:45',
            'subject' => 'New Subject',
        ])
        ->assertRedirect();

    expect($entry->fresh()->subject)->toBe('New Subject');
});

test('a school admin cannot modify another school\'s timetable entry', function () {
    $otherSchool = School::factory()->create();
    $entry = TimetableEntry::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->put(route('timetable.update', $entry), [
            'class_name' => $entry->class_name,
            'day_of_week' => $entry->day_of_week,
            'start_time' => '09:00',
            'end_time' => '09:45',
            'subject' => 'Hacked',
        ])
        ->assertForbidden();
});

test('a school admin can delete a timetable entry', function () {
    $entry = TimetableEntry::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('timetable.destroy', $entry))
        ->assertRedirect();

    expect(TimetableEntry::find($entry->id))->toBeNull();
});
