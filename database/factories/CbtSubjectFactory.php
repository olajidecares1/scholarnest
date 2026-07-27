<?php

namespace Database\Factories;

use App\Enums\CbtSubjectCategory;
use App\Models\CbtSubject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CbtSubject>
 */
class CbtSubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'category' => fake()->randomElement(CbtSubjectCategory::cases()),
        ];
    }
}
