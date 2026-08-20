<?php

namespace Database\Factories;

use App\Models\NewsPost;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsPost>
 */
class NewsPostFactory extends Factory
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
            'title' => fake()->sentence(5),
            'excerpt' => fake()->optional()->sentence(15),
            'body' => fake()->paragraphs(3, true),
            'category' => fake()->randomElement(['School News', 'Achievements', 'Events', 'Announcements']),
            'is_published' => true,
            'published_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }
}
