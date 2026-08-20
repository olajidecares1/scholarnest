<?php

namespace Database\Factories;

use App\Models\SchoolNotice;
use App\Models\SchoolNoticeRead;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolNoticeRead>
 */
class SchoolNoticeReadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_notice_id' => SchoolNotice::factory(),
            'student_id' => Student::factory(),
            'read_at' => now(),
        ];
    }
}
