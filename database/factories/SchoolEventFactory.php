<?php

namespace Database\Factories;

use App\Enums\EventAudience;
use App\Models\School;
use App\Models\SchoolEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolEvent>
 */
class SchoolEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+2 months');

        return [
            'school_id' => School::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'location' => fake()->optional()->streetAddress(),
            'audience' => fake()->randomElement(EventAudience::cases()),
            'is_all_day' => false,
            'starts_at' => $startsAt,
            'ends_at' => null,
        ];
    }
}
