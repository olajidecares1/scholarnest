<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessCbtDocumentUpload;
use App\Jobs\ProcessCbtTestDocumentUpload;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtSubject;
use App\Models\CbtTest;
use App\Models\CbtTestDocumentUpload;
use App\Models\CbtTestQuestion;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CbtDocumentImportService;
use App\Services\DocumentExtraction\QuestionExtractionProvider;
use Illuminate\Support\Facades\Storage;

/**
 * The real thing: whole JAMB past-question compilations, read end to end.
 *
 * HOW TO RUN THESE. The papers themselves are not in the repository (they are
 * somebody else's compilation, and they run to megabytes), so these tests skip
 * unless the documents are sitting in tests/Fixtures/jamb and the reading is
 * asked for:
 *
 *     JAMB_FIXTURES=1 php artisan test tests/Feature/DocumentExtraction/JambPastQuestionTest.php
 *
 * They are slow on purpose. A sixty-page two-column PDF is read position by
 * position, and that is the case worth proving: the fast tests around them use
 * documents built in the test, which can only ever be as awkward as the test
 * imagined.
 *
 * Numbers are asserted as floors, never exact counts. A better reader should
 * make these pass more comfortably, not fail.
 */
function jambPaper(string $name): string
{
    $path = base_path('tests/Fixtures/jamb/'.$name);

    if (env('JAMB_FIXTURES') !== '1' || ! is_file($path)) {
        test()->markTestSkipped("Put {$name} in tests/Fixtures/jamb and set JAMB_FIXTURES=1 to run this.");
    }

    return $path;
}

test('a Literature past-question compilation is read year by year', function () {
    $result = app(QuestionExtractionProvider::class)->extract(jambPaper('literature-in-english.docx'));

    $questions = collect($result->questions);
    $years = $questions->pluck('year')->filter()->unique()->sort()->values();

    expect($questions->count())->toBeGreaterThanOrEqual(420)
        ->and($years->all())->toBe([2010, 2011, 2012, 2013, 2014, 2015, 2016, 2017, 2018])

        // Every year is a full paper, not a handful of questions the reader
        // happened to find before losing its place.
        ->and($questions->groupBy('year')->map->count()->min())->toBeGreaterThanOrEqual(40)

        // The subject comes from the document's own headings.
        ->and($questions->pluck('subject')->filter()->unique()->all())->toBe(['Literature in English'])

        // Answer keys, which are printed per year and used to be dropped for
        // every year after the first.
        ->and($questions->filter(fn (array $q) => filled($q['correct_label']))->count() / $questions->count())->toBeGreaterThan(0.95)

        // Passages, kept with the questions that name them.
        ->and($questions->filter(fn (array $q) => filled($q['passage']))->count())->toBeGreaterThan(200);

    // Four lettered options is what a JAMB question has.
    $fourOptions = $questions->filter(fn (array $q) => count($q['options']) === 4)->count();
    expect($fourOptions / $questions->count())->toBeGreaterThan(0.85);
});

test('a two-column Use of English PDF is read in printed order', function () {
    $result = app(QuestionExtractionProvider::class)->extract(jambPaper('use-of-english.pdf'), 'application/pdf');

    $questions = collect($result->questions);
    $years = $questions->pluck('year')->filter()->unique()->sort()->values();

    expect($result->looksScanned)->toBeFalse()
        ->and($questions->count())->toBeGreaterThanOrEqual(700)
        ->and($years->all())->toBe([2010, 2011, 2012, 2013, 2014, 2015, 2016, 2017, 2018])
        ->and($questions->groupBy('year')->map->count()->min())->toBeGreaterThanOrEqual(45);

    // The first question of the first year, read whole: the test that fails
    // the moment the two columns are braided together again.
    $first = $questions->first();

    expect($first['number'])->toBe(1)
        ->and($first['year'])->toBe(2010)
        ->and(count($first['options']))->toBe(4);

    $fourOptions = $questions->filter(fn (array $q) => count($q['options']) === 4)->count();
    expect($fourOptions / $questions->count())->toBeGreaterThan(0.8);

    // Comprehension passages, which are most of this paper.
    expect($questions->filter(fn (array $q) => filled($q['passage']))->count())->toBeGreaterThan(400);
})->group('slow');

test('a Mathematics compilation keeps its formulae and its five options', function () {
    $result = app(QuestionExtractionProvider::class)->extract(jambPaper('mathematics.pdf'), 'application/pdf');

    $questions = collect($result->questions);
    $years = $questions->pluck('year')->filter()->unique()->sort()->values();

    expect($questions->count())->toBeGreaterThanOrEqual(600)
        ->and($years->first())->toBe(1983)
        ->and($years->last())->toBeGreaterThanOrEqual(2004)
        ->and($years->count())->toBeGreaterThanOrEqual(20);

    // Maths papers of this period offer five options, A to E.
    $lettered = $questions->filter(fn (array $q) => count($q['options']) >= 4)->count();
    expect($lettered / $questions->count())->toBeGreaterThan(0.7);

    // Raised exponents survive as exponents rather than as a stray line
    // containing "2", which is what welded x² into "x" and "2".
    $withPowers = $questions->filter(fn (array $q) => preg_match('/[⁰¹²³⁴⁵⁶⁷⁸⁹ⁿ°]/u', $q['question_text']) === 1);
    expect($withPowers->count())->toBeGreaterThan(20);
})->group('slow');

