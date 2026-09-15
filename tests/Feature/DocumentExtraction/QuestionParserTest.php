<?php

use App\Services\DocumentExtraction\QuestionParser;
use App\Services\ExtractedQuestionSet;

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

// -----------------------------------------------------------------------------
// Options printed on the question's own line.
//
// Every pattern in the parser is anchored to the start of a line, but a PDF has
// no lines: it has glyphs at positions. A paper whose text layer flattens a
// question and all of its choices onto one line produced questions with no
// options at all, which made every one of them unusable and failed the document
// as a whole ("only 0 of the 32 questions could be read properly").
// -----------------------------------------------------------------------------

test('a question carrying its options on the same line is read whole', function () {
    $result = parseQuestions('1. Which organelle releases energy? A. Ribosome B. Mitochondrion C. Nucleus D. Golgi body');

    $q = $result['questions'][0];

    expect($q['question_text'])->toBe('Which organelle releases energy?')
        ->and($q['options'])->toHaveCount(4)
        ->and($q['options'][0])->toBe(['label' => 'A', 'text' => 'Ribosome'])
        ->and($q['options'][3])->toBe(['label' => 'D', 'text' => 'Golgi body']);
});

test('inline options are read when the run is set in brackets', function () {
    $result = parseQuestions('1. The basic unit of life is (A) tissue (B) cell (C) organ (D) system');

    $q = $result['questions'][0];

    expect($q['question_text'])->toBe('The basic unit of life is')
        ->and($q['options'])->toHaveCount(4)
        ->and($q['options'][1])->toBe(['label' => 'B', 'text' => 'cell']);
});

test('an answer key still applies to a question whose options were inline', function () {
    $result = parseQuestions("1. Chlorophyll is found in the A. mitochondria B. chloroplast C. ribosome D. nucleus\nAnswer: B");

    expect($result['questions'][0]['options'])->toHaveCount(4)
        ->and($result['questions'][0]['correct_label'])->toBe('B')
        ->and($result['questions'][0]['answer_source'])->toBe('found_in_document');
});

test('a whole paper laid out inline yields usable questions throughout', function () {
    $body = collect(range(1, 12))
        ->map(fn (int $n) => "{$n}. Question number {$n}? A. first B. second C. third D. fourth")
        ->implode("\n");

    $result = parseQuestions($body);

    expect($result['questions'])->toHaveCount(12)
        ->and(collect($result['questions'])->every(fn (array $q) => count($q['options']) === 4))->toBeTrue();
});

test('a question that found its options normally is left alone', function () {
    // The recovery must never touch a question that already parsed, or prose
    // mentioning an initial would be torn into options.
    $result = parseQuestions("1. In 1960 A. Smith wrote about cells. Was he right?\nA. True\nB. False");

    $q = $result['questions'][0];

    expect($q['options'])->toHaveCount(2)
        ->and($q['question_text'])->toBe('In 1960 A. Smith wrote about cells. Was he right?');
});

test('a sentence containing a lone initial is not split into options', function () {
    // No "B." run follows, so there is nothing that looks like a set of
    // choices and the wording is left exactly as printed.
    $result = parseQuestions('1. Name the scientist A. van Leeuwenhoek worked with.');

    expect($result['questions'][0]['options'])->toHaveCount(0)
        ->and($result['questions'][0]['question_text'])->toBe('Name the scientist A. van Leeuwenhoek worked with.');
});

test('a bare list of options with no question above it is not guessed at', function () {
    $result = parseQuestions('1. A. one B. two C. three');

    expect($result['questions'][0]['options'])->toHaveCount(0);
});

// -----------------------------------------------------------------------------
// Lower-case option labels.
//
// A real JSS1 paper: "(a) Snake (b) Python (c) Scratch". The split between one
// option and the next matched only an upper-case label, so the run never split
// at all, option A swallowed B and C, and a three-choice question arrived with
// one. One option is below the minimum, so every question in the paper was
// unusable and the document failed as a whole: "0 of the 32 questions".
// -----------------------------------------------------------------------------

test('a lower-case bracketed run splits into every option', function () {
    $result = parseQuestions("1. Which is not a programming language?\n(a) Snake (b) Python (c) Scratch");

    $options = $result['questions'][0]['options'];

    expect($options)->toHaveCount(3)
        ->and($options[0])->toBe(['label' => 'A', 'text' => 'Snake'])
        ->and($options[1])->toBe(['label' => 'B', 'text' => 'Python'])
        ->and($options[2])->toBe(['label' => 'C', 'text' => 'Scratch']);
});

