<?php

use App\Services\DocumentExtraction\QuestionParser;

function parseQuestions(string $text): array
{
    return app(QuestionParser::class)->parse($text);
}

// -----------------------------------------------------------------------------
// The ordinary case.
// -----------------------------------------------------------------------------

test('a plainly formatted question is read whole', function () {
    $result = parseQuestions(<<<'TXT'
    1. What is the capital of Nigeria?
    A. Lagos
    B. Abuja
    C. Kano
    D. Ibadan
    Answer: B
    TXT);

    expect($result['questions'])->toHaveCount(1);

    $q = $result['questions'][0];

    expect($q['number'])->toBe(1)
        ->and($q['question_text'])->toBe('What is the capital of Nigeria?')
        ->and($q['options'])->toHaveCount(4)
        ->and($q['options'][0])->toBe(['label' => 'A', 'text' => 'Lagos'])
        ->and($q['options'][1])->toBe(['label' => 'B', 'text' => 'Abuja'])
        ->and($q['correct_label'])->toBe('B')
        ->and($q['answer_source'])->toBe('found_in_document');
});

// -----------------------------------------------------------------------------
// The variations a real stack of papers actually contains.
// -----------------------------------------------------------------------------

test('option labels are read in every common style', function (string $body) {
    $result = parseQuestions("1. Capital of Nigeria?\n".$body."\nAnswer: B");

    expect($result['questions'][0]['options'])->toHaveCount(4)
        ->and($result['questions'][0]['options'][1]['text'])->toBe('Abuja')
        ->and($result['questions'][0]['correct_label'])->toBe('B');
})->with([
    'dotted' => ["A. Lagos\nB. Abuja\nC. Kano\nD. Ibadan"],
    'bracketed' => ["A) Lagos\nB) Abuja\nC) Kano\nD) Ibadan"],
    'parenthesised lower' => ["(a) Lagos\n(b) Abuja\n(c) Kano\n(d) Ibadan"],
    'dashed' => ["A - Lagos\nB - Abuja\nC - Kano\nD - Ibadan"],
    'colon' => ["A: Lagos\nB: Abuja\nC: Kano\nD: Ibadan"],
]);

test('the answer key is read in every common wording', function (string $line) {
    $result = parseQuestions("1. Capital?\nA. Lagos\nB. Abuja\n".$line);

    expect($result['questions'][0]['correct_label'])->toBe('B');
})->with([
    ['Answer: B'],
    ['Correct Answer: B'],
    ['Ans: B'],
    ['Ans - B'],
    ['Correct: B'],
    ['ANSWER: b'],
    ['Answer: (B)'],
    ['Key: B'],
]);

test('question numbering is read in every common style', function (string $opener) {
    $result = parseQuestions($opener."\nA. Lagos\nB. Abuja\nAnswer: A");

    expect($result['questions'])->toHaveCount(1)
        ->and($result['questions'][0]['number'])->toBe(7);
})->with([
    ['7. Capital?'],
    ['7) Capital?'],
    ['Q7. Capital?'],
    ['Question 7. Capital?'],
    ['7 - Capital?'],
]);

// -----------------------------------------------------------------------------
// Order, which must never be rearranged.
// -----------------------------------------------------------------------------

test('questions keep the order the document printed them in', function () {
    $text = '';

    foreach (range(1, 12) as $n) {
        $text .= "{$n}. Question number {$n}?\nA. One\nB. Two\nAnswer: A\n";
    }

    $result = parseQuestions($text);

    expect($result['questions'])->toHaveCount(12)
        ->and(array_column($result['questions'], 'number'))->toBe(range(1, 12))
        ->and($result['questions'][5]['question_text'])->toBe('Question number 6?');
});

test('options keep the order they were printed in', function () {
    $result = parseQuestions("1. Pick?\nA. Alpha\nB. Bravo\nC. Charlie\nD. Delta\nAnswer: C");

    expect(array_column($result['questions'][0]['options'], 'text'))
        ->toBe(['Alpha', 'Bravo', 'Charlie', 'Delta']);
});

// -----------------------------------------------------------------------------
// Text that wraps, which is how documents actually arrive.
// -----------------------------------------------------------------------------

test('a question wrapped across lines is joined back together', function () {
    $result = parseQuestions(<<<'TXT'
    1. Which of the following best describes the process by which
    green plants manufacture their own food using sunlight?
    A. Respiration
    B. Photosynthesis
    Answer: B
    TXT);

    expect($result['questions'][0]['question_text'])
        ->toBe('Which of the following best describes the process by which green plants manufacture their own food using sunlight?');
});

