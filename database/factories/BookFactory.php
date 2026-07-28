<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $copies = fake()->numberBetween(1, 5);

        return [
            'school_id' => School::factory(),
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'isbn' => fake()->optional()->isbn13(),
            'category' => fake()->randomElement(['Fiction', 'Science', 'Mathematics', 'History', 'Reference']),
            'copies_total' => $copies,
            'copies_available' => $copies,
        ];
    }
}
