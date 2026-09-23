<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Jobs\ProcessCbtDocumentUpload;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtSubject;
use App\Models\User;
use App\Services\CbtDocumentImportService;
use App\Services\DocumentExtraction\ExtractionResult;
use App\Services\DocumentExtraction\QuestionExtractionProvider;
use App\Services\DocumentExtraction\QuestionParser;

/**
 * The layouts past-question documents are actually sold and shared in.
 *
 * Every case here was read wrongly before: an answer line like "Correct
 * Answer: Option B" was not recognised, so it was glued onto option D (where
 * a student could read it) and the question had no correct option at all; a
 * question tagged "(JAMB 2010)" was filed under the year of upload; and a
 * paper that prints "Question 1" on its own line produced no questions.
 */
function parsePastQuestions(string $text): array
{
    return app(QuestionParser::class)->parse($text)['questions'];
}

test('the answer line underneath a question is read in every wording the papers use', function (string $line) {
    $questions = parsePastQuestions("1. Evaluate 2 + 3\nA. 4\nB. 5\nC. 6\nD. 7\n{$line}");

    expect($questions[0]['correct_label'])->toBe('B')
        // And never left in the last option, where a student would see it.
        ->and($questions[0]['options'][3]['text'])->toBe('7');
})->with([
    ['Correct Answer: Option B'],
    ['Answer: Option B'],
    ['The correct answer is B'],
    ['Correct option: B'],
    ['Correct Option: (B) 5'],
    ['Answer: B. 5'],
    ['ANSWER: B) 5'],
    ['Answer: B (5)'],
    ['Answer: Option B - 5'],
    ['Correct Answer is: B'],
    ['Answer is B'],
]);

test('an answer welded onto the last option is taken off it', function () {
    $questions = parsePastQuestions("1. Evaluate 2 + 3\nA. 4\nB. 5\nC. 6\nD. 7 Correct Answer: Option B");

    expect($questions[0]['options'][3]['text'])->toBe('7')
        ->and($questions[0]['correct_label'])->toBe('B');
});

test('an instruction that merely starts like an answer is not read as one', function () {
    $questions = parsePastQuestions("1. Evaluate 2 + 3\nA. 4\nB. 5\nC. 6\nD. 7\nSolution: Add the two numbers together");

    expect($questions[0]['correct_label'])->toBeNull();
});

test('a question number on its own line is still a question', function () {
    $questions = parsePastQuestions(<<<'TXT'
    Question 1
    Evaluate 2 + 3
    A. 4
    B. 5
    C. 6
    D. 7
    Correct Answer: Option B
    Explanation: 2 + 3 = 5

    Question 2
    What is 3 x 3?
    A. 6
    B. 9
    C. 12
    D. 3
    Correct Answer: Option B
    TXT);

    expect($questions)->toHaveCount(2)
        ->and($questions[0]['question_text'])->toBe('Evaluate 2 + 3')
        ->and($questions[0]['explanation'])->toBe('2 + 3 = 5')
        ->and($questions[1]['question_text'])->toBe('What is 3 x 3?')
        ->and($questions[1]['correct_label'])->toBe('B');
});

test('a question tagged with its paper is filed under that year, and the tag is taken out', function (string $tagged, string $clean, int $year) {
    $questions = parsePastQuestions("Mathematics Past Questions\n1. {$tagged}\nA. 4\nB. 5\nAnswer: B");

    expect($questions[0]['year'])->toBe($year)
        ->and($questions[0]['question_text'])->toBe($clean);
})->with([
    ['Evaluate 2 + 3 (JAMB 2010)', 'Evaluate 2 + 3', 2010],
    ['Evaluate 2 + 3 [UTME 2011]', 'Evaluate 2 + 3', 2011],
    ['[2012] Evaluate 2 + 3', 'Evaluate 2 + 3', 2012],
    ['Evaluate 2 + 3 - WAEC 2013', 'Evaluate 2 + 3', 2013],
]);

test('a compilation with a heading per year keeps each year apart, answers and all', function () {
    $questions = parsePastQuestions(<<<'TXT'
    JAMB MATHEMATICS 2010
    1. Evaluate 2 + 3
    A. 4
    B. 5
    C. 6
    D. 7
    Correct Answer: Option B

    JAMB MATHEMATICS 2011
    1. Evaluate 3 x 3
    A. 6
    B. 9
    C. 12
    D. 3
    Answer: B. 9
    TXT);

    expect(collect($questions)->pluck('year')->all())->toBe([2010, 2011])
        ->and(collect($questions)->pluck('correct_label')->all())->toBe(['B', 'B']);
});

/**
 * Run the upload job over questions a fake extractor hands it.
 *
 * @param  list<array<string, mixed>>  $questions
 */
function importPastQuestions(array $questions, array $upload = []): CbtDocumentUpload
{
    app()->instance(QuestionExtractionProvider::class, new class($questions) implements QuestionExtractionProvider
    {
        public function __construct(private array $questions) {}

        public function extract(string $absolutePath, ?string $mimeType = null): ExtractionResult
        {
            return new ExtractionResult($this->questions);
        }
    });

    $record = CbtDocumentUpload::factory()->create([
        'uploaded_by' => User::factory()->create()->id,
        'cbt_exam_body_id' => CbtExamBody::factory()->create()->id,
        'cbt_subject_id' => CbtSubject::factory()->create(['name' => 'Mathematics'])->id,
        'status' => CbtDocumentUploadStatus::Pending,
        ...$upload,
    ]);

    (new ProcessCbtDocumentUpload($record))->handle(app(QuestionExtractionProvider::class), app(CbtDocumentImportService::class));

    return $record->fresh();
}

function undatedQuestion(): array
{
    return parsePastQuestions("1. Evaluate 2 + 3\nA. 4\nB. 5\nAnswer: B")[0];
}

test('a paper that never prints its year is filed under the year the uploader gave', function () {
    $upload = importPastQuestions([undatedQuestion()], ['year' => 2015, 'original_filename' => 'maths.docx']);

    expect(CbtExam::pluck('year')->all())->toBe([2015])
        ->and($upload->detected_years)->toBe([2015]);
});

test('without a year from the uploader, the one in the file name is used', function () {
    importPastQuestions([undatedQuestion()], ['original_filename' => 'JAMB Mathematics 2014 Past Questions.docx']);

    expect(CbtExam::pluck('year')->all())->toBe([2014]);
});

test('the document\'s own years always win over the fallback', function () {
    $questions = parsePastQuestions("JAMB 2010\n1. One\nA. x\nB. y\nAnswer: A\n\nJAMB 2011\n1. Two\nA. x\nB. y\nAnswer: B");

    importPastQuestions($questions, ['year' => 2015, 'original_filename' => 'maths 2019.docx']);

    expect(CbtExam::orderBy('year')->pluck('year')->all())->toBe([2010, 2011]);
});

test('filing under the year of upload is said out loud', function () {
    $upload = importPastQuestions([undatedQuestion()], ['original_filename' => 'maths.docx']);

    expect(collect($upload->warnings)->join(' '))->toContain('carry no examination year');
});
