<?php

use App\Enums\ExamTerm;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultAcademicStructure;

test('a round total prints in full and a decimal average gets its own band\'s grade', function () {
    $school = School::factory()->create();
    DefaultAcademicStructure::seedFor($school);
    activateSchool($school);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'term' => ExamTerm::First]);

    // Exam marks 40 + 60 + 50 + 50 = 200 (the old code printed "2"); totals 59.2, 60, 60, 60 -> average 59.8% (graded E before).
    foreach ([['Mathematics', 19.2, 40], ['English', 0, 60], ['Science', 10, 50], ['Civic', 10, 50]] as [$name, $test, $exam]) {
        $subject = ExaminationSubject::create(['examination_id' => $examination->id, 'name' => $name, 'max_score' => 100]);
        ExaminationScore::factory()->create([
            'examination_subject_id' => $subject->id, 'student_id' => $student->id,
            'test_score' => $test, 'exam_score' => $exam, 'score' => $test + $exam, 'grade_override' => null,
        ]);
    }

    $html = $this->actingAs($admin)->get(route('results.print', [$examination, $student]))->assertOk()->getContent();

    expect($html)->toMatch('/>\s*200\s*<\/td>/')  // exam column total
        ->and($html)->toContain('239.2/400')     // marks total
        ->and($html)->toContain('59.8%')
        ->and($html)->toContain('C (GOOD)')      // not E
        ->and($html)->not->toContain('E (NEEDS IMPROVEMENT)');
});
