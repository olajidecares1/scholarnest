<?php

namespace Database\Factories;

use App\Models\PrincipalRemark;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrincipalRemark>
 */
class PrincipalRemarkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = $this->faker->randomElement([
            'Excellent performance. Keep up the good work.',
            'A very impressive result. Continue to work hard.',
            'Good improvement this term.',
            'More effort is required next term.',
            'You have the ability to achieve much more.',
            'Outstanding academic performance.',
        ]).' '.$this->faker->unique()->numberBetween(1, 100000);

        return [
            'school_id' => School::factory(),
            'created_by' => null,
            'body' => $body,
            'body_hash' => hash('sha256', mb_strtolower($body)),
        ];
    }
}
