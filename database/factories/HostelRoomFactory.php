<?php

namespace Database\Factories;

use App\Models\Hostel;
use App\Models\HostelRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HostelRoom>
 */
class HostelRoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hostel_id' => Hostel::factory(),
            'room_number' => (string) fake()->unique()->numberBetween(1, 999),
            'capacity' => 4,
        ];
    }
}
