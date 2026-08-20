<?php

namespace Database\Factories;

use App\Enums\IdCardHolderType;
use App\Enums\IssuedIdCardStatus;
use App\Models\IssuedIdCard;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssuedIdCard>
 */
class IssuedIdCardFactory extends Factory
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
            'holder_type' => IdCardHolderType::Student,
            'holder_uuid' => fake()->uuid(),
            'card_number' => 'SCH-STU-'.fake()->unique()->numerify('######'),
            'serial_number' => fake()->unique()->numberBetween(1, 100000),
            'status' => IssuedIdCardStatus::Active,
            'issued_by' => User::factory(),
            'issued_at' => now(),
        ];
    }
}
