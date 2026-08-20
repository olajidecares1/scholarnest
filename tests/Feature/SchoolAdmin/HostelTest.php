<?php

use App\Enums\HostelGender;
use App\Enums\UserRole;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\School;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can add a hostel', function () {
    $response = $this->actingAs($this->admin)->post(route('hostels.store'), [
        'name' => 'Unity House',
        'gender' => HostelGender::Male->value,
    ]);

    $response->assertRedirect();

    $hostel = Hostel::where('name', 'Unity House')->firstOrFail();
    expect($hostel->school_id)->toBe($this->school->id);
});

test('a hostel with rooms cannot be deleted', function () {
    $hostel = Hostel::factory()->create(['school_id' => $this->school->id]);
    HostelRoom::factory()->create(['hostel_id' => $hostel->id]);

    $this->actingAs($this->admin)->delete(route('hostels.destroy', $hostel));

    expect(Hostel::find($hostel->id))->not->toBeNull();
});

test('a school admin can add a room to a hostel', function () {
    $hostel = Hostel::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('hostels.rooms.store', $hostel), [
        'room_number' => '12',
        'capacity' => 4,
    ])->assertRedirect();

    expect($hostel->rooms()->where('room_number', '12')->exists())->toBeTrue();
});

test('room numbers must be unique within a hostel', function () {
    $hostel = Hostel::factory()->create(['school_id' => $this->school->id]);
    HostelRoom::factory()->create(['hostel_id' => $hostel->id, 'room_number' => '12']);

    $this->actingAs($this->admin)->post(route('hostels.rooms.store', $hostel), [
        'room_number' => '12',
        'capacity' => 4,
    ])->assertSessionHasErrors('room_number');
});

test('a school admin can allocate a student to a room', function () {
    $hostel = Hostel::factory()->create(['school_id' => $this->school->id]);
    $room = HostelRoom::factory()->create(['hostel_id' => $hostel->id, 'capacity' => 4]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('hostels.students.store', $room), [
        'student_id' => $student->id,
    ])->assertRedirect();

    $allocation = HostelAllocation::where('student_id', $student->id)->firstOrFail();
    expect($allocation->hostel_room_id)->toBe($room->id);
});

test('a room at full capacity rejects new allocations', function () {
    $hostel = Hostel::factory()->create(['school_id' => $this->school->id]);
    $room = HostelRoom::factory()->create(['hostel_id' => $hostel->id, 'capacity' => 1]);
    $existingStudent = Student::factory()->create(['school_id' => $this->school->id]);
    HostelAllocation::factory()->create(['hostel_room_id' => $room->id, 'student_id' => $existingStudent->id]);

    $newStudent = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('hostels.students.store', $room), [
        'student_id' => $newStudent->id,
    ]);

    expect(HostelAllocation::where('student_id', $newStudent->id)->exists())->toBeFalse();
});

test('a school admin can vacate a student from a room', function () {
    $hostel = Hostel::factory()->create(['school_id' => $this->school->id]);
    $room = HostelRoom::factory()->create(['hostel_id' => $hostel->id]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $allocation = HostelAllocation::factory()->create(['hostel_room_id' => $room->id, 'student_id' => $student->id]);

    $this->actingAs($this->admin)
        ->delete(route('hostels.allocations.destroy', $allocation))
        ->assertRedirect();

    expect(HostelAllocation::find($allocation->id))->toBeNull();
});

test('a school admin cannot vacate a student from another school\'s room', function () {
    $otherSchool = School::factory()->create();
    $hostel = Hostel::factory()->create(['school_id' => $otherSchool->id]);
    $room = HostelRoom::factory()->create(['hostel_id' => $hostel->id]);
    $student = Student::factory()->create(['school_id' => $otherSchool->id]);
    $allocation = HostelAllocation::factory()->create(['hostel_room_id' => $room->id, 'student_id' => $student->id]);

    $this->actingAs($this->admin)
        ->delete(route('hostels.allocations.destroy', $allocation))
        ->assertForbidden();
});
