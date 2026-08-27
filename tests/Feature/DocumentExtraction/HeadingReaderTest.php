<?php

use App\Services\DocumentExtraction\HeadingReader;
use App\Services\DocumentExtraction\QuestionParser;

/**
 * A paper with a heading, questions, and a key at the end.
 */
function paperWithKey(): string
{
    return <<<'TXT'
    GREENFIELD COLLEGE
    First Term Examination

    Subject: Mathematics
    Class: JSS 2
    Session: 2025/2026

    Answer all questions.

    1. What is 2 + 2?
    A. 3
    B. 4
    C. 5
    D. 6

    2. What is 5 x 3?
    A. 8
    B. 12
    C. 15
    D. 18

    3. What is 10 - 4?
    A. 4
    B. 5
    C. 6
    D. 7

    Answer Key:
    1. B
    2. C
    3. C
    TXT;
}

// ---------------------------------------------------------------------------
// What the paper says it is
// ---------------------------------------------------------------------------

test('the heading is read off the paper', function () {
    $metadata = app(HeadingReader::class)->metadata(paperWithKey());

    expect($metadata->subject)->toBe('Mathematics')
        ->and($metadata->className)->toBe('JSS 2')
        ->and($metadata->session)->toBe('2025/2026')
        ->and($metadata->term)->toBe('first')
        ->and($metadata->title)->toBe('First Term Examination');
});

test('a session written without a label is still found', function () {
    $metadata = app(HeadingReader::class)->metadata("Mock Test\n2026/2027\n\n1. A question?\nA. One\nB. Two");

    expect($metadata->session)->toBe('2026/2027');
});

test('a paper with no heading yields nothing rather than a guess', function () {
    $metadata = app(HeadingReader::class)->metadata("1. What is 2 + 2?\nA. 3\nB. 4");

    expect($metadata->isEmpty())->toBeTrue()
        ->and($metadata->found())->toBe([]);
});

test('a class mentioned inside a passage is not mistaken for the heading', function () {
    // The heading is at the top. Reading the whole document would find this.
    $text = "Subject: English\n\n1. Read the passage.\n"
        .str_repeat("The class gathered by the river.\n", 40)
        ."Class: SSS 3\n";

    expect(app(HeadingReader::class)->metadata($text)->className)->toBeNull();
});

// ---------------------------------------------------------------------------
// The answer key at the end
// ---------------------------------------------------------------------------

test('a key at the end fills in the correct answers', function () {
    $text = paperWithKey();

    $parsed = app(QuestionParser::class)->parse(app(HeadingReader::class)->bodyOf($text));

    // Nothing beside the questions themselves, so the parser found none.
    expect(collect($parsed['questions'])->pluck('correct_label')->filter())->toBeEmpty();

    $withKey = app(HeadingReader::class)->applyAnswerKey($parsed['questions'], $text);

    expect(collect($withKey)->pluck('correct_label')->all())->toBe(['B', 'C', 'C'])
        ->and($withKey[0]['answer_source'])->toBe('found_in_answer_key');
});

test('an answer beside the question wins over the key', function () {
    // Keys are typed separately and drift when questions are reordered. The
    // answer printed against the question is the more specific statement.
    $text = <<<'TXT'
    1. What is 2 + 2?
    A. 3
    B. 4
    Answer: B

    Answer Key:
    1. A
    TXT;

    $parsed = app(QuestionParser::class)->parse(app(HeadingReader::class)->bodyOf($text));
    $withKey = app(HeadingReader::class)->applyAnswerKey($parsed['questions'], $text);

    expect($withKey[0]['correct_label'])->toBe('B')
        ->and($withKey[0]['answer_source'])->toBe('found_in_document');
});

test('a key naming an option the question does not have is left alone', function () {
    $text = <<<'TXT'
    1. What is 2 + 2?
    A. 3
    B. 4

    Answer Key:
    1. G
    TXT;

    $parsed = app(QuestionParser::class)->parse(app(HeadingReader::class)->bodyOf($text));
    $withKey = app(HeadingReader::class)->applyAnswerKey($parsed['questions'], $text);

    // A drifted key is a question for a person, not something to write in as
    // though it were certain.
    expect($withKey[0]['correct_label'])->toBeNull()
        ->and($withKey[0]['answer_source'])->toBe('not_found');
});

test('the questions themselves are never mistaken for a key', function () {
    // "1. What is..." followed by "A. ..." looks like a key entry if you read
    // the whole document. The key is searched only after its own heading.
    $text = "1. What is 2 + 2?\nA. 3\nB. 4\n\n2. What is 3 + 3?\nA. 5\nB. 6";

    $parsed = app(QuestionParser::class)->parse(app(HeadingReader::class)->bodyOf($text));
    $withKey = app(HeadingReader::class)->applyAnswerKey($parsed['questions'], $text);

    expect(collect($withKey)->pluck('correct_label')->filter())->toBeEmpty();
});

test('a paper with no key comes back unchanged', function () {
    $text = "1. What is 2 + 2?\nA. 3\nB. 4";

    $parsed = app(QuestionParser::class)->parse(app(HeadingReader::class)->bodyOf($text));

    expect(app(HeadingReader::class)->applyAnswerKey($parsed['questions'], $text))
        ->toBe($parsed['questions']);
});
