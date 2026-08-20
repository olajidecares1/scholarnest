<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'admission_number' => strtoupper(fake()->unique()->bothify('STU-####')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(Gender::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-5 years'),
            'class_name' => fake()->randomElement(['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3']),
            'guardian_name' => fake()->name(),
            'guardian_phone' => fake()->phoneNumber(),
            'guardian_email' => fake()->safeEmail(),
            'admission_date' => fake()->dateTimeBetween('-3 years', 'now'),
            'is_active' => true,
            'password' => static::$password ??= Hash::make('password'),
        ];
    }
}
