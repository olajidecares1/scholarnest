<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookLoan>
 */
class BookLoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'student_id' => Student::factory(),
            'borrowed_at' => now(),
            'due_at' => now()->addWeeks(2),
            'returned_at' => null,
        ];
    }
}
