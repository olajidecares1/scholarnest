<?php

namespace Database\Factories;

use App\Enums\ResultCheckingPinStatus;
use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultCheckingPin>
 */
class ResultCheckingPinFactory extends Factory
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
            'examination_id' => Examination::factory(),
            'code' => ResultCheckingPin::generateUniqueCode(),
            'status' => ResultCheckingPinStatus::Active,
            'max_uses' => 4,
            'uses_count' => 0,
            'generated_by' => User::factory(),
        ];
    }
}
