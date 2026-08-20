<?php

namespace Database\Factories;

use App\Models\CbtTest;
use App\Models\CbtTestAttempt;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtTestAttempt>
 */
class CbtTestAttemptFactory extends Factory
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
            'cbt_test_id' => CbtTest::factory(),
            'started_at' => now(),
            'submitted_at' => null,
            'score' => null,
            'total_questions' => 0,
        ];
    }
}
