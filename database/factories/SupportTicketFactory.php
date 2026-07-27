<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\School;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
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
            'opened_by' => User::factory(),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Medium,
        ];
    }
}
