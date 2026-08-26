<?php

namespace Database\Factories;

use App\Enums\DiaryEntryStatus;
use App\Enums\ExamTerm;
use App\Models\School;
use App\Models\Staff;
use App\Models\TeacherDiaryEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeacherDiaryEntry>
 */
class TeacherDiaryEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'school_id' => School::factory(),
            'staff_id' => Staff::factory(),
            'class_name' => 'JSS 1',
            'subject' => fake()->randomElement(['Mathematics', 'English Language', 'Basic Science']),
            'session' => '2025/2026',
            'term' => ExamTerm::Second->value,
            'week_number' => fake()->numberBetween(1, 14),
            'topic' => fake()->sentence(8),
            'status' => DiaryEntryStatus::Submitted->value,
        ];
    }

    public function seen(): static
    {
        return $this->state(fn () => [
            'status' => DiaryEntryStatus::Seen->value,
            'seen_at' => now(),
        ]);
    }
}
