<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * A register holds one row per student per day, so a random date inside a
     * thirty-day window collides often enough to fail a run. Walk the window
     * instead.
     */
    private static int $day = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'class_name' => fake()->randomElement(['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3']),
            'date' => now()->startOfDay()->subDays(self::$day++ % 30),
            'status' => fake()->randomElement(AttendanceStatus::cases()),
        ];
    }
}
