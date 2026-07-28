<?php

namespace Database\Factories;

use App\Enums\ExamTerm;
use App\Models\Examination;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Examination>
 */
class ExaminationFactory extends Factory
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
            'name' => fake()->randomElement(['First Term Examination', 'Second Term Examination', 'Third Term Examination']),
            'class_name' => fake()->randomElement(['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3']),
            'term' => fake()->randomElement(ExamTerm::cases()),
            'session' => '2025/2026',
            'exam_date' => fake()->dateTimeBetween('-2 months', '+2 months'),
        ];
    }
}