test('an option wrapped across lines is joined back together', function () {
    $result = parseQuestions(<<<'TXT'
    1. Define photosynthesis.
    A. The process by which plants convert light energy
    into chemical energy stored as glucose
    B. Something else
    Answer: A
    TXT);

    expect($result['questions'][0]['options'][0]['text'])
        ->toBe('The process by which plants convert light energy into chemical energy stored as glucose')
        ->and($result['questions'][0]['options'])->toHaveCount(2);
});

// -----------------------------------------------------------------------------
// Things that must not be mistaken for structure.
// -----------------------------------------------------------------------------

test('a year inside a sentence does not start a new question', function () {
    $result = parseQuestions("1. Nigeria gained independence in 1960 from which country?\nA. France\nB. Britain\nAnswer: B");

    expect($result['questions'])->toHaveCount(1)
        ->and($result['questions'][0]['question_text'])->toContain('1960');
});

test('a rubric before the first question is not read as an option', function () {
    $result = parseQuestions(<<<'TXT'
    GREENFIELD COLLEGE
    Answer ALL questions. Time allowed: 45 minutes.
    1. Capital of Nigeria?
    A. Lagos
    B. Abuja
    Answer: B
    TXT);

    expect($result['questions'])->toHaveCount(1)
        ->and($result['questions'][0]['options'])->toHaveCount(2)
        ->and($result['instructions'])->toContain('Answer ALL questions');
});

test('the school name alone is not treated as a rubric', function () {
    $result = parseQuestions("GREENFIELD COLLEGE\n1. Capital?\nA. Lagos\nB. Abuja\nAnswer: B");

    expect($result['instructions'])->toBeNull();
});

// -----------------------------------------------------------------------------
// Papers with no answer key, which are ordinary.
// -----------------------------------------------------------------------------

test('a question with no printed answer is kept, marked as having none', function () {
    $result = parseQuestions("1. Capital?\nA. Lagos\nB. Abuja\nC. Kano\nD. Ibadan");

    expect($result['questions'])->toHaveCount(1)
        ->and($result['questions'][0]['correct_label'])->toBeNull()
        ->and($result['questions'][0]['answer_source'])->toBe('not_found')
        ->and($result['questions'][0]['options'])->toHaveCount(4);
});

test('a paper mixing answered and unanswered questions keeps both', function () {
    $result = parseQuestions(<<<'TXT'
    1. First?
    A. Yes
    B. No
    Answer: A
    2. Second?
    A. Yes
    B. No
    TXT);

    expect($result['questions'])->toHaveCount(2)
        ->and($result['questions'][0]['correct_label'])->toBe('A')
        ->and($result['questions'][1]['correct_label'])->toBeNull();
});

// -----------------------------------------------------------------------------
// Extras the papers carry.
// -----------------------------------------------------------------------------

test('a mark allocation is lifted out of the wording', function () {
    $result = parseQuestions("1. Capital? [2 marks]\nA. Lagos\nB. Abuja\nAnswer: B");

    expect($result['questions'][0]['marks'])->toBe(2)
        ->and($result['questions'][0]['question_text'])->toBe('Capital?')
        ->and($result['questions'][0]['question_text'])->not->toContain('marks');
});

test('an explanation is captured when the paper prints one', function () {
    $result = parseQuestions(<<<'TXT'
    1. Capital?
    A. Lagos
    B. Abuja
    Answer: B
    Explanation: Abuja became the capital in 1991.
    TXT);

    expect($result['questions'][0]['explanation'])->toBe('Abuja became the capital in 1991.');
});

test('a question referring to a diagram is flagged for a person', function () {
    $result = parseQuestions("1. From the diagram above, find angle x.\nA. 30\nB. 60\nAnswer: A");

    expect($result['questions'][0]['has_diagram'])->toBeTrue()
        ->and($result['questions'][0]['diagram_description'])->not->toBeNull();
});

// -----------------------------------------------------------------------------
// Degenerate input must not throw.
// -----------------------------------------------------------------------------

test('empty text yields no questions rather than an error', function () {
    expect(parseQuestions('')['questions'])->toBe([])
        ->and(parseQuestions("   \n\n  ")['questions'])->toBe([]);
});

test('prose with no questions in it yields none', function () {
    $result = parseQuestions("This is just a letter to parents about the mid-term break.\nIt contains no questions at all.");

    expect($result['questions'])->toBe([]);
});

test('a long paper is parsed in full', function () {
    // 120 questions, which is a real JAMB-sized paper.
    $text = '';

    foreach (range(1, 120) as $n) {
        $label = ['A', 'B', 'C', 'D'][$n % 4];
        $text .= "{$n}. Question {$n}?\nA. One\nB. Two\nC. Three\nD. Four\nAnswer: {$label}\n";
    }

    $result = parseQuestions($text);

    expect($result['questions'])->toHaveCount(120)
        ->and($result['questions'][119]['number'])->toBe(120)
        ->and(collect($result['questions'])->every(fn ($q) => count($q['options']) === 4))->toBeTrue();
});

