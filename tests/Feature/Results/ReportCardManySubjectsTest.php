<?php

use App\Enums\ExamTerm;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\RepositoryResult;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultAcademicStructure;
use App\Support\DownloadName;

/**
 * A report card is one A4 page. A class with many subjects must still fit on
 * it, with the remarks and signatures, rather than run onto a second page or,
 * on screen, be cut off at the bottom of the sheet.
 */
function manySubjectsCard(int $subjects, string $admissionNumber = 'TGA-001'): array
{
    $school = School::factory()->create(['name' => 'Triple Glory Academy', 'contact_address' => fake()->unique()->streetAddress().', Abeokuta', 'contact_phone' => '+234 814 127 9663']);
    DefaultAcademicStructure::seedFor($school);
    activateSchool($school);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'is_active' => true, 'admission_number' => $admissionNumber]);
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'term' => ExamTerm::First]);
    $examination->subjects()->delete();

    foreach (range(1, $subjects) as $i) {
        $subject = ExaminationSubject::create(['examination_id' => $examination->id, 'name' => "Subject {$i}", 'max_score' => 100]);
        ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $student->id, 'test_score' => 30, 'exam_score' => 40, 'score' => 70]);
    }

    return [$admin, $examination, $student];
}

function pdfPageCount(string $pdf): int
{
    return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
}

test('the downloaded report card stays on one page however many subjects there are', function (int $subjects) {
    [$admin, $examination, $student] = manySubjectsCard($subjects);

    $pdf = $this->actingAs($admin)->get(route('results.pdf', [$examination, $student]))->assertOk()->getContent();

    expect(pdfPageCount($pdf))->toBe(1);
})->with([10, 14, 18, 22, 26, 30]);

test('the on-screen card tightens its marks table as subjects are added', function () {
    [$admin, $examination, $student] = manySubjectsCard(10);
    expect($this->actingAs($admin)->get(route('results.print', [$examination, $student]))->getContent())->toContain('py-[5px]');

    [$admin, $examination, $student] = manySubjectsCard(20);
    expect($this->actingAs($admin)->get(route('results.print', [$examination, $student]))->getContent())
        ->toContain('Subject 20')
        ->not->toContain('py-[5px]');
});

test('a report card downloads when the admission number carries the session, slashes and all', function () {
    [$admin, $examination, $student] = manySubjectsCard(4, 'TGA-2026/2027-JUR-005');

    $response = $this->actingAs($admin)->get(route('results.pdf', [$examination, $student]))->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('report-card-TGA-2026-2027-JUR-005.pdf');
});

test('a download name keeps only what a file name can hold', function () {
    expect(DownloadName::pdf('report-card', 'TGA-2026/2027-JUR-005'))->toBe('report-card-TGA-2026-2027-JUR-005.pdf')
        ->and(DownloadName::pdf('result', 'A\\B  C'))->toBe('result-A-B-C.pdf')
        ->and(DownloadName::pdf('id-card', ''))->toBe('id-card.pdf');
});

test('a card pushed to the Result Repository keeps every subject and fits the same way', function () {
    [$admin, $examination, $student] = manySubjectsCard(20, 'TGA-2026/2027-JUR-005');

    $this->actingAs($admin)->post(route('results.push', [$examination, $student]));
    $stored = RepositoryResult::query()->where('student_id', $student->id)->firstOrFail();

    $response = $this->actingAs($admin)->getJson(route('result-repository.show', $stored))->assertOk();

    expect($response->json('report_card_html'))->toContain('Subject 1<')
        ->toContain('Subject 20')
        ->toContain('TGA-2026/2027-JUR-005')
        ->not->toContain('py-[5px]')
        ->and($response->json('details_html'))->toContain('Subject 20');
});
