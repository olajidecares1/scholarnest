<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimetableEntry>
 */
class TimetableEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(8, 14);

        return [
            'school_id' => School::factory(),
            'class_name' => fake()->randomElement(['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3']),
            'day_of_week' => fake()->numberBetween(1, 5),
            'start_time' => sprintf('%02d:00', $start),
            'end_time' => sprintf('%02d:00', $start + 1),
            'subject' => fake()->randomElement(['Mathematics', 'English Language', 'Basic Science', 'Social Studies']),
            'room' => 'Room '.fake()->numberBetween(1, 20),
            'sort_order' => 0,
        ];
    }
}
