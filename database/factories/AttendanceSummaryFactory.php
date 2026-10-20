<?php

namespace Database\Factories;

use App\Enums\ExamTerm;
use App\Models\AttendanceSummary;
use App\Models\School;
use App\Models\Student;
use App\Support\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSummary>
 */
class AttendanceSummaryFactory extends Factory
{
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
            'session' => AcademicSession::current(),
            'term' => ExamTerm::First,
            'days_present' => fake()->numberBetween(40, 55),
            'days_absent' => fake()->numberBetween(0, 5),
            'days_late' => fake()->numberBetween(0, 5),
            'days_excused' => fake()->numberBetween(0, 3),
        ];
    }
}
