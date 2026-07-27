<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sent_by' => User::factory(),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'recipients_count' => fake()->numberBetween(1, 50),
        ];
    }
}
