<?php

namespace Database\Seeders;

use App\Enums\SubjectCategory;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds the platform-wide senior-secondary subject catalogue schools pick
     * from when configuring what each of their classes offers - not tied to
     * any single school. Subjects taught across more than one stream (e.g.
     * Mathematics, Economics) are seeded once as General rather than
     * duplicated per stream.
     */
    public function run(): void
    {
        $subjects = [
            // Science
            'Biology' => SubjectCategory::Science,
            'Chemistry' => SubjectCategory::Science,
            'Physics' => SubjectCategory::Science,
            'Further Mathematics' => SubjectCategory::Science,
            'Health Education' => SubjectCategory::Science,
            'Physical Education' => SubjectCategory::Science,
            'Computer Science' => SubjectCategory::Science,

            // Arts
            'Literature-in-English' => SubjectCategory::Arts,
            'History' => SubjectCategory::Arts,
            'Christian Religious Studies' => SubjectCategory::Arts,
            'Islamic Religious Studies' => SubjectCategory::Arts,
            'French' => SubjectCategory::Arts,
            'Yoruba' => SubjectCategory::Arts,
            'Igbo' => SubjectCategory::Arts,
            'Hausa' => SubjectCategory::Arts,
            'Visual Art' => SubjectCategory::Arts,
            'Music' => SubjectCategory::Arts,
            'Fine Art' => SubjectCategory::Arts,

            // Commercial
            'Commerce' => SubjectCategory::Commercial,
            'Financial Accounting' => SubjectCategory::Commercial,
            'Marketing' => SubjectCategory::Commercial,
            'Office Practice' => SubjectCategory::Commercial,
            'Computer Studies' => SubjectCategory::Commercial,
            'Business Studies' => SubjectCategory::Commercial,
            'Accounting' => SubjectCategory::Commercial,
            'Insurance' => SubjectCategory::Commercial,

            // General (cross-stream core + vocational electives)
            'Mathematics' => SubjectCategory::General,
            'English Language' => SubjectCategory::General,
            'Economics' => SubjectCategory::General,
            'Geography' => SubjectCategory::General,
            'Government' => SubjectCategory::General,
            'Civic Education' => SubjectCategory::General,
            'Data Processing' => SubjectCategory::General,
            'Agricultural Science' => SubjectCategory::General,
            'Technical Drawing' => SubjectCategory::General,
            'Entrepreneurship' => SubjectCategory::General,
            'Catering Craft Practice' => SubjectCategory::General,
            'Home Management' => SubjectCategory::General,
            'Food & Nutrition' => SubjectCategory::General,
            'Clothing & Textiles' => SubjectCategory::General,
            'Building Construction' => SubjectCategory::General,
            'Basic Electricity' => SubjectCategory::General,
            'Basic Electronics' => SubjectCategory::General,
            'Auto Mechanics' => SubjectCategory::General,
            'Woodwork' => SubjectCategory::General,
            'Metalwork' => SubjectCategory::General,
            'Fisheries' => SubjectCategory::General,
            'Animal Husbandry' => SubjectCategory::General,
        ];

        foreach ($subjects as $name => $category) {
            Subject::updateOrCreate(['name' => $name], ['category' => $category]);
        }
    }
}
