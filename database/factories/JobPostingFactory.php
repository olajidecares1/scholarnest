<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => fake()->jobTitle(),
            'department' => fake()->optional()->randomElement(['Academics', 'Administration', 'Sports', 'ICT']),
            'employment_type' => fake()->randomElement(EmploymentType::cases()),
            'status' => JobPostingStatus::Published,
            'location' => fake()->city(),
            'description' => fake()->paragraphs(2, true),
            'posted_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'published_at' => now(),
            'closes_at' => fake()->dateTimeBetween('+1 week', '+2 months'),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => JobPostingStatus::Draft, 'published_at' => null]);
    }

    public function closed(): static
    {
        return $this->state(['status' => JobPostingStatus::Closed, 'closed_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(['closes_at' => now()->subDays(2)]);
    }
}
