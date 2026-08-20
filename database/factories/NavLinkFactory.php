<?php

namespace Database\Factories;

use App\Models\NavLink;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NavLink>
 */
class NavLinkFactory extends Factory
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
            'label' => fake()->randomElement(['Home', 'About', 'Academics', 'Contact']),
            'url' => '/'.fake()->slug(2),
            'sort_order' => fake()->numberBetween(0, 5),
        ];
    }
}
