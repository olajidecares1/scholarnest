<?php

namespace Database\Factories;

use App\Enums\FeePaymentMethod;
use App\Models\FeePayment;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeePayment>
 */
class FeePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->numberBetween(1000, 20000),
            'paid_at' => now(),
            'method' => fake()->randomElement(FeePaymentMethod::cases()),
        ];
    }
}
