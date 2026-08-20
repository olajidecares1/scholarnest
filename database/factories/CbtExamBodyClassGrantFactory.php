<?php

namespace Database\Factories;

use App\Models\CbtExamBody;
use App\Models\CbtExamBodyClassGrant;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbtExamBodyClassGrant>
 */
class CbtExamBodyClassGrantFactory extends Factory
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
            'cbt_exam_body_id' => CbtExamBody::factory(),
            'class_name' => 'Primary 4',
            'granted_by' => null,
        ];
    }
}
