<?php

use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Staff;
use App\Models\StaffAttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function checkInSettings(array $overrides = []): array
{
    return array_merge([
        'check_in_enabled' => '1',
        'student_attendance_mode' => AttendanceMode::Qr->value,
        'latitude' => '6.5244',
        'longitude' => '3.3792',
        'check_in_radius_metres' => '150',
    ], $overrides);
}

test('switching check-in on issues the school a poster, once', function () {
    $this->actingAs($this->admin)
        ->put(route('attendance.check-in.update'), checkInSettings())
        ->assertSessionHasNoErrors();

    $first = $this->school->fresh()->check_in_token;

    expect($first)->not->toBeNull()
        ->and($this->school->fresh()->check_in_enabled)->toBeTrue();

    // Saving again is not a reprint.
    $this->actingAs($this->admin)->put(route('attendance.check-in.update'), checkInSettings(['check_in_radius_metres' => '200']));

    expect($this->school->fresh()->check_in_token)->toBe($first)
        ->and($this->school->fresh()->check_in_radius_metres)->toBe(200);
});

test('check-in cannot be switched on without saying where the school is', function () {
    $this->actingAs($this->admin)
        ->put(route('attendance.check-in.update'), checkInSettings(['latitude' => '', 'longitude' => '']))
        ->assertSessionHasErrors('latitude');

    expect($this->school->fresh()->check_in_enabled)->toBeFalse()
        ->and($this->school->fresh()->check_in_token)->toBeNull();
});

test('a school can choose the marked-by-a-teacher register and still use QR for staff', function () {
    $this->actingAs($this->admin)
        ->put(route('attendance.check-in.update'), checkInSettings(['student_attendance_mode' => AttendanceMode::Manual->value]))
        ->assertSessionHasNoErrors();

    expect($this->school->fresh()->student_attendance_mode)->toBe(AttendanceMode::Manual)
        ->and($this->school->fresh()->check_in_enabled)->toBeTrue();
});

test('issuing a new code kills the poster on the wall', function () {
    $this->actingAs($this->admin)->put(route('attendance.check-in.update'), checkInSettings());

    $old = $this->school->fresh()->check_in_token;

    $this->actingAs($this->admin)->post(route('attendance.check-in.rotate'))->assertSessionHasNoErrors();

    $new = $this->school->fresh()->check_in_token;

    expect($new)->not->toBe($old);

    $this->get(route('check-in.show', ['school' => $this->school, 'token' => $old]))->assertNotFound();
    $this->get(route('check-in.show', ['school' => $this->school, 'token' => $new]))->assertOk();
});

test('the poster downloads as a PDF naming the school', function () {
    $this->actingAs($this->admin)->put(route('attendance.check-in.update'), checkInSettings());

    $response = $this->actingAs($this->admin)->get(route('attendance.check-in.poster'));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))->toContain('check-in-poster.pdf');
});

test('there is no poster to download before check-in is switched on', function () {
    $this->actingAs($this->admin)->get(route('attendance.check-in.poster'))->assertNotFound();
});

test('the staff register shows arrivals, departures and time on site', function () {
    $staff = Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ngozi', 'last_name' => 'Eze']);

    StaffAttendanceRecord::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $staff->id,
        'date' => Carbon::parse('2026-09-21'),
        'arrived_at' => Carbon::parse('2026-09-21 07:15:00'),
        'departed_at' => Carbon::parse('2026-09-21 15:45:00'),
        'status' => AttendanceStatus::Present,
    ]);

    $this->actingAs($this->admin)
        ->get(route('attendance.staff'))
        ->assertOk()
        ->assertSee('Ngozi Eze')
        ->assertSee('7:15:00am')
        ->assertSee('3:45:00pm')
        ->assertSee('8h 30m');
});

test('one school never sees another school\'s staff register', function () {
    $otherSchool = School::factory()->create();
    $stranger = Staff::factory()->create(['school_id' => $otherSchool->id, 'first_name' => 'Tunde', 'last_name' => 'Bello']);

    StaffAttendanceRecord::factory()->create(['school_id' => $otherSchool->id, 'staff_id' => $stranger->id]);

    $this->actingAs($this->admin)
        ->get(route('attendance.staff'))
        ->assertOk()
        ->assertDontSee('Tunde Bello');
});

test('the settings page is School Admin only', function () {
    $staff = Staff::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($staff, 'staff')
        ->get(route('attendance.check-in.edit'))
        ->assertForbidden();
});
