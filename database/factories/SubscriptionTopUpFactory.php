<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionTopUpStatus;
use App\Models\Subscription;
use App\Models\SubscriptionTopUp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionTopUp>
 */
class SubscriptionTopUpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'additional_students_count' => 50,
            'additional_amount' => 25000,
            'currency' => 'NGN',
            'payment_method' => PaymentMethod::BankTransfer,
            'status' => SubscriptionTopUpStatus::PendingVerification,
            'reference' => strtoupper(Str::random(12)),
        ];
    }
}
