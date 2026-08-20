<?php

namespace Database\Factories;

use App\Models\CbtAttempt;
use App\Models\CbtAttemptAnswer;
use App\Models\CbtQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtAttemptAnswer>
 */
class CbtAttemptAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cbt_attempt_id' => CbtAttempt::factory(),
            'cbt_question_id' => CbtQuestion::factory(),
            'cbt_question_option_id' => null,
            'is_correct' => false,
        ];
    }
}
