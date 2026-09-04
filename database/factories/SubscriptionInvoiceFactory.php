<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SubscriptionInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionInvoice>
 */
class SubscriptionInvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $licences = fake()->numberBetween(50, 400);
        $unitPrice = 1500;
        $total = $licences * $unitPrice;

        return [
            // Random rather than sequential: the real numbering belongs to
            // App\Services\SubscriptionInvoiceIssuer, and a factory quietly
            // producing plausible-looking sequences invites a test to assert
            // against numbering this never actually generated.
            'number' => 'SN-'.now()->format('Y').'-'.strtoupper(Str::random(8)),
            'school_id' => School::factory(),
            'issued_at' => now(),
            'billed_to_name' => fake()->company(),
            'billed_to_email' => fake()->safeEmail(),
            'billed_to_phone' => fake()->phoneNumber(),
            'plan_name' => fake()->randomElement(['Basic', 'Standard', 'Exclusive']),
            'billing_cycle' => 'Per Term',
            'description' => 'Subscription',
            'licences' => $licences,
            'unit_price' => $unitPrice,
            'currency' => 'NGN',
            'subtotal' => $total,
            'discount' => 0,
            'fees' => 0,
            'total' => $total,
        ];
    }
}
