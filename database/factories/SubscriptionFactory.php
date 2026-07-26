<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
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
            'plan_id' => Plan::factory(),
            'billing_cycle' => BillingCycle::PerTerm,
            'students_count' => null,
            'amount' => 200000,
            'currency' => 'NGN',
            'status' => SubscriptionStatus::PendingPayment,
            'reference' => strtoupper(Str::random(12)),
        ];
    }
}
