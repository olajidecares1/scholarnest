<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\CbtTestStatus;
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
use App\Services\DocumentExtraction\ExtractionResult;
use App\Services\DocumentExtraction\QuestionExtractionProvider;
use App\Services\ExtractedQuestion;
use App\Services\ExtractedQuestionSet;

/**
 * A well-formed extraction payload, to be bent out of shape per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function extractedQuestion(array $overrides = []): array
{
    return [
        'number' => 1,
        'question_text' => 'What is the capital of Nigeria?',
        'has_diagram' => false,
        'diagram_description' => null,
        'answer_source' => 'found_in_document',
        'correct_label' => 'B',
        'options' => [
            ['label' => 'A', 'text' => 'Lagos'],
            ['label' => 'B', 'text' => 'Abuja'],
            ['label' => 'C', 'text' => 'Kano'],
            ['label' => 'D', 'text' => 'Ibadan'],
        ],
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->school = School::factory()->create();

    $plan = Plan::firstOrCreate(
        ['key' => PlanKey::Standard],
        Plan::factory()->make(['key' => PlanKey::Standard])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $this->school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $this->teacher = Staff::factory()->create([
        'school_id' => $this->school->id,
        'role' => StaffRole::Teacher,
    ]);
});

// -----------------------------------------------------------------------------
// A sound question survives extraction intact.
// -----------------------------------------------------------------------------

test('a well-formed question keeps its text, options and answer', function () {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion(), 1);

    expect($question->text)->toBe('What is the capital of Nigeria?')
        ->and($question->options)->toHaveCount(4)
        ->and($question->correctLabel)->toBe('B')
        ->and($question->isUsable())->toBeTrue()
        ->and($question->hasCorrectAnswer())->toBeTrue()
        ->and($question->needsReview())->toBeFalse()
        ->and($question->reviewNotes())->toBeNull();

    $correct = collect($question->options)->firstWhere('is_correct', true);
    expect($correct['label'])->toBe('B')->and($correct['text'])->toBe('Abuja');
});

test('option order is preserved exactly as printed', function () {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion(), 1);

    expect(array_column($question->options, 'text'))
        ->toBe(['Lagos', 'Abuja', 'Kano', 'Ibadan']);
});

test('the document numbering is kept, and position fills in when it is missing', function () {
    expect(ExtractedQuestion::fromExtraction(extractedQuestion(['number' => 17]), 3)->number)->toBe(17)
        ->and(ExtractedQuestion::fromExtraction(extractedQuestion(['number' => 0]), 3)->number)->toBe(3);
});

// -----------------------------------------------------------------------------
// The silent failure this work exists to remove.
// -----------------------------------------------------------------------------

test('an answer key naming an option the question does not have is caught, not silently dropped', function () {
    // Previously: no option matched, so none was stored correct, and the
    // question was NOT flagged - because an answer key had after all been
    // found. It published looking ordinary and marked every student wrong.
    $question = ExtractedQuestion::fromExtraction(extractedQuestion(['correct_label' => 'E']), 1);

    expect($question->hasCorrectAnswer())->toBeFalse()
        ->and($question->needsReview())->toBeTrue()
        ->and($question->reviewNotes())->toContain('answer key gives E')
        ->and($question->reviewNotes())->toContain('A, B, C, D');

    expect(collect($question->options)->where('is_correct', true))->toBeEmpty();
});

test('no question is ever stored with more than one correct option', function () {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion(), 1);

    expect(collect($question->options)->where('is_correct', true))->toHaveCount(1);
});

// -----------------------------------------------------------------------------
// Labels as documents actually print them.
// -----------------------------------------------------------------------------

test('labels printed with brackets, dots or in lower case still match the answer key', function (string $label, string $key) {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion([
        'correct_label' => $key,
        'options' => [
            ['label' => $label, 'text' => 'Lagos'],
            ['label' => 'B', 'text' => 'Abuja'],
        ],
    ]), 1);

    expect($question->correctLabel)->toBe('A')
        ->and($question->needsReview())->toBeFalse();
})->with([
    ['A.', 'A'],
    ['(A)', 'a'],
    ['a)', 'A.'],
    [' A ', '(A)'],
]);

// -----------------------------------------------------------------------------
// Structurally broken questions.
// -----------------------------------------------------------------------------

test('a question that lost its options is unusable and says so', function () {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion(['options' => []]), 1);

    expect($question->isUsable())->toBeFalse()
        ->and($question->needsReview())->toBeTrue()
        ->and($question->reviewNotes())->toContain('at least 2');
});

test('a question with only one option is unusable', function () {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion([
        'options' => [['label' => 'A', 'text' => 'Lagos']],
    ]), 1);

    expect($question->isUsable())->toBeFalse();
});

test('a question with no text is unusable', function () {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion(['question_text' => '   ']), 1);

    expect($question->isUsable())->toBeFalse()
        ->and($question->reviewNotes())->toContain('No question text');
});

test('two options sharing a label are reported rather than silently merged', function () {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion([
        'options' => [
            ['label' => 'A', 'text' => 'Lagos'],
            ['label' => 'B', 'text' => 'Abuja'],
            ['label' => 'B', 'text' => 'Kano'],
        ],
    ]), 1);

    expect($question->options)->toHaveCount(2)
        ->and($question->needsReview())->toBeTrue()
        ->and($question->reviewNotes())->toContain('both labelled B');
});

test('an empty option is dropped and counted against the minimum', function () {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion([
        'options' => [
            ['label' => 'A', 'text' => 'Lagos'],
            ['label' => 'B', 'text' => '   '],
        ],
    ]), 1);

    expect($question->options)->toHaveCount(1)
        ->and($question->isUsable())->toBeFalse();
});

test('an unlabelled option is given a label rather than discarded', function () {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion([
        'correct_label' => null,
        'answer_source' => 'not_found',
        'options' => [
            ['label' => '', 'text' => 'Lagos'],
            ['label' => 'B', 'text' => 'Abuja'],
        ],
    ]), 1);

    expect($question->options)->toHaveCount(2)
        ->and($question->options[0]['label'])->toBe('A')
        ->and($question->reviewNotes())->toContain('no label');
});

// -----------------------------------------------------------------------------
// Absent answer keys are normal, not errors.
// -----------------------------------------------------------------------------

test('a question with no answer key is structurally fine but needs the teacher to choose', function () {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion([
        'answer_source' => 'not_found',
        'correct_label' => null,
    ]), 1);

    expect($question->isUsable())->toBeTrue()
        ->and($question->hasCorrectAnswer())->toBeFalse()
        ->and($question->needsReview())->toBeTrue()
        ->and($question->reviewNotes())->toContain('select it manually');
});

test('a diagram question is flagged so the image can be attached', function () {
    $question = ExtractedQuestion::fromExtraction(extractedQuestion([
        'has_diagram' => true,
        'diagram_description' => 'a triangle labelled PQR',
    ]), 1);

    expect($question->needsReview())->toBeTrue()
        ->and($question->reviewNotes())->toContain('triangle labelled PQR');
});

// -----------------------------------------------------------------------------
// Marks.
// -----------------------------------------------------------------------------

test('marks are taken from the paper, defaulting to one', function () {
    expect(ExtractedQuestion::fromExtraction(extractedQuestion(['marks' => 3]), 1)->marks)->toBe(3)
        ->and(ExtractedQuestion::fromExtraction(extractedQuestion(), 1)->marks)->toBe(1)
        ->and(ExtractedQuestion::fromExtraction(extractedQuestion(['marks' => 0]), 1)->marks)->toBe(1);
});

// -----------------------------------------------------------------------------
// Judging a document as a whole.
// -----------------------------------------------------------------------------

test('a document of sound questions is acceptable', function () {
    $set = ExtractedQuestionSet::fromExtraction([
        'questions' => [extractedQuestion(), extractedQuestion(), extractedQuestion()],
    ]);

    expect($set->isAcceptable())->toBeTrue()
        ->and($set->count())->toBe(3)
        ->and($set->needingReviewCount())->toBe(0)
        ->and($set->rejectionReason())->toBeNull();
});

test('a document where most questions are broken is rejected outright', function () {
    $set = ExtractedQuestionSet::fromExtraction([
        'questions' => [
            extractedQuestion(),
            extractedQuestion(['options' => []]),
            extractedQuestion(['options' => []]),
            extractedQuestion(['question_text' => '']),
        ],
    ]);

    expect($set->isAcceptable())->toBeFalse()
        ->and($set->rejectionReason())->toContain('Only 1 of the 4 questions')
        ->and($set->rejectionReason())->toContain('Nothing has been added');
});

test('a document that yielded no questions is rejected with advice', function () {
    $set = ExtractedQuestionSet::fromExtraction(['questions' => []]);

    expect($set->isAcceptable())->toBeFalse()
        ->and($set->rejectionReason())->toContain('No questions could be read');
});

test('a document with a minority of bad questions is kept, with those flagged', function () {
    $set = ExtractedQuestionSet::fromExtraction([
        'questions' => [
            extractedQuestion(),
            extractedQuestion(),
            extractedQuestion(),
            extractedQuestion(['correct_label' => 'Z']),
        ],
    ]);

    expect($set->isAcceptable())->toBeTrue()
        ->and($set->needingReviewCount())->toBe(1);
});

// -----------------------------------------------------------------------------
// End to end through the job.
// -----------------------------------------------------------------------------

test('a document becomes real, answerable CBT questions', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
    ]);

    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $this->teacher->id,
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    $payload = [
        'instructions' => 'Answer ALL questions. Shade your answers in HB pencil.',
        'questions' => [
            extractedQuestion(['number' => 1, 'question_text' => 'Capital of Nigeria?', 'marks' => 2]),
            extractedQuestion(['number' => 2, 'question_text' => 'Largest continent?', 'correct_label' => 'C']),
        ],
    ];

    $this->mock(QuestionExtractionProvider::class)
        ->shouldReceive('extract')->once()->andReturn(new ExtractionResult(
            questions: $payload['questions'],
            instructions: $payload['instructions'],
        ));

    (new ProcessCbtTestDocumentUpload($upload))->handle(app(QuestionExtractionProvider::class));

    $upload->refresh();
    $test->refresh();

    expect($upload->status)->toBe(CbtDocumentUploadStatus::Completed)
        ->and($upload->questions_extracted_count)->toBe(2)
        ->and($upload->questions_needing_review_count)->toBe(0)
        ->and($test->instructions)->toBe('Answer ALL questions. Shade your answers in HB pencil.');

    $questions = $test->questions()->with('options')->orderBy('sort_order')->get();

    expect($questions)->toHaveCount(2)
        ->and($questions[0]->question_text)->toBe('Capital of Nigeria?')
        ->and($questions[0]->marks)->toBe(2)
        ->and($questions[0]->options)->toHaveCount(4)
        ->and($questions[0]->options->firstWhere('is_correct', true)->label)->toBe('B')
        ->and($questions[1]->options->firstWhere('is_correct', true)->label)->toBe('C');
});

test('a document that cannot be read adds nothing to the test and explains why', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
    ]);

    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $this->teacher->id,
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    $this->mock(QuestionExtractionProvider::class)
        ->shouldReceive('extract')->once()->andReturn(new ExtractionResult(questions: [
            extractedQuestion(['options' => []]),
            extractedQuestion(['options' => []]),
            extractedQuestion(['question_text' => '']),
        ]));

    (new ProcessCbtTestDocumentUpload($upload))->handle(app(QuestionExtractionProvider::class));

    $upload->refresh();

    expect($upload->status)->toBe(CbtDocumentUploadStatus::Failed)
        ->and($upload->error_message)->toContain('could be read properly')
        ->and($test->questions()->count())->toBe(0);
});

// -----------------------------------------------------------------------------
// The publish gate: review happens before students, not after.
// -----------------------------------------------------------------------------

test('a test whose questions have no correct answer cannot be published', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
    ]);

    CbtTestQuestion::factory()->create(['cbt_test_id' => $test->id]);

    expect($test->canBePublished())->toBeFalse()
        ->and($test->publishBlocker())->toContain('no correct answer marked');

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'published'])
        ->assertStatus(422);

    expect($test->fresh()->status)->not->toBe(CbtTestStatus::Published);
});

test('a test with questions still flagged for review cannot be published', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
    ]);

    CbtTestQuestion::factory()->answerable()->create([
        'cbt_test_id' => $test->id,
        'needs_review' => true,
        'review_notes' => 'Diagram referenced.',
    ]);

    expect($test->publishBlocker())->toContain('need review');

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'published'])
        ->assertStatus(422);
});

test('a fully reviewed test publishes', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
    ]);

    CbtTestQuestion::factory()->answerable()->count(3)->create(['cbt_test_id' => $test->id]);

    expect($test->canBePublished())->toBeTrue();

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.status', [$this->school, $test]), ['status' => 'published'])
        ->assertRedirect();

    expect($test->fresh()->status)->toBe(CbtTestStatus::Published);
});

test('editing a flagged question clears its flag, so a fixed test can publish', function () {
    $test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
    ]);

    $question = CbtTestQuestion::factory()->create([
        'cbt_test_id' => $test->id,
        'needs_review' => true,
        'review_notes' => 'The answer key gives E, but this question only has options A, B.',
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.cbt.tests.questions.update', [$this->school, $test, $question]), [
            'question_text' => 'Capital of Nigeria?',
            'options' => ['Lagos', 'Abuja'],
            'correct_index' => 1,
        ])
        ->assertRedirect();

    $question->refresh();

    expect($question->needs_review)->toBeFalse()
        ->and($question->review_notes)->toBeNull()
        ->and($test->fresh()->canBePublished())->toBeTrue();
});
