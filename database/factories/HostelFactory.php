<?php

namespace Database\Factories;

use App\Enums\HostelGender;
use App\Models\Hostel;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hostel>
 */
class HostelFactory extends Factory
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
            'name' => fake()->lastName().' House',
            'gender' => fake()->randomElement(HostelGender::cases()),
            'warden_name' => fake()->name(),
            'warden_phone' => fake()->phoneNumber(),
        ];
    }
}
