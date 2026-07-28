<?php

namespace Database\Factories;

use App\Models\Examination;
use App\Models\ExaminationSubject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExaminationSubject>
 */
class ExaminationSubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'examination_id' => Examination::factory(),
            'name' => fake()->unique()->randomElement(['Mathematics', 'English Language', 'Basic Science', 'Social Studies', 'Civic Education']),
            'max_score' => 100,
        ];
    }
}
