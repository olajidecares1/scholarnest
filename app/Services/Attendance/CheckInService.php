<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Enums\CheckInKind;
use App\Enums\CheckInOutcome;
use App\Models\AttendanceRecord;
use App\Models\AttendanceScan;
use App\Models\School;
use App\Models\Staff;
use App\Models\StaffAttendanceRecord;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns a tap on the poster into a row in the register.
 *
 * Everything that decides whether a scan counts lives here, once, because the
 * scan arrives by three different routes, from the page the moment it happens,
 * from the queue on the device when signal comes back, and from the queue in
 * the service worker, and all three have to reach the same answer.
 */
class CheckInService
{
    /**
     * Two scans this close together are one scan. Somebody double-taps, or
     * holds the phone up while the page is still loading, and without this the
     * second tap would be read as going home at 7:41am.
     */
    public const DUPLICATE_WINDOW_MINUTES = 2;

    /**
     * How far ahead of the server a phone's clock may be before it is treated
     * as simply wrong. Phones drift; they do not drift into next week.
     */
    public const CLOCK_SKEW_MINUTES = 5;

    /**
     * A queued scan older than this is not a scan that was waiting for signal,
     * it is a clock set wrong, so it is recorded at the time it arrived.
     */
    public const MAX_QUEUE_AGE_DAYS = 7;

    /**
     * How much of the phone's own claimed inaccuracy is forgiven at the
     * boundary. A good urban fix is 10-30m and a poor one is hundreds; letting
     * the whole reported figure widen the fence would make "accuracy: 5000"
     * the way to check in from home, so it is capped.
     */
    public const MAX_ACCURACY_ALLOWANCE_METRES = 50;

    /**
     * Record a scan, or return the row that already recorded it.
     *
     * Never throws for a refused scan: a refusal is an outcome, and the school
     * wants to see it. The only thing that stops a row being written is the
     * scan already having one.
     */
    public function record(School $school, Student|Staff $person, ScannedPunch $punch): AttendanceScan
    {
        $existing = AttendanceScan::where('school_id', $school->id)
            ->where('client_uuid', $punch->clientUuid)
            ->first();

        // The same scan, sent again because the first reply never made it back
        // to the phone. Hand back what happened the first time.
        if ($existing) {
            return $existing;
        }

        $receivedAt = now();
        $scannedAt = $this->trustworthyTime($punch->scannedAt, $receivedAt);
        $distance = $this->distanceInMetres($school, $punch);

        $outcome = $this->outcomeFor($school, $person, $punch, $distance);
        $kind = CheckInKind::Arrival;

        return DB::transaction(function () use ($school, $person, $punch, $scannedAt, $receivedAt, $distance, $outcome, $kind) {
            if ($outcome === CheckInOutcome::Recorded) {
                [$kind, $outcome] = $this->applyToRegister($school, $person, $scannedAt);
            }

            return AttendanceScan::create([
                'school_id' => $school->id,
                'student_id' => $person instanceof Student ? $person->id : null,
                'staff_id' => $person instanceof Staff ? $person->id : null,
                'client_uuid' => $punch->clientUuid,
                'scanned_at' => $scannedAt,
                'received_at' => $receivedAt,
                'kind' => $kind,
                'outcome' => $outcome,
                'latitude' => $punch->latitude,
                'longitude' => $punch->longitude,
                'accuracy_metres' => $punch->accuracyMetres,
                'distance_metres' => $distance === null ? null : (int) round($distance),
                'was_queued' => $punch->wasQueued,
            ]);
        });
    }

    /**
     * Is this person allowed to check themselves in at this school at all?
     *
     * Staff follow the school's master switch. Pupils additionally need the
     * school to have chosen QR over the marked-by-a-teacher register, which is
     * the setting a primary school leaves alone.
     */
    public function isAllowed(School $school, Student|Staff $person): bool
    {
        if (! $school->check_in_enabled) {
            return false;
        }

        if ($person instanceof Student) {
            return $school->student_attendance_mode === AttendanceMode::Qr;
        }

        return true;
    }

    /**
     * The scan's own day, in the school's timezone.
     *
     * Not "today": a scan that sat on a phone overnight belongs to the morning
     * it was made, which is the reason the device's clock is carried at all.
     */
    public function localDate(School $school, Carbon $scannedAt): string
    {
        return $scannedAt->copy()->setTimezone($this->timezone($school))->toDateString();
    }

    /**
     * Straight-line distance from the school gate, or null when the phone gave
     * no position or the school has recorded none.
     */
    public function distanceInMetres(School $school, ScannedPunch $punch): ?float
    {
        if ($punch->latitude === null || $punch->longitude === null) {
            return null;
        }

        if ($school->latitude === null || $school->longitude === null) {
            return null;
        }

        return $this->haversine(
            (float) $school->latitude,
            (float) $school->longitude,
            $punch->latitude,
            $punch->longitude,
        );
    }

