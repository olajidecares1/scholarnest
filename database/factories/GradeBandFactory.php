<?php

namespace Database\Factories;

use App\Models\GradeBand;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeBand>
 */
class GradeBandFactory extends Factory
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
            'min_percent' => 70,
            'max_percent' => 100,
            'letter' => 'A',
            'description' => 'Excellent',
            'position' => 0,
        ];
    }
}
