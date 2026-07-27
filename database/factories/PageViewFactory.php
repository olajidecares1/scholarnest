<?php

namespace Database\Factories;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => fake()->randomElement(['/', '/login', '/register', '/report-misconduct']),
            'referrer_host' => null,
            'traffic_source' => fake()->randomElement(['direct', 'search', 'social', 'referral']),
            'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'ip_address' => fake()->ipv4(),
            'viewed_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