// -----------------------------------------------------------------------------
// Multi-year compilations, which is how past-question papers arrive.
// -----------------------------------------------------------------------------

test('a year heading assigns the questions that follow it', function () {
    $result = parseQuestions(<<<'TXT'
    JAMB 2018
    1. First from 2018?
    A. Yes
    B. No
    Answer: A
    JAMB 2019
    1. First from 2019?
    A. Yes
    B. No
    Answer: B
    TXT);

    expect($result['questions'])->toHaveCount(2)
        ->and($result['questions'][0]['year'])->toBe(2018)
        ->and($result['questions'][0]['question_text'])->toBe('First from 2018?')
        ->and($result['questions'][1]['year'])->toBe(2019)
        ->and($result['questions'][1]['question_text'])->toBe('First from 2019?');
});

test('a question above a heading keeps the year it was printed under', function () {
    // The boundary case: the last question of one paper sits immediately above
    // the next paper's heading.
    $result = parseQuestions(<<<'TXT'
    2010
    1. Belongs to 2010?
    A. Yes
    B. No
    2011
    1. Belongs to 2011?
    A. Yes
    B. No
    TXT);

    expect($result['questions'][0]['year'])->toBe(2010)
        ->and($result['questions'][1]['year'])->toBe(2011);
});

test('a bare year line is not read as a question', function () {
    $result = parseQuestions("2019\n1. Capital?\nA. Lagos\nB. Abuja\nAnswer: B");

    expect($result['questions'])->toHaveCount(1)
        ->and($result['questions'][0]['question_text'])->toBe('Capital?');
});

test('a document with no year headings leaves the year unset', function () {
    $result = parseQuestions("1. Capital?\nA. Lagos\nB. Abuja\nAnswer: B");

    expect($result['questions'][0]['year'])->toBeNull();
});

test('a year inside a question does not become a heading', function () {
    // Long lines are prose. Only a short line that is essentially just a year
    // counts as a heading.
    $result = parseQuestions("1. In which year did Nigeria gain independence from Britain, 1960 or thereabouts?\nA. 1960\nB. 1963\nAnswer: A");

    expect($result['questions'])->toHaveCount(1)
        ->and($result['questions'][0]['year'])->toBeNull();
});

// -----------------------------------------------------------------------------
// Two-column option layouts, flattened by PDF extraction.
// -----------------------------------------------------------------------------

test('two options printed side by side become two options', function () {
    // Found on a real JAMB paper: printed in two columns, and a PDF has no
    // columns, so each row arrives as one line carrying two options. Read
    // naively, a four-choice question arrives with two.
    $result = parseQuestions(<<<'TXT'
    2. Assigning revenues to the period in which goods were sold is known as
    A. passing of entries B. consistency convention
    C. matching concept D. adjusting for revenue
    TXT);

    $options = $result['questions'][0]['options'];

    expect($options)->toHaveCount(4)
        ->and($options[0])->toBe(['label' => 'A', 'text' => 'passing of entries'])
        ->and($options[1])->toBe(['label' => 'B', 'text' => 'consistency convention'])
        ->and($options[2])->toBe(['label' => 'C', 'text' => 'matching concept'])
        ->and($options[3])->toBe(['label' => 'D', 'text' => 'adjusting for revenue']);
});

test('four options on a single line become four options', function () {
    $result = parseQuestions("1. Capital?\nA. Lagos B. Abuja C. Kano D. Ibadan\nAnswer: B");

    expect($result['questions'][0]['options'])->toHaveCount(4)
        ->and($result['questions'][0]['options'][3]['text'])->toBe('Ibadan');
});

test('a split only happens at the next label in sequence', function () {
    // "D." after A does not open a new option, only "B." can. This is what
    // keeps an option whose wording contains a letter and a stop intact.
    $result = parseQuestions("1. Which?\nA. See figure D. below\nB. Something else\nAnswer: B");

    expect($result['questions'][0]['options'])->toHaveCount(2)
        ->and($result['questions'][0]['options'][0]['text'])->toBe('See figure D. below');
});

test('an option is not split when nothing precedes the label', function () {
    $result = parseQuestions("1. Capital?\nA. Lagos\nB. Abuja\nAnswer: B");

    expect($result['questions'][0]['options'])->toHaveCount(2)
        ->and($result['questions'][0]['options'][0]['text'])->toBe('Lagos');
});

test('side-by-side options still wrap correctly onto the next line', function () {
    $result = parseQuestions(<<<'TXT'
    1. Which?
    A. the first choice B. the second choice
    which continues here
    TXT);

    $options = $result['questions'][0]['options'];

    expect($options)->toHaveCount(2)
        ->and($options[1]['text'])->toBe('the second choice which continues here');
});
