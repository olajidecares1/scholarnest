<?php

namespace Database\Factories;

use App\Models\ClassNote;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassNote>
 */
class ClassNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'staff_id' => null,
            'title' => fake()->sentence(4),
            'subject' => fake()->randomElement(['Mathematics', 'English Language', 'Basic Science', 'Social Studies']),
            'description' => fake()->sentence(),
            'path' => 'class-notes/'.fake()->uuid().'.docx',
            'original_name' => 'note.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size_bytes' => fake()->numberBetween(20_000, 400_000),
            'body_text' => fake()->paragraphs(3, true),
        ];
    }

    /**
     * Sent to these classes.
     *
     * @param  list<string>  $classNames
     */
    public function sentTo(array $classNames): static
    {
        return $this->afterCreating(function (ClassNote $note) use ($classNames) {
            $note->classes()->createMany(
                array_map(fn (string $className) => ['class_name' => $className], $classNames),
            );
        });
    }

    /**
     * A legacy .doc, whose text cannot be read out of it.
     */
    public function downloadOnly(): static
    {
        return $this->state(fn () => [
            'original_name' => 'note.doc',
            'mime_type' => 'application/msword',
            'body_text' => null,
        ]);
    }
}
