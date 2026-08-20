<?php

namespace Database\Factories;

use App\Enums\TeacherAssignmentType;
use App\Models\School;
use App\Models\Staff;
use App\Models\TeacherAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAssignment>
 */
class TeacherAssignmentFactory extends Factory
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
            'staff_id' => Staff::factory(),
            'type' => TeacherAssignmentType::ClassTeacher,
            'class_name' => 'JSS 1',
            'subject' => null,
        ];
    }

    public function subjectTeacher(string $subject = 'Mathematics'): static
    {
        return $this->state([
            'type' => TeacherAssignmentType::SubjectTeacher,
            'subject' => $subject,
        ]);
    }
}
