<?php

namespace Database\Factories;

use App\Enums\CbtDocumentUploadStatus;
use App\Models\CbtDocumentUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtDocumentUpload>
 */
class CbtDocumentUploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uploaded_by' => User::factory(),
            'original_filename' => fake()->word().'.pdf',
            'disk' => 'local',
            'path' => 'cbt-uploads/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'status' => CbtDocumentUploadStatus::Pending,
        ];
    }
}
