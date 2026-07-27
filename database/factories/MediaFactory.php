<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'uploaded_by' => User::factory(),
            'name' => $name,
            'type' => MediaType::Image,
            'disk' => 'public',
            'path' => 'media/'.fake()->uuid().'.jpg',
            'original_filename' => $name.'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 2_000_000),
            'width' => 1920,
            'height' => 1080,
        ];
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'type' => MediaType::Video,
            'path' => 'media/'.fake()->uuid().'.mp4',
            'mime_type' => 'video/mp4',
            'width' => null,
            'height' => null,
        ]);
    }
}
