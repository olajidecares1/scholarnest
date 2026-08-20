<?php

namespace Database\Factories;

use App\Enums\CbtTestStatus;
use App\Models\CbtTest;
use App\Models\School;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtTest>
 */
class CbtTestFactory extends Factory
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
            'staff_id' => Staff::factory(),
            'title' => fake()->sentence(3),
            'subject' => fake()->randomElement(['Mathematics', 'English Language', 'Basic Science']),
            'class_name' => fake()->randomElement(['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3']),
            'duration_minutes' => 30,
            'pass_mark' => 50,
            'status' => CbtTestStatus::Draft,
        ];
    }
}
