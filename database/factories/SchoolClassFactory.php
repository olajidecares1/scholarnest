<?php

namespace Database\Factories;

use App\Models\AcademicLevel;
use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
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
            'academic_level_id' => AcademicLevel::factory(),
            'name' => fake()->randomElement(['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3']),
            'sort_order' => 0,
        ];
    }
}
