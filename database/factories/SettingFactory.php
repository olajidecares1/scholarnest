<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_name' => 'EduNest',
            'support_email' => 'support@edunest.test',
            'support_phone' => null,
            'notification_from_name' => 'EduNest',
            'notification_from_email' => 'no-reply@edunest.test',
            'maintenance_mode' => false,
            'maintenance_message' => null,
        ];
    }
}
