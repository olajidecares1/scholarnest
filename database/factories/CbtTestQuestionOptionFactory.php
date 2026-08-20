<?php

namespace Database\Factories;

use App\Models\CbtTestQuestion;
use App\Models\CbtTestQuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtTestQuestionOption>
 */
class CbtTestQuestionOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cbt_test_question_id' => CbtTestQuestion::factory(),
            'label' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'option_text' => fake()->words(3, true),
            'is_correct' => false,
        ];
    }
}
