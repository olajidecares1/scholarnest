<?php

namespace Database\Factories;

use App\Enums\CbtDocumentUploadStatus;
use App\Models\CbtTest;
use App\Models\CbtTestDocumentUpload;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtTestDocumentUpload>
 */
class CbtTestDocumentUploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cbt_test_id' => CbtTest::factory(),
            'staff_id' => Staff::factory(),
            'original_filename' => fake()->word().'.pdf',
            'disk' => 'local',
            'path' => 'cbt-test-uploads/documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'status' => CbtDocumentUploadStatus::Pending,
        ];
    }
}
