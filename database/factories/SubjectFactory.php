<?php

namespace Database\Factories;

use App\Enums\SubjectCategory;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Government', 'Commerce']),
            'category' => SubjectCategory::General,
        ];
    }
}
