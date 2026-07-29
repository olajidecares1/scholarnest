<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportAssignment>
 */
class TransportAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transport_route_id' => TransportRoute::factory(),
            'student_id' => Student::factory(),
        ];
    }
}
