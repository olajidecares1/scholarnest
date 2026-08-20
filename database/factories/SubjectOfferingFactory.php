<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Subject;
use App\Models\SubjectOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectOffering>
 */
class SubjectOfferingFactory extends Factory
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
            'class_name' => 'SS 1 Science',
            'subject_id' => Subject::factory(),
        ];
    }
}
