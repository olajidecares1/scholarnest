<?php

namespace Database\Factories;

use App\Models\HeroSlide;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSlide>
 */
class HeroSlideFactory extends Factory
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
            'image_path' => 'hero-slides/'.fake()->uuid().'.jpg',
            'sort_order' => fake()->numberBetween(0, 5),
        ];
    }
}
