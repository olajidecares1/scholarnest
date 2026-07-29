<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SchoolWebsite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolWebsite>
 */
class SchoolWebsiteFactory extends Factory
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
            'hero_title' => fake()->catchPhrase(),
            'hero_subtitle' => fake()->sentence(),
            'about_text' => fake()->paragraph(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'is_published' => false,
        ];
    }
}
