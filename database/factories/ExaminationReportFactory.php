<?php

namespace Database\Factories;

use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExaminationReport>
 */
class ExaminationReportFactory extends Factory
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
            'examination_id' => Examination::factory(),
            'student_id' => Student::factory(),
            'teacher_remark' => null,
            'principal_remark' => null,
        ];
    }
}
