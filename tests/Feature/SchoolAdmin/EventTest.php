<?php

use App\Enums\EventAudience;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can create an event', function () {
    $response = $this->actingAs($this->admin)->post(route('events.store'), [
        'title' => 'Inter-House Sports',
        'audience' => EventAudience::Everyone->value,
        'starts_at' => now()->addWeek()->toDateTimeString(),
    ]);

    $response->assertRedirect();

    $event = SchoolEvent::where('title', 'Inter-House Sports')->firstOrFail();
    expect($event->school_id)->toBe($this->school->id);
});

test('a school admin only sees events from their own school', function () {
    $otherSchool = School::factory()->create();
    SchoolEvent::factory()->create(['school_id' => $this->school->id, 'title' => 'Own Event', 'starts_at' => now()->addDay()]);
    SchoolEvent::factory()->create(['school_id' => $otherSchool->id, 'title' => 'Other Event', 'starts_at' => now()->addDay()]);

    $this->actingAs($this->admin)
        ->get(route('events.index'))
        ->assertSee('Own Event')
        ->assertDontSee('Other Event');
});

test('upcoming and past events are filtered correctly', function () {
    SchoolEvent::factory()->create(['school_id' => $this->school->id, 'title' => 'Future Event', 'starts_at' => now()->addWeek()]);
    SchoolEvent::factory()->create(['school_id' => $this->school->id, 'title' => 'Past Event', 'starts_at' => now()->subWeek()]);

    $this->actingAs($this->admin)
        ->get(route('events.index', ['when' => 'upcoming']))
        ->assertSee('Future Event')
        ->assertDontSee('Past Event');

    $this->actingAs($this->admin)
        ->get(route('events.index', ['when' => 'past']))
        ->assertSee('Past Event')
        ->assertDontSee('Future Event');
});

test('a school admin can update an event', function () {
    $event = SchoolEvent::factory()->create(['school_id' => $this->school->id, 'title' => 'Old Title']);

    $this->actingAs($this->admin)
        ->put(route('events.update', $event), [
            'title' => 'New Title',
            'audience' => EventAudience::Everyone->value,
            'starts_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertRedirect();

    expect($event->fresh()->title)->toBe('New Title');
});

test('a school admin cannot update another school\'s event', function () {
    $otherSchool = School::factory()->create();
    $event = SchoolEvent::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->put(route('events.update', $event), [
            'title' => 'Hacked',
            'audience' => EventAudience::Everyone->value,
            'starts_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertForbidden();
});

test('a school admin can delete an event', function () {
    $event = SchoolEvent::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('events.destroy', $event))
        ->assertRedirect();

    expect(SchoolEvent::find($event->id))->toBeNull();
});

test('the dashboard shows upcoming events', function () {
    SchoolEvent::factory()->create(['school_id' => $this->school->id, 'title' => 'Prize Giving Day', 'starts_at' => now()->addDays(2)]);

    $this->actingAs($this->admin)
        ->get(route('overview.index'))
        ->assertSee('Prize Giving Day');
});
