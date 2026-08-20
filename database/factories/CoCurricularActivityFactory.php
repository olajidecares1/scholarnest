<?php

namespace Database\Factories;

use App\Models\CoCurricularActivity;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoCurricularActivity>
 */
class CoCurricularActivityFactory extends Factory
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
            'name' => fake()->randomElement(['Debate Club', 'Football Team', 'Drama Society', 'Chess Club', 'Choir']),
            'category' => fake()->randomElement(['Sports', 'Arts', 'Academic']),
            'description' => fake()->sentence(),
            'schedule_text' => 'Every Friday, 2:00 PM',
        ];
    }
}
