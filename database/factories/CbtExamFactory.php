<?php

namespace Database\Factories;

use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtSubject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtExam>
 */
class CbtExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cbt_exam_body_id' => CbtExamBody::factory(),
            'cbt_subject_id' => CbtSubject::factory(),
            'year' => fake()->numberBetween(1999, (int) now()->format('Y')),
            'duration_minutes' => 60,
            'pass_mark' => 50,
        ];
    }
}
