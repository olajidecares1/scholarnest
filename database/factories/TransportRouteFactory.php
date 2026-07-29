<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\TransportRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportRoute>
 */
class TransportRouteFactory extends Factory
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
            'name' => fake()->streetName().' Route',
            'fee' => fake()->numberBetween(3000, 15000),
        ];
    }
}
