<?php

namespace Database\Factories;

use App\Models\CbtExam;
use App\Models\CbtQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtQuestion>
 */
class CbtQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cbt_exam_id' => CbtExam::factory(),
            'question_text' => fake()->sentence().'?',
            'sort_order' => 0,
        ];
    }
}
