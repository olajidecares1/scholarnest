<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
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
            'class_name' => fake()->randomElement(['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3']),
            'subject' => fake()->randomElement(['Mathematics', 'English Language', 'Basic Science', 'Social Studies']),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'due_date' => fake()->dateTimeBetween('now', '+2 weeks'),
            'max_score' => 100,
        ];
    }
}
