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

    /**
     * A question a student could actually sit: four options, one of them right.
     *
     * The bare factory deliberately makes neither - a question with no options
     * is a real state extraction can produce, and tests need to be able to
     * create it. But a test that means "a finished, publishable question" has
     * to say so, because the publish gate now checks exactly that.
     */
    public function answerable(string $correctLabel = 'A'): static
    {
        return $this->afterCreating(function (CbtTestQuestion $question) use ($correctLabel): void {
            foreach (['A', 'B', 'C', 'D'] as $label) {
                $question->options()->create([
                    'label' => $label,
                    'option_text' => fake()->word(),
                    'is_correct' => $label === strtoupper($correctLabel),
                ]);
            }
        });
    }
}
