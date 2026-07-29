<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\TransportVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportVehicle>
 */
class TransportVehicleFactory extends Factory
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
            'name' => 'Bus '.fake()->numberBetween(1, 20),
            'plate_number' => strtoupper(fake()->bothify('???-###??')),
            'capacity' => fake()->numberBetween(14, 60),
            'driver_name' => fake()->name(),
            'driver_phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
