<?php

namespace Database\Factories;

use App\Models\CbtQuestion;
use App\Models\CbtQuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtQuestionOption>
 */
class CbtQuestionOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cbt_question_id' => CbtQuestion::factory(),
            'label' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'option_text' => fake()->words(3, true),
            'is_correct' => false,
        ];
    }
}
