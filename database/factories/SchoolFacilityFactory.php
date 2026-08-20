<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SchoolFacility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolFacility>
 */
class SchoolFacilityFactory extends Factory
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
            'name' => fake()->randomElement(['Science Laboratory', 'ICT Lab', 'Library', 'Sports Complex', 'Assembly Hall', 'Playground']),
            'category' => fake()->randomElement(['Academic', 'Sports', 'Recreation']),
            'description' => fake()->sentence(12),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
