<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SchoolNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolNotice>
 */
class SchoolNoticeFactory extends Factory
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
            'sent_by' => null,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'class_name' => null,
        ];
    }
}
