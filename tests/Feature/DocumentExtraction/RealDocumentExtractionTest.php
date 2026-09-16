<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessCbtTestDocumentUpload;
use App\Models\CbtTest;
use App\Models\CbtTestDocumentUpload;
use App\Models\CbtTestQuestion;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Subscription;
use App\Services\DocumentExtraction\QuestionExtractionProvider;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * These build genuine .docx and .pdf binaries and run the real parsers over
 * them. Mocks prove the wiring; only real files prove that PHPWord and
 * pdfparser actually read what a teacher uploads.
 */

/**
 * The lines of a question paper with $count questions.
 *
 * @return list<string>
 */
function paperLines(int $count, string $style = 'dot'): array
{
    $lines = ['GREENFIELD COLLEGE', 'Answer ALL questions. Time allowed: 45 minutes.'];

    for ($n = 1; $n <= $count; $n++) {
        $lines[] = "{$n}. Question number {$n}: what is the capital of Nigeria?";

        foreach ([['A', 'Lagos'], ['B', 'Abuja'], ['C', 'Kano'], ['D', 'Ibadan']] as [$label, $text]) {
            $lines[] = match ($style) {
                'bracket' => "{$label}) {$text}",
                'paren' => '('.strtolower($label).") {$text}",
                default => "{$label}. {$text}",
            };
        }

        $lines[] = 'Answer: B';
    }

    return $lines;
}

/**
 * @param  list<string>  $lines
 */
function writeTestDocx(string $path, array $lines): string
{
    $word = new PhpWord;
    $section = $word->addSection();

    foreach ($lines as $line) {
        $section->addText($line);
    }

    // The storage folder is git-ignored, so on a fresh checkout (CI) it does
    // not exist yet, and PhpWord cannot close a zip into a missing folder.
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    IOFactory::createWriter($word, 'Word2007')->save($path);

    return $path;
}

/**
 * @param  list<string>  $lines
 */
function writeTestPdf(string $path, array $lines): string
{
    $html = '<html><body style="font-family:sans-serif;font-size:11pt">';

    foreach ($lines as $line) {
        $html .= '<div>'.htmlspecialchars($line).'</div>';
    }

    file_put_contents($path, Pdf::loadHTML($html.'</body></html>')->output());

    return $path;
}

function scratchPath(string $name): string
{
    $dir = sys_get_temp_dir().'/akademicnest-extraction-tests';

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir.'/'.uniqid().'-'.$name;
}

// -----------------------------------------------------------------------------
// Real Word documents.
// -----------------------------------------------------------------------------

test('a real DOCX is read, in order, with its answers', function () {
    $path = writeTestDocx(scratchPath('paper.docx'), paperLines(5));

    $result = app(QuestionExtractionProvider::class)->extract($path);

    expect($result->questions)->toHaveCount(5)
        ->and(array_column($result->questions, 'number'))->toBe([1, 2, 3, 4, 5])
        ->and($result->questions[0]['options'])->toHaveCount(4)
        ->and($result->questions[0]['correct_label'])->toBe('B')
        ->and($result->instructions)->toContain('Answer ALL questions');
});

test('a long real DOCX is read in full', function () {
    // 120 questions, the size of an actual JAMB paper.
    $path = writeTestDocx(scratchPath('long.docx'), paperLines(120, 'bracket'));

    $result = app(QuestionExtractionProvider::class)->extract($path);

    expect($result->questions)->toHaveCount(120)
        ->and(array_column($result->questions, 'number'))->toBe(range(1, 120))
        ->and(collect($result->questions)->every(fn ($q) => count($q['options']) === 4))->toBeTrue()
        ->and(collect($result->questions)->every(fn ($q) => $q['correct_label'] === 'B'))->toBeTrue();
});

test('a real DOCX using the (a) style is read', function () {
    $path = writeTestDocx(scratchPath('paren.docx'), paperLines(8, 'paren'));

    $result = app(QuestionExtractionProvider::class)->extract($path);

    expect($result->questions)->toHaveCount(8)
        ->and($result->questions[0]['options'][1])->toBe(['label' => 'B', 'text' => 'Abuja']);
});

// -----------------------------------------------------------------------------
// Real PDFs.
// -----------------------------------------------------------------------------

test('a real PDF with selectable text is read', function () {
    $path = writeTestPdf(scratchPath('paper.pdf'), paperLines(5));

    $result = app(QuestionExtractionProvider::class)->extract($path, 'application/pdf');

    expect($result->questions)->toHaveCount(5)
        ->and($result->looksScanned)->toBeFalse()
        ->and($result->questions[0]['correct_label'])->toBe('B');
});

test('a real multi-page PDF is read across its pages', function () {
    $path = writeTestPdf(scratchPath('multipage.pdf'), paperLines(60));

    $result = app(QuestionExtractionProvider::class)->extract($path, 'application/pdf');

    expect($result->questions)->toHaveCount(60)
        ->and(array_column($result->questions, 'number'))->toBe(range(1, 60));
});

// -----------------------------------------------------------------------------
// Files that are not what they claim.
// -----------------------------------------------------------------------------

test('a corrupted DOCX explains itself rather than throwing', function () {
    // Found by running real files: PHPWord throws on a non-zip, and an
    // unhandled throw here means a corrupt Word file behaves worse than a
    // corrupt PDF.
    $path = scratchPath('broken.docx');
    file_put_contents($path, 'not a zip archive');

    $result = app(QuestionExtractionProvider::class)->extract($path);

    expect($result->questions)->toBe([])
        ->and($result->failureReason())->toContain('could not be opened')
        ->and($result->failureReason())->not->toContain('error code');
});

