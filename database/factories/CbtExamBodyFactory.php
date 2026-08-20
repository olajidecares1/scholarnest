<?php

namespace Database\Factories;

use App\Enums\AcademicStage;
use App\Models\CbtExamBody;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtExamBody>
 */
class CbtExamBodyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => fake()->unique()->lexify('???'),
            'description' => fake()->sentence(),
            'academic_stages' => [AcademicStage::SeniorSecondary->value],
        ];
    }
}
