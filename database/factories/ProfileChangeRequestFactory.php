<?php

namespace Database\Factories;

use App\Models\ProfileChangeRequest;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProfileChangeRequest>
 */
class ProfileChangeRequestFactory extends Factory
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
            'requester_type' => 'student',
            'requester_uuid' => (string) Str::uuid(),
            'subject_type' => 'student',
            'subject_uuid' => (string) Str::uuid(),
            'field_key' => 'admission_number',
            'field_label' => 'Admission Number',
            'current_value' => $this->faker->bothify('ADM-####'),
            'requested_value' => $this->faker->bothify('ADM-####'),
            'reason' => $this->faker->sentence(),
            'status' => 'pending',
        ];
    }
}
