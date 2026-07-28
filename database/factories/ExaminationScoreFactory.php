<?php

namespace Database\Factories;

use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExaminationScore>
 */
class ExaminationScoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'examination_subject_id' => ExaminationSubject::factory(),
            'student_id' => Student::factory(),
            'score' => fake()->numberBetween(20, 100),
        ];
    }
}
