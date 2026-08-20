<?php

namespace Database\Seeders;

use App\Enums\AcademicStage;
use App\Enums\CbtSubjectCategory;
use App\Models\CbtExamBody;
use App\Models\CbtSubject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CbtSeeder extends Seeder
{
    /**
     * Nigerian secondary/basic-education subjects, grouped by category.
     * This is standard curriculum information (not exam content) and is
     * meant as a sensible starting point - adjust from the Subjects screen
     * as needed.
     *
     * @var array<string, list<string>>
     */
    private const SUBJECTS = [
        'general' => [
            'English Language',
            'Mathematics',
            'Civic Education',
        ],
        'science' => [
            'Physics',
            'Chemistry',
            'Biology',
            'Agricultural Science',
            'Further Mathematics',
            'Health Science',
            'Computer Studies',
        ],
        'arts' => [
            'Literature in English',
            'Government',
            'History',
            'Christian Religious Studies',
            'Islamic Religious Studies',
            'Geography',
            'French',
            'Fine Art',
            'Music',
            'Hausa',
            'Igbo',
            'Yoruba',
            'Arabic',
            'Physical Education',
        ],
        'commercial' => [
            'Economics',
            'Commerce',
            'Financial Accounting',
            'Office Practice',
            'Insurance',
            'Store Management',
            'Marketing',
        ],
        'jss' => [
            'English Studies',
            'Basic Science',
            'Basic Technology',
            'Social Studies',
            'Business Studies',
            'Home Economics',
            'Cultural and Creative Arts',
            'Physical and Health Education',
        ],
    ];

    /**
     * Which of the subjects above each exam body offers, by name.
     *
     * @var array<string, list<string>>
     */
    private const EXAM_BODY_SUBJECTS = [
        'WAEC' => [
            'English Language', 'Mathematics', 'Civic Education', 'Physics', 'Chemistry', 'Biology',
            'Agricultural Science', 'Further Mathematics', 'Computer Studies', 'Literature in English',
            'Government', 'History', 'Christian Religious Studies', 'Islamic Religious Studies', 'Geography',
            'French', 'Fine Art', 'Music', 'Hausa', 'Igbo', 'Yoruba', 'Physical Education', 'Economics',
            'Commerce', 'Financial Accounting', 'Office Practice', 'Insurance', 'Store Management', 'Marketing',
        ],
        'NECO' => [
            'English Language', 'Mathematics', 'Civic Education', 'Physics', 'Chemistry', 'Biology',
            'Agricultural Science', 'Further Mathematics', 'Computer Studies', 'Literature in English',
            'Government', 'History', 'Christian Religious Studies', 'Islamic Religious Studies', 'Geography',
            'French', 'Fine Art', 'Music', 'Hausa', 'Igbo', 'Yoruba', 'Physical Education', 'Economics',
            'Commerce', 'Financial Accounting', 'Office Practice', 'Insurance', 'Store Management', 'Marketing',
        ],
        'JAMB' => [
            'English Language', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Agricultural Science',
            'Further Mathematics', 'Computer Studies', 'Literature in English', 'Government', 'History',
            'Christian Religious Studies', 'Islamic Religious Studies', 'Geography', 'French', 'Fine Art',
            'Music', 'Hausa', 'Igbo', 'Yoruba', 'Arabic', 'Economics', 'Commerce', 'Financial Accounting',
            'Insurance', 'Office Practice', 'Civic Education',
        ],
        'BECE' => [
            'English Studies', 'Mathematics', 'Basic Science', 'Basic Technology', 'Social Studies',
            'Civic Education', 'Christian Religious Studies', 'Islamic Religious Studies', 'Agricultural Science',
            'Business Studies', 'Home Economics', 'French', 'Computer Studies', 'Physical and Health Education',
            'Cultural and Creative Arts', 'History', 'Hausa', 'Igbo', 'Yoruba',
        ],
        'NABTEB' => [
            'English Language', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Agricultural Science',
            'Computer Studies', 'Government', 'Economics', 'Commerce', 'Financial Accounting', 'Office Practice',
            'Insurance', 'Store Management', 'Marketing', 'Civic Education',
        ],
        'JWAEC' => [
            'English Studies', 'Mathematics', 'Basic Science', 'Basic Technology', 'Social Studies',
            'Civic Education', 'Christian Religious Studies', 'Islamic Religious Studies', 'Agricultural Science',
            'Business Studies', 'Home Economics', 'French', 'Computer Studies', 'Physical and Health Education',
            'Cultural and Creative Arts', 'History', 'Hausa', 'Igbo', 'Yoruba',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const EXAM_BODIES = [
        'WAEC' => 'West African Examinations Council - conducts the WASSCE for senior secondary school students.',
        'NECO' => 'National Examinations Council - conducts the SSCE, an alternative senior secondary certificate exam.',
        'JAMB' => 'Joint Admissions and Matriculation Board - conducts the UTME for tertiary institution admission.',
        'BECE' => 'Basic Education Certificate Examination - taken by JSS3 students to complete basic education.',
        'NABTEB' => 'National Business and Technical Examinations Board - conducts NBC/NTC exams for senior secondary technical/business students.',
        'JWAEC' => 'Junior WAEC - the West African Examinations Council\'s exam for junior secondary students.',
    ];

    /**
     * Which academic stage(s) can see each exam body in the student portal.
     *
     * @var array<string, list<string>>
     */
    private const EXAM_BODY_STAGES = [
        'WAEC' => [AcademicStage::SeniorSecondary->value],
        'NECO' => [AcademicStage::SeniorSecondary->value],
        'JAMB' => [AcademicStage::SeniorSecondary->value],
        'BECE' => [AcademicStage::JuniorSecondary->value],
        'NABTEB' => [AcademicStage::SeniorSecondary->value],
        'JWAEC' => [AcademicStage::JuniorSecondary->value],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjectsByName = [];

        foreach (self::SUBJECTS as $category => $names) {
            foreach ($names as $name) {
                $subjectsByName[$name] = CbtSubject::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name, 'category' => CbtSubjectCategory::from($category)]
                );
            }
        }

        foreach (self::EXAM_BODIES as $code => $description) {
            $examBody = CbtExamBody::updateOrCreate(
                ['code' => $code],
                ['name' => $code, 'description' => $description, 'academic_stages' => self::EXAM_BODY_STAGES[$code]]
            );

            $subjectIds = collect(self::EXAM_BODY_SUBJECTS[$code])
                ->map(fn (string $name) => $subjectsByName[$name]->id ?? null)
                ->filter()
                ->all();

            $examBody->subjects()->syncWithoutDetaching($subjectIds);
        }
    }
}
