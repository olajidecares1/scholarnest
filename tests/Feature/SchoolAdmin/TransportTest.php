<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\Student;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportVehicle;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('a school admin can add a vehicle', function () {
    $response = $this->actingAs($this->admin)->post(route('transport.store'), [
        'name' => 'Bus 1',
        'capacity' => 30,
    ]);

    $response->assertRedirect();

    $vehicle = TransportVehicle::where('name', 'Bus 1')->firstOrFail();
    expect($vehicle->school_id)->toBe($this->school->id);
});

test('a school admin only sees vehicles from their own school', function () {
    $otherSchool = School::factory()->create();
    TransportVehicle::factory()->create(['school_id' => $this->school->id, 'name' => 'Own Bus']);
    TransportVehicle::factory()->create(['school_id' => $otherSchool->id, 'name' => 'Other Bus']);

    $this->actingAs($this->admin)
        ->get(route('transport.index'))
        ->assertSee('Own Bus')
        ->assertDontSee('Other Bus');
});

test('a vehicle assigned to a route cannot be deleted', function () {
    $vehicle = TransportVehicle::factory()->create(['school_id' => $this->school->id]);
    TransportRoute::factory()->create(['school_id' => $this->school->id, 'transport_vehicle_id' => $vehicle->id]);

    $this->actingAs($this->admin)->delete(route('transport.destroy', $vehicle));

    expect(TransportVehicle::find($vehicle->id))->not->toBeNull();
});

test('a school admin can create a route and assign a vehicle', function () {
    $vehicle = TransportVehicle::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('transport.routes.store'), [
        'name' => 'Ikeja Route',
        'transport_vehicle_id' => $vehicle->id,
        'fee' => 5000,
    ])->assertRedirect();

    $route = TransportRoute::where('name', 'Ikeja Route')->firstOrFail();
    expect($route->transport_vehicle_id)->toBe($vehicle->id);
});

test('a school admin cannot assign another school\'s vehicle to a route', function () {
    $otherVehicle = TransportVehicle::factory()->create(['school_id' => School::factory()->create()->id]);

    $this->actingAs($this->admin)->post(route('transport.routes.store'), [
        'name' => 'Ikeja Route',
        'transport_vehicle_id' => $otherVehicle->id,
    ])->assertSessionHasErrors('transport_vehicle_id');
});

test('a school admin can assign a student to a route', function () {
    $route = TransportRoute::factory()->create(['school_id' => $this->school->id]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('transport.routes.students.store', $route), [
        'student_id' => $student->id,
        'pickup_point' => 'Main Gate',
    ])->assertRedirect();

    $assignment = TransportAssignment::where('student_id', $student->id)->firstOrFail();
    expect($assignment->transport_route_id)->toBe($route->id);
    expect($assignment->pickup_point)->toBe('Main Gate');
});

test('assigning a student to a new route moves them instead of duplicating', function () {
    $routeA = TransportRoute::factory()->create(['school_id' => $this->school->id]);
    $routeB = TransportRoute::factory()->create(['school_id' => $this->school->id]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('transport.routes.students.store', $routeA), ['student_id' => $student->id]);
    $this->actingAs($this->admin)->post(route('transport.routes.students.store', $routeB), ['student_id' => $student->id]);

    expect(TransportAssignment::where('student_id', $student->id)->count())->toBe(1);
    expect(TransportAssignment::where('student_id', $student->id)->first()->transport_route_id)->toBe($routeB->id);
});

test('a school admin can remove a student from a route', function () {
    $route = TransportRoute::factory()->create(['school_id' => $this->school->id]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $assignment = TransportAssignment::factory()->create(['transport_route_id' => $route->id, 'student_id' => $student->id]);

    $this->actingAs($this->admin)
        ->delete(route('transport.routes.assignments.destroy', $assignment))
        ->assertRedirect();

    expect(TransportAssignment::find($assignment->id))->toBeNull();
});

test('a school admin cannot remove an assignment from another school\'s route', function () {
    $otherSchool = School::factory()->create();
    $route = TransportRoute::factory()->create(['school_id' => $otherSchool->id]);
    $student = Student::factory()->create(['school_id' => $otherSchool->id]);
    $assignment = TransportAssignment::factory()->create(['transport_route_id' => $route->id, 'student_id' => $student->id]);

    $this->actingAs($this->admin)
        ->delete(route('transport.routes.assignments.destroy', $assignment))
        ->assertForbidden();
});
