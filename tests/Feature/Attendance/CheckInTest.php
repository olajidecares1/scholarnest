<?php

use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceScan;
use App\Models\School;
use App\Models\Staff;
use App\Models\StaffAttendanceRecord;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/*
 * The poster by the gate.
 *
 * The school in these tests sits at the origin of the coordinate system, which
 * is in the Gulf of Guinea and therefore nowhere, on purpose: the numbers are
 * then small enough to reason about, and 0.01 degrees of latitude is a little
 * over a kilometre, comfortably outside any radius under test.
 */

beforeEach(function () {
    $this->school = School::factory()->create([
        'check_in_enabled' => true,
        'student_attendance_mode' => AttendanceMode::Qr,
        'check_in_token' => Str::random(48),
        'latitude' => 0.0,
        'longitude' => 0.0,
        'check_in_radius_metres' => 150,
        'timezone' => 'Africa/Lagos',
    ]);

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 1',
    ]);

    $this->staff = Staff::factory()->create(['school_id' => $this->school->id]);
});

function scanUrl(School $school, string $name = 'check-in.store'): string
{
    return route($name, ['school' => $school, 'token' => $school->check_in_token]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function atTheGate(array $overrides = []): array
{
    return array_merge([
        'client_uuid' => (string) Str::uuid(),
        'scanned_at' => now()->toIso8601String(),
        'latitude' => 0.0,
        'longitude' => 0.0,
        'accuracy_metres' => 12,
        'was_queued' => false,
    ], $overrides);
}

describe('arriving and leaving', function () {
    test('a student\'s first scan of the day is their arrival, down to the second', function () {
        Carbon::setTestNow(Carbon::parse('2026-09-22 07:41:09', 'Africa/Lagos'));

        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate()]])
            ->assertOk()
            ->assertJsonPath('results.0.recorded', true)
            ->assertJsonPath('results.0.kind', 'arrival');

        $record = AttendanceRecord::sole();

        expect($record->student_id)->toBe($this->student->id)
            ->and($record->status)->toBe(AttendanceStatus::Present)
            ->and($record->source)->toBe('qr')
            ->and($record->class_name)->toBe('JSS 1')
            ->and($record->arrived_at->setTimezone('Africa/Lagos')->format('H:i:s'))->toBe('07:41:09')
            ->and($record->departed_at)->toBeNull();

        Carbon::setTestNow();
    });

    test('scanning again on the way home records the departure, not a second arrival', function () {
        Carbon::setTestNow(Carbon::parse('2026-09-22 07:41:09', 'Africa/Lagos'));

        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate()]])
            ->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-09-22 15:02:44', 'Africa/Lagos'));

        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate()]])
            ->assertOk()
            ->assertJsonPath('results.0.kind', 'departure')
            ->assertJsonPath('results.0.recorded', true);

        $record = AttendanceRecord::sole();

        expect($record->arrived_at->setTimezone('Africa/Lagos')->format('H:i:s'))->toBe('07:41:09')
            ->and($record->departed_at->setTimezone('Africa/Lagos')->format('H:i:s'))->toBe('15:02:44');

        Carbon::setTestNow();
    });

    test('a double tap at the gate is one arrival, not an instant departure', function () {
        Carbon::setTestNow(Carbon::parse('2026-09-22 07:41:09', 'Africa/Lagos'));

        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate()]])
            ->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-09-22 07:41:41', 'Africa/Lagos'));

        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate()]])
            ->assertOk()
            ->assertJsonPath('results.0.outcome', 'duplicate');

        expect(AttendanceRecord::sole()->departed_at)->toBeNull();

        Carbon::setTestNow();
    });

    test('staff get a register of their own, which never existed before', function () {
        $this->actingAs($this->staff, 'staff')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate()]])
            ->assertOk()
            ->assertJsonPath('results.0.recorded', true);

        expect(StaffAttendanceRecord::sole()->staff_id)->toBe($this->staff->id)
            ->and(AttendanceRecord::count())->toBe(0);
    });
});

