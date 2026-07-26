<?php

namespace Database\Factories;

use App\Enums\PlanKey;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->randomElement(PlanKey::cases()),
            'name' => fake()->words(2, true).' Plan',
            'tagline' => fake()->sentence(),
            'price_per_student_per_term' => 500,
            'features' => ['Feature One', 'Feature Two'],
            'is_popular' => false,
            'sort_order' => fake()->numberBetween(1, 3),
        ];
    }
}
