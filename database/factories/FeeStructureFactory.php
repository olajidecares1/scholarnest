<?php

namespace Database\Factories;

use App\Models\FeeStructure;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeStructure>
 */
class FeeStructureFactory extends Factory
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
            'name' => 'Tuition Fee',
            'class_name' => null,
            'amount' => fake()->numberBetween(10000, 100000),
            'session' => '2025/2026',
            'term' => 'First Term',
        ];
    }
}