test('a lower-case run of four options splits into four', function () {
    $result = parseQuestions("1. What can you create?\n(a) Games (b) Stories (c) Animations (d) All of the above");

    expect($result['questions'][0]['options'])->toHaveCount(4)
        ->and($result['questions'][0]['options'][3]['text'])->toBe('All of the above');
});

test('option labels are matched whatever case the paper used', function (string $body) {
    $result = parseQuestions("1. Capital of Nigeria?\n".$body);

    expect($result['questions'][0]['options'])->toHaveCount(3)
        ->and($result['questions'][0]['options'][1]['text'])->toBe('Abuja');
})->with([
    '(a) Lagos (b) Abuja (c) Kano',
    'a. Lagos b. Abuja c. Kano',
    'a) Lagos b) Abuja c) Kano',
    'A. Lagos b. Abuja C. Kano',
]);

// -----------------------------------------------------------------------------
// True/false and yes/no questions.
//
// Teachers mix these freely with lettered questions in one paper. Read as prose
// they gave a question with no options at all, so each one was dropped from the
// imported test without anything being said about it.
// -----------------------------------------------------------------------------

test('a true or false line becomes two options', function () {
    $result = parseQuestions("4. Scratch prepares children for future learning.\nTRUE / FALSE");

    $q = $result['questions'][0];

    expect($q['question_text'])->toBe('Scratch prepares children for future learning.')
        ->and($q['options'])->toBe([
            ['label' => 'A', 'text' => 'True'],
            ['label' => 'B', 'text' => 'False'],
        ]);
});

test('a yes or no line becomes two options', function () {
    $result = parseQuestions("5. Does Scratch improve logical thinking?\nYES / NO");

    expect($result['questions'][0]['options'])->toBe([
        ['label' => 'A', 'text' => 'Yes'],
        ['label' => 'B', 'text' => 'No'],
    ]);
});

test('a true or false pair is read in every common wording', function (string $line) {
    $result = parseQuestions("1. Scratch teaches teamwork.\n".$line);

    expect($result['questions'][0]['options'])->toHaveCount(2);
})->with(['TRUE / FALSE', 'True/False', 'true / false', 'YES / NO', 'Yes/No', 'T/F']);

test('a shorthand true or false pair is written out in full', function () {
    $result = parseQuestions("1. Scratch teaches teamwork.\nT/F");

    expect($result['questions'][0]['options'][0]['text'])->toBe('True')
        ->and($result['questions'][0]['options'][1]['text'])->toBe('False');
});

test('a true or false line before any question is not an orphan question', function () {
    $result = parseQuestions("TRUE / FALSE\n1. Scratch teaches teamwork.\nYES / NO");

    expect($result['questions'])->toHaveCount(1)
        ->and($result['questions'][0]['options'])->toHaveCount(2);
});

test('a true or false line does not overwrite options already read', function () {
    $result = parseQuestions("1. Capital?\nA. Lagos\nB. Abuja\nTRUE / FALSE");

    expect($result['questions'][0]['options'])->toHaveCount(2)
        ->and($result['questions'][0]['options'][0]['text'])->toBe('Lagos');
});

// -----------------------------------------------------------------------------
// The whole document, judged as the importer judges it.
//
// The two bugs above were each invisible on their own: the parser returned 32
// questions and reported no error, and only the usable-ratio check downstream
// turned that into "0 of the 32 questions could be read properly". A test that
// stops at "questions were found" would have passed throughout. This one asks
// the question the importer asks.
// -----------------------------------------------------------------------------

test('a JSS1 paper mixing lower-case, true/false and free-response questions imports', function () {
    // The layout of a paper that failed completely in production.
    $result = parseQuestions(<<<'TXT'
    1. __________ is not a programming language.
    (a) Snake (b) Python (c) Scratch
    2. __________ helps children learn how to think and solve problems.
    (a) Scratch (b) Play (c) AI
    3. Scratch prepares children for future learning in computer technology.
    TRUE / FALSE
    4. Scratch builds confidence when children create their own projects.
    YES / NO
    5. List the three (3) components of Scratch.
    6. __________ teaches the basics of coding in a simple and fun way.
    (a) Scratch (b) Keyboard (c) Monitor
    TXT);

    $set = ExtractedQuestionSet::fromExtraction($result);

    expect($set->count())->toBe(6)
        ->and($set->usable())->toHaveCount(5)
        ->and($set->isAcceptable())->toBeTrue()
        ->and($set->rejectionReason())->toBeNull();
});

