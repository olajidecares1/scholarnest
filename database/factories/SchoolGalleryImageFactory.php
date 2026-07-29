<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SchoolGalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolGalleryImage>
 */
class SchoolGalleryImageFactory extends Factory
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
            'image_path' => 'gallery/'.fake()->uuid().'.jpg',
            'caption' => fake()->optional()->sentence(3),
            'sort_order' => 0,
        ];
    }
}
