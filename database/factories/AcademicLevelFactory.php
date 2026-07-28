<?php

namespace Database\Factories;

use App\Models\AcademicLevel;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicLevel>
 */
class AcademicLevelFactory extends Factory
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
            'name' => fake()->randomElement(['Senior Secondary School', 'Junior Secondary School', 'Upper Primary', 'Lower Primary']),
            'sort_order' => 0,
        ];
    }
}
