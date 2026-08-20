<?php

namespace Database\Factories;

use App\Models\CbtTest;
use App\Models\CbtTestQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtTestQuestion>
 */
class CbtTestQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cbt_test_id' => CbtTest::factory(),
            'question_text' => fake()->sentence().'?',
            'sort_order' => 0,
        ];
    }
}
