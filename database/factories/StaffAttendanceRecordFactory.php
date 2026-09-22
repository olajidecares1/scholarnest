<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\School;
use App\Models\Staff;
use App\Models\StaffAttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffAttendanceRecord>
 */
class StaffAttendanceRecordFactory extends Factory
{
    /**
     * One row per member of staff per day, so the dates have to be distinct
     * for the same reason AttendanceRecordFactory's are: a random date in a
     * window collides with itself often enough to fail a run.
     */
    private static int $day = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = now()->startOfDay()->subDays(self::$day++ % 30);

        return [
            'school_id' => School::factory(),
            'staff_id' => Staff::factory(),
            'date' => $date,
            'arrived_at' => $date->copy()->setTime(7, 45),
            'departed_at' => $date->copy()->setTime(15, 30),
            'status' => AttendanceStatus::Present,
            'source' => 'qr',
        ];
    }
}
