<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'RPT-'.strtoupper(Str::random(8)),
            'reporter_name' => fake()->name(),
            'reporter_email' => fake()->safeEmail(),
            'description' => fake()->paragraph(),
            'status' => ReportStatus::New,
        ];
    }
}
