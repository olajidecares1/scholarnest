<?php

namespace Database\Factories;

use App\Models\CbtAttempt;
use App\Models\CbtExam;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtAttempt>
 */
class CbtAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'cbt_exam_id' => CbtExam::factory(),
            'started_at' => now(),
            'submitted_at' => null,
            'score' => null,
            'total_questions' => 0,
        ];
    }
}
