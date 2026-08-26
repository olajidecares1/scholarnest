<?php

namespace Database\Factories;

use App\Enums\ExamTerm;
use App\Models\Examination;
use App\Models\RepositoryResult;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepositoryResult>
 */
class RepositoryResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory(),
            'examination_id' => Examination::factory(),
            'class_name' => 'JSS 1',
            'session' => '2025/2026',
            'term' => ExamTerm::First,
            'payload' => [
                'student' => [
                    'admission_number' => fake()->bothify('ADM-###'),
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'full_name' => fake()->name(),
                    'gender' => 'male',
                    'date_of_birth' => '2012-04-01',
                    'house' => null,
                    'guardian_name' => null,
                    'photo_path' => null,
                ],
                'examination' => [
                    'name' => 'First Term Examination',
                    'class_name' => 'JSS 1',
                    'session' => '2025/2026',
                    'term' => 'first',
                    'exam_date' => '2026-07-14',
                ],
                'subjects' => [],
                'summary' => [
                    'subjectsGraded' => 0,
                    'averageScore' => null,
                    'average' => null,
                    'position' => null,
                ],
                'attendance' => null,
                'numberInClass' => 1,
                'nextTermBegins' => null,
                'remarks' => ['teacher' => null, 'principal' => null],
                'classTeacher' => null,
            ],
            'source_fingerprint' => hash('sha256', fake()->uuid()),
            'pushed_by_type' => (new User)->getMorphClass(),
            'pushed_by_id' => User::factory(),
            'pushed_by_name' => fake()->name(),
            'pushed_at' => now(),
            'version' => 1,
        ];
    }
}