describe('a photograph of the poster is not attendance', function () {
    test('a scan from across town is refused and the register stays empty', function () {
        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate(['latitude' => 0.05, 'longitude' => 0.05])]])
            ->assertOk()
            ->assertJsonPath('results.0.recorded', false)
            ->assertJsonPath('results.0.outcome', 'out_of_range');

        expect(AttendanceRecord::count())->toBe(0);
    });

    test('but the refusal is kept, with how far away they were', function () {
        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate(['latitude' => 0.05, 'longitude' => 0.05])]]);

        $scan = AttendanceScan::sole();

        expect($scan->outcome->value)->toBe('out_of_range')
            ->and($scan->distance_metres)->toBeGreaterThan(1000);
    });

    test('a phone that gives no position cannot check anybody in', function () {
        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate(['latitude' => null, 'longitude' => null, 'accuracy_metres' => null])]])
            ->assertOk()
            ->assertJsonPath('results.0.outcome', 'no_location');

        expect(AttendanceRecord::count())->toBe(0);
    });

    test('a poor fix is forgiven at the boundary, but only so far', function () {
        // ~220m out, with the phone admitting to 40m of error. Inside the
        // radius plus the allowance.
        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate(['latitude' => 0.0017, 'accuracy_metres' => 40])]])
            ->assertJsonPath('results.0.recorded', true);

        // The same distance with a phone claiming to be accurate to 5km does
        // not get to widen the fence to 5km.
        $this->actingAs($this->staff, 'staff')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate(['latitude' => 0.01, 'accuracy_metres' => 5000])]])
            ->assertJsonPath('results.0.recorded', false);
    });

    test('a wrong token is not a school', function () {
        $this->actingAs($this->student, 'student')
            ->postJson(route('check-in.store', ['school' => $this->school, 'token' => Str::random(48)]), ['scans' => [atTheGate()]])
            ->assertNotFound();
    });

    test('somebody signed in at another school cannot scan this poster', function () {
        $stranger = Student::factory()->create(['school_id' => School::factory()->create()->id]);

        $this->actingAs($stranger, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate()]])
            ->assertStatus(401);
    });

    test('a signed-out phone is told to sign in rather than turned away for good', function () {
        $this->postJson(scanUrl($this->school), ['scans' => [atTheGate()]])
            ->assertStatus(401);
    });
});

describe('no signal at the gate', function () {
    test('a scan held on a phone is recorded at the time it was made, not the time it arrived', function () {
        Carbon::setTestNow(Carbon::parse('2026-09-22 11:30:00', 'Africa/Lagos'));

        $scannedAt = Carbon::parse('2026-09-22 07:38:52', 'Africa/Lagos');

        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate([
                'scanned_at' => $scannedAt->toIso8601String(),
                'was_queued' => true,
            ])]])
            ->assertOk()
            ->assertJsonPath('results.0.recorded', true);

        expect(AttendanceRecord::sole()->arrived_at->setTimezone('Africa/Lagos')->format('H:i:s'))->toBe('07:38:52')
            ->and(AttendanceScan::sole()->was_queued)->toBeTrue();

        Carbon::setTestNow();
    });

    test('a morning\'s worth of queued scans goes up in one send', function () {
        Carbon::setTestNow(Carbon::parse('2026-09-22 11:30:00', 'Africa/Lagos'));

        $this->actingAs($this->staff, 'staff')
            ->postJson(scanUrl($this->school), ['scans' => [
                atTheGate(['scanned_at' => Carbon::parse('2026-09-22 07:20:00', 'Africa/Lagos')->toIso8601String(), 'was_queued' => true]),
                atTheGate(['scanned_at' => Carbon::parse('2026-09-22 10:05:00', 'Africa/Lagos')->toIso8601String(), 'was_queued' => true]),
            ]])
            ->assertOk()
            ->assertJsonCount(2, 'results');

        $record = StaffAttendanceRecord::sole();

        expect($record->arrived_at->setTimezone('Africa/Lagos')->format('H:i'))->toBe('07:20')
            ->and($record->departed_at->setTimezone('Africa/Lagos')->format('H:i'))->toBe('10:05');

        Carbon::setTestNow();
    });

    test('sending the same scan twice records it once', function () {
        $scan = atTheGate();

        $this->actingAs($this->student, 'student')->postJson(scanUrl($this->school), ['scans' => [$scan]])->assertOk();
        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [$scan]])
            ->assertOk()
            ->assertJsonPath('results.0.kind', 'arrival');

        expect(AttendanceScan::count())->toBe(1)
            ->and(AttendanceRecord::sole()->departed_at)->toBeNull();
    });

    test('an arrival that arrives late, after a departure already landed, still becomes the arrival', function () {
        Carbon::setTestNow(Carbon::parse('2026-09-22 15:10:00', 'Africa/Lagos'));

        // The phone found signal on the way out, so the 3pm scan is sent first
        // and is the only thing on file.
        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate()]])
            ->assertJsonPath('results.0.kind', 'arrival');

        // Then the 7:30am one, which had been waiting in the queue all day.
        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate([
                'scanned_at' => Carbon::parse('2026-09-22 07:30:00', 'Africa/Lagos')->toIso8601String(),
                'was_queued' => true,
            ])]])
            ->assertJsonPath('results.0.kind', 'arrival');

        expect(AttendanceRecord::sole()->arrived_at->setTimezone('Africa/Lagos')->format('H:i'))->toBe('07:30');

        Carbon::setTestNow();
    });

    test('a phone whose clock says next week is recorded at the time it actually arrived', function () {
        Carbon::setTestNow(Carbon::parse('2026-09-22 07:41:00', 'Africa/Lagos'));

        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate([
                'scanned_at' => now()->addWeek()->toIso8601String(),
            ])]])
            ->assertOk();

        expect(AttendanceRecord::sole()->arrived_at->setTimezone('Africa/Lagos')->format('Y-m-d H:i'))->toBe('2026-09-22 07:41');

        Carbon::setTestNow();
    });
});

