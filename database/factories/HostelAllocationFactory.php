<?php

namespace Database\Factories;

use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HostelAllocation>
 */
class HostelAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hostel_room_id' => HostelRoom::factory(),
            'student_id' => Student::factory(),
            'allocated_date' => now(),
        ];
    }
}
