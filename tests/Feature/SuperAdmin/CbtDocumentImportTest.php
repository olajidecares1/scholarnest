<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtSubject;
use App\Services\CbtDocumentImportService;

beforeEach(function () {
    $this->examBody = CbtExamBody::factory()->create(['code' => 'WAEC']);
    $this->subject = CbtSubject::factory()->create(['name' => 'Mathematics']);
});

test('importing extracted questions creates one exam per year with correctly marked options', function () {
    $upload = CbtDocumentUpload::factory()->create([
        'ai_response' => [
            'exam_body' => 'WAEC',
            'subject' => 'Mathematics',
            'years' => [
                [
                    'year' => 2015,
                    'questions' => [
                        [
                            'number' => 1,
                            'question_text' => 'What is 2 + 2?',
                            'has_diagram' => false,
                            'diagram_description' => null,
                            'options' => [
                                ['label' => 'A', 'text' => '3'],
                                ['label' => 'B', 'text' => '4'],
                                ['label' => 'C', 'text' => '5'],
                            ],
                            'correct_label' => 'B',
                            'answer_source' => 'found_in_document',
                        ],
                    ],
                ],
                [
                    'year' => 2016,
                    'questions' => [
                        [
                            'number' => 1,
                            'question_text' => 'What is 3 + 3?',
                            'has_diagram' => false,
                            'diagram_description' => null,
                            'options' => [
                                ['label' => 'A', 'text' => '6'],
                                ['label' => 'B', 'text' => '7'],
                            ],
                            'correct_label' => 'A',
                            'answer_source' => 'found_in_document',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $result = app(CbtDocumentImportService::class)->import($upload, $this->examBody, $this->subject);

    expect($result)->toBe(['extracted' => 2, 'needs_review' => 0]);
    expect(CbtExam::where('cbt_exam_body_id', $this->examBody->id)->count())->toBe(2);

    $exam2015 = CbtExam::where('year', 2015)->firstOrFail();
    $question = $exam2015->questions()->firstOrFail();
    expect($question->question_text)->toBe('What is 2 + 2?');
    expect($question->needs_review)->toBeFalse();
    expect($question->correctOption()->option_text)->toBe('4');

    $upload->refresh();
    expect($upload->status)->toBe(CbtDocumentUploadStatus::Completed);
    expect($upload->questions_extracted_count)->toBe(2);
});

test('a question with no answer found in the document is flagged for review', function () {
    $upload = CbtDocumentUpload::factory()->create([
        'ai_response' => [
            'exam_body' => 'WAEC',
            'subject' => 'Mathematics',
            'years' => [
                [
                    'year' => 2015,
                    'questions' => [
                        [
                            'number' => 1,
                            'question_text' => 'Unanswerable question?',
                            'has_diagram' => false,
                            'diagram_description' => null,
                            'options' => [
                                ['label' => 'A', 'text' => 'One'],
                                ['label' => 'B', 'text' => 'Two'],
                            ],
                            'correct_label' => null,
                            'answer_source' => 'not_found',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    app(CbtDocumentImportService::class)->import($upload, $this->examBody, $this->subject);

    $question = CbtExam::where('year', 2015)->firstOrFail()->questions()->firstOrFail();
    expect($question->needs_review)->toBeTrue();
    expect($question->review_notes)->toContain('Correct answer was not found');
    expect($question->correctOption())->toBeNull();

    $upload->refresh();
    expect($upload->questions_needing_review_count)->toBe(1);
});

test('a question referencing a diagram is flagged for review', function () {
    $upload = CbtDocumentUpload::factory()->create([
        'ai_response' => [
            'exam_body' => 'WAEC',
            'subject' => 'Mathematics',
            'years' => [
                [
                    'year' => 2015,
                    'questions' => [
                        [
                            'number' => 1,
                            'question_text' => 'Study the diagram above and answer.',
                            'has_diagram' => true,
                            'diagram_description' => 'A right-angled triangle labelled ABC',
                            'options' => [
                                ['label' => 'A', 'text' => 'One'],
                                ['label' => 'B', 'text' => 'Two'],
                            ],
                            'correct_label' => 'A',
                            'answer_source' => 'found_in_document',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    app(CbtDocumentImportService::class)->import($upload, $this->examBody, $this->subject);

    $question = CbtExam::where('year', 2015)->firstOrFail()->questions()->firstOrFail();
    expect($question->needs_review)->toBeTrue();
    expect($question->review_notes)->toContain('right-angled triangle');
});

test('importing into an existing exam for the same subject and year reuses it', function () {
    $existingExam = CbtExam::factory()->create([
        'cbt_exam_body_id' => $this->examBody->id,
        'cbt_subject_id' => $this->subject->id,
        'year' => 2015,
    ]);

    $upload = CbtDocumentUpload::factory()->create([
        'ai_response' => [
            'exam_body' => 'WAEC',
            'subject' => 'Mathematics',
            'years' => [
                [
                    'year' => 2015,
                    'questions' => [
                        [
                            'number' => 1,
                            'question_text' => 'New question?',
                            'has_diagram' => false,
                            'diagram_description' => null,
                            'options' => [
                                ['label' => 'A', 'text' => 'X'],
                                ['label' => 'B', 'text' => 'Y'],
                            ],
                            'correct_label' => 'A',
                            'answer_source' => 'found_in_document',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    app(CbtDocumentImportService::class)->import($upload, $this->examBody, $this->subject);

    expect(CbtExam::where('cbt_exam_body_id', $this->examBody->id)->where('year', 2015)->count())->toBe(1);
    expect($existingExam->fresh()->questions()->count())->toBe(1);
});
