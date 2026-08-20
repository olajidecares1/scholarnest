<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Models\JobPosting;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
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
            'title' => fake()->jobTitle(),
            'department' => fake()->optional()->randomElement(['Academics', 'Administration', 'Sports', 'ICT']),
            'employment_type' => fake()->randomElement(EmploymentType::cases()),
            'location' => fake()->city(),
            'description' => fake()->paragraphs(2, true),
            'is_active' => true,
            'posted_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'closes_at' => fake()->optional()->dateTimeBetween('now', '+2 months'),
        ];
    }
}