    private function outcomeFor(School $school, Student|Staff $person, ScannedPunch $punch, ?float $distance): CheckInOutcome
    {
        if (! $this->isAllowed($school, $person)) {
            return CheckInOutcome::NotAllowed;
        }

        // A school cannot switch check-in on without coordinates, so a null
        // distance here means the PHONE gave nothing, not that the school did.
        if ($distance === null) {
            return CheckInOutcome::NoLocation;
        }

        $allowance = min($punch->accuracyMetres ?? 0, self::MAX_ACCURACY_ALLOWANCE_METRES);

        return $distance <= $school->check_in_radius_metres + $allowance
            ? CheckInOutcome::Recorded
            : CheckInOutcome::OutOfRange;
    }

    /**
     * Write the arrival or the departure, and say which it was.
     *
     * @return array{0: CheckInKind, 1: CheckInOutcome}
     */
    private function applyToRegister(School $school, Student|Staff $person, Carbon $scannedAt): array
    {
        $date = $this->localDate($school, $scannedAt);
        $record = $this->registerRow($person, $date);

        // Nobody has scanned yet today: this is the arrival.
        if (! $record || ! $record->arrived_at) {
            $this->writeArrival($school, $person, $date, $scannedAt);

            return [CheckInKind::Arrival, CheckInOutcome::Recorded];
        }

        // A scan made BEFORE the arrival already on file. That happens when the
        // real arrival was queued on a phone with no signal and something else
        // reached the server first, so the earlier time is the true one.
        if ($scannedAt->lessThan($record->arrived_at)) {
            $record->update(['arrived_at' => $scannedAt]);

            return [CheckInKind::Arrival, CheckInOutcome::Recorded];
        }

        if ($record->arrived_at->diffInMinutes($scannedAt) < self::DUPLICATE_WINDOW_MINUTES) {
            return [CheckInKind::Arrival, CheckInOutcome::Duplicate];
        }

        // Going home. A third and fourth scan simply move the time later,
        // because the last time somebody was seen is the one that matters.
        if ($record->departed_at && $record->departed_at->greaterThanOrEqualTo($scannedAt)) {
            return [CheckInKind::Departure, CheckInOutcome::Duplicate];
        }

        $record->update(['departed_at' => $scannedAt]);

        return [CheckInKind::Departure, CheckInOutcome::Recorded];
    }

    private function registerRow(Student|Staff $person, string $date): AttendanceRecord|StaffAttendanceRecord|null
    {
        if ($person instanceof Student) {
            return AttendanceRecord::where('student_id', $person->id)->where('date', $date)->first();
        }

        return StaffAttendanceRecord::where('staff_id', $person->id)->where('date', $date)->first();
    }

    private function writeArrival(School $school, Student|Staff $person, string $date, Carbon $scannedAt): void
    {
        // Present, not Late: how late is late is a school's own rule and this
        // does not know it. The office can change any row by hand afterwards,
        // and a change made later is the one that stands.
        if ($person instanceof Student) {
            AttendanceRecord::updateOrCreate(
                ['student_id' => $person->id, 'date' => $date],
                [
                    'school_id' => $school->id,
                    'class_name' => $person->class_name,
                    'status' => AttendanceStatus::Present,
                    'arrived_at' => $scannedAt,
                    'source' => 'qr',
                ],
            );

            return;
        }

        StaffAttendanceRecord::updateOrCreate(
            ['staff_id' => $person->id, 'date' => $date],
            [
                'school_id' => $school->id,
                'status' => AttendanceStatus::Present,
                'arrived_at' => $scannedAt,
                'source' => 'qr',
            ],
        );
    }

    /**
     * The phone's clock, or the server's where the phone's is not believable.
     */
    private function trustworthyTime(Carbon $scannedAt, Carbon $receivedAt): Carbon
    {
        if ($scannedAt->greaterThan($receivedAt->copy()->addMinutes(self::CLOCK_SKEW_MINUTES))) {
            return $receivedAt->copy();
        }

        if ($scannedAt->lessThan($receivedAt->copy()->subDays(self::MAX_QUEUE_AGE_DAYS))) {
            return $receivedAt->copy();
        }

        return $scannedAt->copy();
    }

    private function timezone(School $school): string
    {
        return $school->timezone ?: config('app.timezone');
    }

    /**
     * Great-circle distance in metres. The earth is not a sphere, but over the
     * few hundred metres a school gate is measured in, pretending it is one is
     * accurate to far better than any phone's own fix.
     */
    private function haversine(float $latitude1, float $longitude1, float $latitude2, float $longitude2): float
    {
        $earthRadius = 6371000.0;

        $deltaLatitude = deg2rad($latitude2 - $latitude1);
        $deltaLongitude = deg2rad($longitude2 - $longitude1);

        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($deltaLongitude / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
