<?php

namespace Database\Factories;

use App\Enums\ExamTerm;
use App\Models\AcademicTerm;
use App\Models\School;
use App\Support\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
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
            'session' => AcademicSession::current(),
            'term' => ExamTerm::First,
            'starts_on' => now()->subMonths(2)->startOfMonth(),
            'ends_on' => now()->addMonth()->endOfMonth(),
        ];
    }
}
