<?php

namespace Database\Factories;

use App\Enums\IdCardHolderType;
use App\Enums\IdCardOrientation;
use App\Models\IdCardTemplate;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdCardTemplate>
 */
class IdCardTemplateFactory extends Factory
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
            'name' => fake()->randomElement(['Standard Student Card', 'Standard Staff Card', 'Senior School Card']),
            'type' => fake()->randomElement(IdCardHolderType::cases()),
            'orientation' => fake()->randomElement(IdCardOrientation::cases()),
            'primary_color' => '#1d4ed8',
            'secondary_color' => '#111a35',
            'show_blood_group' => false,
            'show_dob' => false,
            'is_default' => false,
        ];
    }
}