test('a corrupted PDF explains itself rather than throwing', function () {
    $path = scratchPath('broken.pdf');
    file_put_contents($path, 'this is not a pdf at all');

    $result = app(QuestionExtractionProvider::class)->extract($path, 'application/pdf');

    expect($result->questions)->toBe([])
        ->and($result->failureReason())->toContain('stored safely');
});

test('an empty document yields nothing and says what to check', function () {
    $path = writeTestDocx(scratchPath('empty.docx'), ['']);

    $result = app(QuestionExtractionProvider::class)->extract($path);

    expect($result->questions)->toBe([])
        ->and($result->failureReason())->toContain('Try extracting again');
});

test('a document with prose but no questions yields nothing', function () {
    $path = writeTestDocx(scratchPath('letter.docx'), [
        'A letter to parents about the mid-term break.',
        'There are no questions in this document at all.',
    ]);

    $result = app(QuestionExtractionProvider::class)->extract($path);

    expect($result->questions)->toBe([]);
});

// -----------------------------------------------------------------------------
// The whole job, on a real file, with no network.
// -----------------------------------------------------------------------------

test('a real document travels the whole pipeline into CBT questions', function () {
    $school = School::factory()->create();

    $plan = Plan::firstOrCreate(
        ['key' => PlanKey::Standard],
        Plan::factory()->make(['key' => PlanKey::Standard])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $teacher = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher]);
    $test = CbtTest::factory()->create(['school_id' => $school->id, 'staff_id' => $teacher->id]);

    // A real .docx, stored where the job will look for it.
    $storedPath = 'cbt-test-uploads/documents/'.uniqid().'.docx';
    writeTestDocx(Storage::disk('local')->path($storedPath), paperLines(10));

    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $teacher->id,
        'path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    (new ProcessCbtTestDocumentUpload($upload))->handle(app(QuestionExtractionProvider::class));

    $upload->refresh();

    expect($upload->status)->toBe(CbtDocumentUploadStatus::Completed)
        ->and($upload->questions_extracted_count)->toBe(10);

    $questions = $test->questions()->with('options')->orderBy('sort_order')->get();

    expect($questions)->toHaveCount(10)
        ->and($questions[0]->question_text)->toContain('Question number 1')
        ->and($questions[0]->options)->toHaveCount(4)
        ->and($questions[0]->options->firstWhere('is_correct', true)->label)->toBe('B')
        ->and($questions[9]->question_text)->toContain('Question number 10');
});

test('extracting the same upload twice does not double the questions', function () {
    // "Try extracting again" is a button a teacher will press twice.
    $school = School::factory()->create();

    $plan = Plan::firstOrCreate(
        ['key' => PlanKey::Standard],
        Plan::factory()->make(['key' => PlanKey::Standard])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $teacher = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher]);
    $test = CbtTest::factory()->create(['school_id' => $school->id, 'staff_id' => $teacher->id]);

    $storedPath = 'cbt-test-uploads/documents/'.uniqid().'.docx';
    writeTestDocx(Storage::disk('local')->path($storedPath), paperLines(6));

    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $teacher->id,
        'path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    (new ProcessCbtTestDocumentUpload($upload))->handle(app(QuestionExtractionProvider::class));

    // Re-queued the way the retry button does it.
    $upload->update(['status' => CbtDocumentUploadStatus::Pending]);
    (new ProcessCbtTestDocumentUpload($upload->fresh()))->handle(app(QuestionExtractionProvider::class));

    expect($test->questions()->count())->toBe(6)
        ->and($upload->fresh()->questions_extracted_count)->toBe(6);
});

test('a retry replaces only its own questions, never the teacher\'s own', function () {
    $school = School::factory()->create();

    $plan = Plan::firstOrCreate(
        ['key' => PlanKey::Standard],
        Plan::factory()->make(['key' => PlanKey::Standard])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $teacher = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher]);
    $test = CbtTest::factory()->create(['school_id' => $school->id, 'staff_id' => $teacher->id]);

    // Typed by hand, belonging to no upload.
    $handWritten = CbtTestQuestion::factory()->answerable()->create([
        'cbt_test_id' => $test->id,
        'question_text' => 'Typed by the teacher.',
    ]);

    $storedPath = 'cbt-test-uploads/documents/'.uniqid().'.docx';
    writeTestDocx(Storage::disk('local')->path($storedPath), paperLines(4));

    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $teacher->id,
        'path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    (new ProcessCbtTestDocumentUpload($upload))->handle(app(QuestionExtractionProvider::class));

    $upload->update(['status' => CbtDocumentUploadStatus::Pending]);
    (new ProcessCbtTestDocumentUpload($upload->fresh()))->handle(app(QuestionExtractionProvider::class));

    expect($test->questions()->count())->toBe(5)
        ->and(CbtTestQuestion::find($handWritten->id))->not->toBeNull();
});

test('a second job on an upload already processing declines rather than duplicating', function () {
    $school = School::factory()->create();

    $plan = Plan::firstOrCreate(
        ['key' => PlanKey::Standard],
        Plan::factory()->make(['key' => PlanKey::Standard])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $teacher = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher]);
    $test = CbtTest::factory()->create(['school_id' => $school->id, 'staff_id' => $teacher->id]);

    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $teacher->id,
        'status' => CbtDocumentUploadStatus::Processing,
    ]);

    (new ProcessCbtTestDocumentUpload($upload))->handle(app(QuestionExtractionProvider::class));

    // Untouched: no questions, and the status left exactly as it was.
    expect($test->questions()->count())->toBe(0)
        ->and($upload->fresh()->status)->toBe(CbtDocumentUploadStatus::Processing);
});