describe('whether pupils may check themselves in is the school\'s choice', function () {
    test('a school on the marked-by-a-teacher register refuses a pupil\'s scan', function () {
        $this->school->update(['student_attendance_mode' => AttendanceMode::Manual]);

        $this->actingAs($this->student, 'student')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate()]])
            ->assertOk()
            ->assertJsonPath('results.0.outcome', 'not_allowed');

        expect(AttendanceRecord::count())->toBe(0);
    });

    test('and still accepts a teacher\'s, because that setting is about pupils', function () {
        $this->school->update(['student_attendance_mode' => AttendanceMode::Manual]);

        $this->actingAs($this->staff, 'staff')
            ->postJson(scanUrl($this->school), ['scans' => [atTheGate()]])
            ->assertJsonPath('results.0.recorded', true);
    });

    test('with check-in switched off the poster leads nowhere at all', function () {
        $this->school->update(['check_in_enabled' => false]);

        $this->actingAs($this->student, 'student')
            ->get(route('check-in.show', ['school' => $this->school, 'token' => $this->school->check_in_token]))
            ->assertNotFound();
    });
});

describe('the scan page', function () {
    test('opens for anybody holding the poster, signed in or not', function () {
        $this->get(route('check-in.show', ['school' => $this->school, 'token' => $this->school->check_in_token]))
            ->assertOk()
            ->assertSee($this->school->name);
    });

    test('names nobody, because the service worker is allowed to keep a copy of it', function () {
        // The whole of resources/views/pwa/service-worker.blade.php turns on
        // never caching a page with somebody's session in it. This page is the
        // one exception, and it is only safe while this holds.
        $this->actingAs($this->student, 'student')
            ->get(route('check-in.show', ['school' => $this->school, 'token' => $this->school->check_in_token]))
            ->assertOk()
            ->assertDontSee($this->student->first_name)
            ->assertDontSee($this->student->last_name);
    });

    test('says who is signed in, separately, where nothing is cached', function () {
        $this->actingAs($this->student, 'student')
            ->getJson(route('check-in.whoami', ['school' => $this->school, 'token' => $this->school->check_in_token]))
            ->assertOk()
            ->assertJsonPath('signed_in', true)
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('name', trim($this->student->first_name.' '.$this->student->last_name));
    });

    test('and offers the way in when nobody is', function () {
        $this->getJson(route('check-in.whoami', ['school' => $this->school, 'token' => $this->school->check_in_token]))
            ->assertOk()
            ->assertJsonPath('signed_in', false)
            ->assertJsonPath('portal_url', route('portal.index', ['school' => $this->school]));
    });
});