test('a real compilation uploaded by a teacher becomes one test per year', function () {
    $path = jambPaper('literature-in-english.docx');

    Storage::fake('local');
    Storage::fake('public');

    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    $teacher = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher]);
    $test = CbtTest::factory()->create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'title' => 'JAMB Literature Practice',
    ]);

    $stored = 'cbt-test-uploads/documents/jamb-literature.docx';
    Storage::disk('local')->put($stored, file_get_contents($path));

    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $teacher->id,
        'path' => $stored,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    (new ProcessCbtTestDocumentUpload($upload))->handle(app(QuestionExtractionProvider::class));

    $tests = CbtTest::where('school_id', $school->id)->orderBy('source_year')->get();

    expect($upload->fresh()->status)->toBe(CbtDocumentUploadStatus::Completed)
        ->and($tests)->toHaveCount(9)
        ->and($tests->pluck('source_year')->all())->toBe([2010, 2011, 2012, 2013, 2014, 2015, 2016, 2017, 2018])

        // A year's paper, not a year's worth of everything.
        ->and($tests->map(fn (CbtTest $test) => $test->questions()->count())->min())->toBeGreaterThanOrEqual(40)
        ->and($tests->map(fn (CbtTest $test) => $test->questions()->count())->max())->toBeLessThanOrEqual(60);

    // The wording is the question, not the question plus its options plus the
    // answer, which is what the old reader stored.
    $questions = CbtTestQuestion::whereIn('cbt_test_id', $tests->pluck('id'))->with('options')->get();

    expect($questions->filter(fn (CbtTestQuestion $q) => str_contains($q->question_text, 'Correct Answer')))->toHaveCount(0)
        ->and($questions->filter(fn (CbtTestQuestion $q) => str_contains($q->question_text, '&#')))->toHaveCount(0)
        ->and($questions->filter(fn (CbtTestQuestion $q) => $q->options->count() === 4)->count() / $questions->count())->toBeGreaterThan(0.85);

    $first = $tests->first()->questions()->with('options')->first();

    expect($first->question_text)->toBe('Which literature in English Question Paper Type is given to you?')
        ->and($first->options->pluck('option_text')->all())->toBe(['Type A', 'Type B', 'Type C', 'Type D'])
        ->and($first->options->firstWhere('is_correct', true)->label)->toBe('A');
});

test('a real compilation imports as published-ready papers, one per year', function () {
    $path = jambPaper('literature-in-english.docx');

    Storage::fake('local');
    Storage::fake('public');

    $stored = 'cbt-uploads/documents/jamb-literature.docx';
    Storage::disk('local')->put($stored, file_get_contents($path));

    $examBody = CbtExamBody::factory()->create(['code' => 'JAMB', 'name' => 'JAMB']);
    $subject = CbtSubject::factory()->create(['name' => 'Literature in English']);
    $uploader = User::factory()->create();

    $upload = CbtDocumentUpload::factory()->create([
        'uploaded_by' => $uploader->id,
        'cbt_exam_body_id' => $examBody->id,
        'cbt_subject_id' => $subject->id,
        'original_filename' => 'literature-in-english.docx',
        'path' => $stored,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    (new ProcessCbtDocumentUpload($upload))->handle(
        app(QuestionExtractionProvider::class),
        app(CbtDocumentImportService::class),
    );

    $upload->refresh();

    expect($upload->status)->toBe(CbtDocumentUploadStatus::Completed)
        ->and($upload->detected_years)->toContain(2010, 2018)
        ->and($upload->questions_extracted_count)->toBeGreaterThan(400);

    // One exam per year, and not one question visible to a student yet.
    $exams = CbtExam::where('cbt_exam_body_id', $examBody->id)->get();

    expect($exams)->toHaveCount(9)
        ->and($exams->sum(fn (CbtExam $exam) => $exam->questions()->count()))->toBe($upload->questions_extracted_count)
        ->and($exams->sum(fn (CbtExam $exam) => $exam->publishedQuestions()->count()))->toBe(0);

    // Reading the same document a second time adds nothing.
    $before = $upload->questions()->count();
    (new ProcessCbtDocumentUpload($upload->fresh()))->handle(
        app(QuestionExtractionProvider::class),
        app(CbtDocumentImportService::class),
    );

    expect(CbtExam::where('cbt_exam_body_id', $examBody->id)->get()->sum(fn (CbtExam $exam) => $exam->questions()->count()))
        ->toBe($before);

    // Published, the AkademicNest Team's decision, and only then do students
    // see anything.
    app(CbtDocumentImportService::class)->publish($upload->fresh());

    expect(CbtExam::where('cbt_exam_body_id', $examBody->id)->get()->sum(fn (CbtExam $exam) => $exam->publishedQuestions()->count()))
        ->toBeGreaterThan(300);
});
