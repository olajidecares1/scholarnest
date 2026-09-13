<?php

namespace Database\Factories;

use App\Enums\JobApplicationStatus;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'school_id' => fn (array $attributes) => JobPosting::find($attributes['job_posting_id'])->school_id,
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0803'.fake()->numerify('#######'),
            'address' => fake()->address(),
            'cover_letter' => fake()->paragraphs(2, true),
            'qualifications' => 'B.Ed. Mathematics',
            'years_of_experience' => fake()->numberBetween(0, 15),
            'cv_path' => JobApplication::DIRECTORY.'/'.fake()->uuid().'.pdf',
            'cv_original_name' => 'cv.pdf',
            'cv_mime_type' => 'application/pdf',
            'cv_size_bytes' => 20480,
            'status' => JobApplicationStatus::New,
        ];
    }
}