// -----------------------------------------------------------------------------
// Welded layouts: a JAMB compilation with no space before its labels.
//
// Taken from the document that imported 430 questions into production with no
// options on any of them. Each question is one line, "...to you?A. Type AB.
// Type BC. Type CD. Type D", and the answer follows on the next line behind a
// tick. Nothing here parsed: no options, no answers, and HTML entities printed
// to candidates verbatim.
// -----------------------------------------------------------------------------

test('a welded question line yields its question and every option', function () {
    $result = parseQuestions('1. Which paper type is given to you?A. Type AB. Type BC. Type CD. Type D');

    $q = $result['questions'][0];

    expect($q['question_text'])->toBe('Which paper type is given to you?')
        ->and($q['options'])->toHaveCount(4)
        ->and($q['options'][0]['text'])->toBe('Type A')
        ->and($q['options'][3]['text'])->toBe('Type D');
});

test('an answer key is read through a tick or bullet in front of it', function (string $line) {
    $result = parseQuestions("1. Capital?A. LagosB. AbujaC. KanoD. Ibadan\n".$line);

    expect($result['questions'][0]['correct_label'])->toBe('B')
        ->and($result['questions'][0]['answer_source'])->toBe('found_in_document');
})->with(['✓ Correct Answer: B', '✔ Answer: B', '• Correct Answer: B', '- Ans: B', '→ Key: B']);

test('html entities are decoded before a candidate ever sees them', function () {
    $result = parseQuestions('1. &quot;If you touch me&quot;, said Don&#039;t who?A. AwereB. MaananC. JamesD. Aaron');

    expect($result['questions'][0]['question_text'])
        ->toBe('"If you touch me", said Don\'t who?');
});

test('a multi-byte character earlier in the line does not truncate an option', function () {
    // The offsets preg_match reports are byte offsets. Slicing them with
    // mb_substr dropped one character per multi-byte character seen earlier,
    // so a paper using curly quotes lost the front of its first option:
    // "Fosuwa and Maidservant" imported as "suwa and Maidservant".
    $result = parseQuestions('2. „I simply don’t understand — who?A. Fosuwa and MaidservantB. Hannah and GeorgeC. Aaron and MaananD. Lawyer B');

    expect($result['questions'][0]['options'][0]['text'])->toBe('Fosuwa and Maidservant')
        ->and($result['questions'][0]['options'][3]['text'])->toBe('Lawyer B');
});

test('a passage heading is lifted off the option it was welded to', function () {
    $result = parseQuestions('5. Where does the play take place?A. On the streetB. In George&#039;s placeC. In Aunt&#039;s houseD. In Ofosu&#039;s place.Questions 6 to 10 are based on Romeo and Juliet');

    $q = $result['questions'][0];

    expect($q['options'])->toHaveCount(4)
        ->and($q['options'][3]['text'])->toBe("In Ofosu's place")
        ->and($q['passage'])->toBe('Questions 6 to 10 are based on Romeo and Juliet');
});

test('an ordinary question carries no passage', function () {
    $result = parseQuestions("1. Capital?\nA. Lagos\nB. Abuja\nAnswer: B");

    expect($result['questions'][0]['passage'])->toBeNull();
});

test('the spaced layout is still preferred over the welded rule', function () {
    // "Mrs. B" and "J.C.De" must not be read as labels. A paper that parses
    // under the strict rule must never be re-read by the looser one.
    $result = parseQuestions("1. Who said it?\nA. Mrs. B and Lawyer B\nB. J.C.De Graft\nAnswer: A");

    expect($result['questions'][0]['options'])->toHaveCount(2)
        ->and($result['questions'][0]['options'][0]['text'])->toBe('Mrs. B and Lawyer B')
        ->and($result['questions'][0]['options'][1]['text'])->toBe('J.C.De Graft');
});

test('a welded JAMB paper imports as a usable examination', function () {
    $result = parseQuestions(<<<'TXT'
    1. Which paper type is given to you?A. Type AB. Type BC. Type CD. Type DQuestions 2 to 4 are based on Sons and Daughters
    ✓ Correct Answer: A
    2. The traditional order is represented by .A. Mrs. BB. HannahC. MaananD. Aunt
    ✓ Correct Answer: D
    3. The play is mostly written in .A. blank verseB. free verseC. metresD. foot.
    ✓ Correct Answer: B
    TXT);

    $set = ExtractedQuestionSet::fromExtraction($result);

    expect($set->count())->toBe(3)
        ->and($set->usable())->toHaveCount(3)
        ->and($set->isAcceptable())->toBeTrue()
        ->and(collect($result['questions'])->every(fn (array $q) => count($q['options']) === 4))->toBeTrue()
        ->and(collect($result['questions'])->pluck('correct_label')->all())->toBe(['A', 'D', 'B']);
});
