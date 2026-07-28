<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'student_id' => Student::factory(),
            'title' => 'Tuition Fee',
            'amount' => fake()->numberBetween(10000, 100000),
            'due_date' => fake()->dateTimeBetween('now', '+1 month'),
        ];
    }
}
