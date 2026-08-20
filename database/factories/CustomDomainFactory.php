<?php

namespace Database\Factories;

use App\Enums\CustomDomainSslStatus;
use App\Enums\CustomDomainStatus;
use App\Models\CustomDomain;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomDomain>
 */
class CustomDomainFactory extends Factory
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
            'domain' => 'www.'.fake()->unique()->domainWord().'.com',
            'is_primary' => true,
            'status' => CustomDomainStatus::PendingVerification,
            'verification_token' => Str::random(32),
            'ssl_status' => CustomDomainSslStatus::Pending,
            'redirect_default_domain' => true,
        ];
    }
}
