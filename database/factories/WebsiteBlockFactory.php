<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\WebsiteBlock;
use App\Support\WebsiteBlockDefaults;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteBlock>
 */
class WebsiteBlockFactory extends Factory
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
            'page' => 'home',
            'section' => 'hero',
            'type' => 'text',
            'content' => fake()->sentence(),
            'secondary_content' => null,
            'url' => null,
            'x' => fake()->numberBetween(0, 60),
            'y' => fake()->numberBetween(0, 60),
            'w' => fake()->numberBetween(20, 40),
            'h' => fake()->numberBetween(10, 30),
            'style' => WebsiteBlockDefaults::defaultStyle('text'),
            'sort_order' => 0,
        ];
    }
}
