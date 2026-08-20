<?php

namespace Database\Factories;

use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\ResultCheckingPinUsage;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultCheckingPinUsage>
 */
class ResultCheckingPinUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'result_checking_pin_id' => ResultCheckingPin::factory(),
            'student_id' => Student::factory(),
            'examination_id' => Examination::factory(),
            'ip_address' => fake()->ipv4(),
            'used_at' => now(),
        ];
    }
}
