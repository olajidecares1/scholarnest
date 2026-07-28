<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\StaffRole;
use App\Models\School;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
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
            'staff_number' => strtoupper(fake()->unique()->bothify('STF-####')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(Gender::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-21 years'),
            'role' => fake()->randomElement(StaffRole::cases()),
            'department' => fake()->randomElement(['Mathematics', 'English Language', 'Science', 'Administration', 'Accounts']),
            'qualification' => fake()->randomElement(['B.Sc', 'B.Ed', 'M.Sc', 'HND', 'NCE']),
            'employment_date' => fake()->dateTimeBetween('-10 years', 'now'),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
