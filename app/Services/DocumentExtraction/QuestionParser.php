<?php

namespace App\Services\DocumentExtraction;

/**
 * Reads multiple-choice questions out of the plain text of a document.
 *
 * This replaces a call to a hosted model, and the design follows from that:
 * every rule here is one a person could check by eye, and nothing depends on a
 * network, an account, or a bill being paid.
 *
 * The approach is a line-by-line state machine rather than one large regular
 * expression. Question papers are written by people, and the thing that varies
 * is never the whole layout at once, it is one detail at a time. A paper may
 * number questions "1." and options "(a)", or number them "Q1)" and use "A -",
 * and a monolithic pattern that assumes one house style fails completely on the
 * next document. Walking the lines lets each signal be recognised on its own.
 *
 * Continuation lines are the reason for the state: a question that wraps onto a
 * second line, or an option whose text runs long, belongs to whatever was last
 * opened. That is why every line that matches nothing is appended to the
 * current context rather than discarded.
 */
class QuestionParser
{
    /**
     * The start of a numbered question: "1.", "1)", "Q1.", "1 -", "No. 1".
     *
     * Anchored to the start of the line and requiring a separator, so a year
     * written mid-sentence ("in 1999 the...") cannot open a new question.
     */
    private const QUESTION_PATTERN = '/^\s*(?:Q(?:uestion)?\s*\.?\s*|No\.?\s*)?(\d{1,3})\s*[\.\)\:\-–]\s+(.*)$/iu';

    /**
     * An answer option: "A.", "B)", "(c)", "d -", "A:".
     *
     * The label is a single letter so a numbered sub-point cannot be mistaken
     * for one, and the separator is required for the same reason a question's
     * is: a line beginning with a capital letter and a space is just prose.
     */
    private const OPTION_PATTERN = '/^\s*\(?\s*([A-Ha-h])\s*[\)\.\:\-–]\s*(.*)$/u';

    /**
     * The words that open an answer line, in every wording the papers use:
     * "Answer", "Ans", "Correct Answer", "Correct option", "Key", "The
     * correct answer is", "Answer is". What follows them is read by
     * answerLabel(), which is where "Option B", "B. 5" and "(B) 5" are
     * accepted and "Answer all questions" is not.
     */
    private const ANSWER_LEAD = '(?:the\s+)?(?:correct\s+)?(?:answer|ans|key|correct|solution)(?:\s+(?:option|answer))?(?:\s+is)?';

    /**
     * A question number printed on a line of its own, its wording on the
     * next: "Question 1", "Question 1:", "Q1.", "1.", or "Question 1 (JAMB
     * 2010)". The bracket, when there is one, is kept so the year in it is
     * read.
     */
    private const QUESTION_ALONE_PATTERN = '/^\s*(?:Q(?:uestion)?\s*\.?\s*(\d{1,3})\s*[\.\)\:\-–]?|(\d{1,3})\s*[\.\)])\s*([\(\[][^\)\]]{1,40}[\)\]])?\s*$/iu';

    /**
     * The examinations a question may be tagged with: "(JAMB 2010)",
     * "[UTME 2011]", "(WAEC/2014)".
     */
    private const EXAM_TAG = '(?:JAMB|UTME|UME|WAEC|WASSCE|SSCE|NECO|GCE|BECE|NABTEB|POST[\s\-]?UTME)';

    /**
     * An explanation following the answer, which some papers print.
     */
    private const EXPLANATION_PATTERN = '/^\s*(?:Explanation|Reason|Rationale|Solution)\s*[\:\-–]\s*(.*)$/iu';

    /**
     * A line that mentions a figure the text cannot carry.
     */
    private const DIAGRAM_PATTERN = '/\b(?:diagram|figure|fig\.|chart|graph|table|illustration|image)\b/iu';

    /**
     * A per-question mark allocation: "[2 marks]", "(3 Marks)".
     */
    private const MARKS_PATTERN = '/[\[\(]\s*(\d{1,2})\s*marks?\s*[\]\)]/iu';

    /**
     * The start of an option run printed inside a question's own line.
     *
     * Requires a space before the "A", so it can never match at the very start
     * of the text, and requires a "B" to follow somewhere after it. That
     * lookahead is what separates a real set of choices from a sentence that
     * merely contains a letter and a full stop.
     */
    private const INLINE_OPTION_RUN_PATTERN = '/\s\(?A\s*[\)\.\:\-–]\s+(?=.*\s\(?B\s*[\)\.\:\-–]\s+)/iu';

    /**
     * The same run, in a document that printed no space before its labels.
     *
     * A real JAMB compilation reads "...is given to you?A. Type AB. Type BC.
     * Type CD. Type D" on one line: the label is welded to the end of the text
     * before it, so the pattern above, which requires a space, matches nothing
     * and the whole paper imports as questions with no options at all.
     *
     * Dropping that space is a real loosening, so it is only ever tried after
     * the strict pattern has failed, and the separator it demands is stricter
     * in exchange: a label here must be followed by a full stop, bracket or
     * colon AND a space, which "J.C.De" and "Mrs. B" do not satisfy.
     */
    private const INLINE_OPTION_RUN_WELDED_PATTERN = '/\(?A\s*[\)\.\:]\s+(?=.*\(?B\s*[\)\.\:]\s+)/iu';

    /**
     * A heading that hands the next several questions a shared passage:
     * "Questions 2 to 5 are based on J.C. De Graft's Sons and Daughters".
     *
     * In a welded document this arrives glued to the end of the last option,
     * so option D of one question ends up carrying the reading instruction for
     * the next four. It is lifted out and kept as the passage it is.
     */
    private const PASSAGE_HEADING_PATTERN = '/(?:Questions?\s+\d{1,3}\s*(?:to|and|[-–])\s*\d{1,3}\s+(?:are\s+|is\s+)?based\s+on\b|\b(?:Use|Read|Study)\s+the\s+(?:\w+\s+){1,3}(?:below|above)\s+(?:to\s+)?answer\s+questions?\b)/iu';

    /**
     * How many options a recovered run must yield before it is believed.
     */
    private const MINIMUM_RECOVERED_OPTIONS = 2;

    /**
     * A true/false or yes/no question, whose choices are printed as one line
     * rather than as lettered options: "TRUE / FALSE", "Yes / No", "T/F".
     *
     * Teachers mix these freely with lettered questions in the same paper, and
     * they are perfectly good CBT questions: two options, one of them right.
     * Read as prose they produced a question with no options at all, so every
     * one of them was silently dropped from the imported test.
     */
    private const BOOLEAN_OPTIONS_PATTERN = '/^\s*\(?\s*(TRUE|YES|T)\s*\)?\s*[\/\\\\|]\s*\(?\s*(FALSE|NO|F)\s*\)?\s*[\.\?]?\s*$/iu';

    /**
     * What to print for a shorthand true/false pair, so an option never reads
     * as a bare "T".
     *
     * @var array<string, string>
     */
    private const BOOLEAN_WORDS = ['T' => 'True', 'F' => 'False'];

    /**
     * The line that opens an answer-key block: "ANSWER KEYS", "Answer Key:",
     * "ANSWERS KEY", "Marking scheme".
     */
    private const KEY_HEADING_PATTERN = '/^\s*(?:answers?\s*keys?|answers?|marking\s*(?:scheme|guide)|solutions?)\s*[\:\-–]?\s*$/iu';

    /**
     * One entry in an answer key: "1. A", "1) A", "Q1: A", "1 A".
     */
    private const KEY_ENTRY_PATTERN = '/(?:^|\s)(?:Q(?:uestion)?\s*\.?\s*)?(\d{1,3})\s*[\.\)\:\-–]?\s*\(?([A-Ha-h])\)?(?=\s|$|,|;)/u';

    /**
     * A shared block that names the questions it applies to.
     *
     * @var list<string>
     */
    private const RANGED_BLOCK_PATTERNS = [
        '/^questions?\s*,?\s*\d{1,3}\s*(?:to|and|[-–])\s*\d{1,3}\b/iu',
        '/^in\s+(?:each\s+)?(?:of\s+)?(?:the\s+)?questions?\s*,?\s*\d{1,3}\s*(?:to|and|[-–])\s*\d{1,3}/iu',
        '/^(?:use|read|study|refer\s+to|answer)\b.{0,80}\bquestions?\s*,?\s*\d{1,3}\s*(?:to|and|[-–])\s*\d{1,3}/iu',
        '/^(?:for|from)\s+questions?\s*\d{1,3}\s*(?:to|and|[-–])\s*\d{1,3}/iu',
    ];

    /**
     * A shared block that names no range: a titled passage, or an
     * instruction to read one.
     *
     * @var list<string>
     */
    private const UNRANGED_BLOCK_PATTERNS = [
        '/^(?:passage|extract|excerpt|poem)\s+(?:[IVXLC]{1,5}|\d{1,2}|[A-D])\b(?:\s*[\:\.\-–].*)?$/iu',
        '/^comprehension\b/iu',
        '/^(?:read|study)\s+(?:the|each|this)\s+(?:following\s+)?(?:passages?|extracts?|poems?|texts?|excerpts?)\b/iu',
    ];

    /**
     * How many questions a passage that names no range is shown with, at
     * most, before it is assumed to have ended.
     */
    private const UNRANGED_PASSAGE_QUESTIONS = 10;

    /**
     * Words in a year heading that are not the subject.
     */
    private const HEADING_NOISE_PATTERN = '/\b(?:past\s+questions?(?:\s+and\s+(?:correct\s+)?answers?)?|questions?\s+and\s+(?:correct\s+)?answers?|questions?|answers?|jamb|utme|ume|waec|neco|ssce|gce|wassce|bece|nabteb|post|papers?|exams?|examinations?|cbt|objectives?|may|june|nov|dec|november|december|year|session)\b/iu';

    /**
     * Words that make a line with a year in it read as a heading rather than
     * prose.
     */
    private const HEADING_WORDS_PATTERN = '/\b(?:jamb|utme|ume|waec|neco|ssce|gce|wassce|bece|nabteb|exam|examination|paper|questions|past|session)\b/iu';

    /** @var list<array<string, mixed>> */
    private array $questions = [];

    /** @var array<string, mixed>|null */
    private ?array $current = null;

    private ?string $context = null;

    private ?int $year = null;

    private ?string $subject = null;

    private int $section = 0;

    private int $sectionStart = 0;

    /** @var array<int, string> */
    private array $sectionKey = [];

    /** @var array{text: string, range: array{0: int, 1: int}|false, used: bool}|null */
    private ?array $block = null;

    /** @var list<array{from: int, to: int, text: string}> */
    private array $rangedPassages = [];

    /** @var array{text: string, remaining: int}|null */
    private ?array $openPassage = null;

    private int $lastNumber = 0;

    private bool $headingSinceQuestion = true;

    /**
     * A heading that announces which year's paper follows: "2019",
     * "JAMB 2019", "2019 WAEC Mathematics", "MAY/JUNE 2018".
     *
     * Only whole lines qualify, and only short ones. A year inside a sentence
     * is part of a question ("independence in 1960"), not a heading, and the
     * length limit is what keeps the two apart.
     */
    private const YEAR_HEADING_PATTERN = '/^[^\d]{0,40}\b((?:19|20)\d{2})\b[^\d]{0,40}$/u';

    /**
     * Turn document text into the structure the importer already understands.
     *
     * The shape returned is deliberately identical to what the hosted model
     * used to produce, so everything downstream, validation, review flagging,
     * the importers, is untouched by this change. Each question additionally
     * carries the year, subject and section it was printed under, the passage
     * or instruction it shares with its neighbours, and whether its numbering
     * restarted without a heading to say why.
     *
     * A past-questions compilation is read as a series of sections. A heading
     * such as "UTME 2010 USE OF ENGLISH QUESTIONS" opens one; the numbering
     * starts again from 1 inside it; and an "ANSWER KEYS" block at its end
     * answers that section's questions only, never the next year's question 1.
     *
     * @return array{questions: list<array<string, mixed>>, instructions: ?string}
     */
    public function parse(string $text): array
    {
        $lines = preg_split('/\R/u', $this->decodeEntities($text)) ?: [];

        $this->questions = [];
        $this->current = null;
        $this->context = null;
        $this->year = null;
        $this->subject = null;
        $this->section = 0;
        $this->sectionStart = 0;
        $this->sectionKey = [];
        $this->block = null;
        $this->rangedPassages = [];
        $this->openPassage = null;
        $this->lastNumber = 0;
        $this->headingSinceQuestion = true;

        $preamble = [];
        $keyMode = false;

        foreach ($lines as $rawLine) {
            $line = trim((string) $rawLine);

            // Blank lines, and the page numbers a PDF leaves between pages.
            if ($line === '' || preg_match('/^\d{1,3}$/', $line)) {
                continue;
            }

            // A heading the Word reader recognised by its style. Never a
            // question, an option or part of one.
            if (str_starts_with($line, '## ')) {
                $heading = trim(substr($line, 3));
                $keyMode = false;
                $this->closeQuestion();

                if (preg_match(self::KEY_HEADING_PATTERN, $heading)) {
                    $keyMode = true;

                    continue;
                }

                $this->openSection($this->yearHeading($heading), $heading);

                continue;
            }

            // An answer key printed at the end of a section: "1. A", "2. C".
            // Read as answers, never as questions with options.
            if ($keyMode) {
                if ($this->readKeyLine($line)) {
                    continue;
                }

                $keyMode = false;
            }

            if (preg_match(self::KEY_HEADING_PATTERN, $line) && ($this->current !== null || $this->questions !== [])) {
                $this->closeQuestion();
                $keyMode = true;

                continue;
            }

            // An answer key belongs to the question already open; checked
            // before the option pattern because "Ans: B" would otherwise be
            // read as an option labelled A. A passage heading printed between
            // a question and its answer does not change which question the
            // answer is for.
            if ($this->current !== null && ($label = $this->answerLabel($line)) !== null) {
                $this->current['correct_label'] = $label;

                if ($this->context !== 'block') {
                    $this->context = null;
                }

                continue;
            }

            if ($this->current !== null && $this->context !== 'block' && preg_match(self::EXPLANATION_PATTERN, $line, $m)) {
                $this->current['explanation'] = trim($m[1]);
                $this->context = 'explanation';

                continue;
            }

            // "The passage below has gaps numbered 16 to" then "25. Immediately
            // following each gap..." is one instruction wrapped across two
            // lines, not question 25.
            if ($this->context === 'block' && preg_match(self::QUESTION_PATTERN, $line, $m)
                && preg_match('/\d{1,3}\s*(?:to|and|[-–])$/iu', rtrim($this->block['text']))) {
                $this->block['text'] .= ' '.$line;

                continue;
            }

            if (preg_match(self::QUESTION_PATTERN, $line, $m)) {
                $this->closeQuestion();
                $this->openQuestion((int) $m[1], trim($m[2]));

                continue;
            }

            // "Question 1" on a line of its own, the wording on the next line.
            // Before this, a paper laid out that way produced no questions at
            // all: every line was read as preamble.
            if ($this->context !== 'block' && preg_match(self::QUESTION_ALONE_PATTERN, $line, $m)) {
                $this->closeQuestion();
                $this->openQuestion((int) ($m[1] !== '' ? $m[1] : $m[2]), trim($m[3] ?? ''));

                continue;
            }

            // A passage, extract or instruction shared by several questions:
            // "PASSAGE II", "Questions 21 to 30 are based on...", "In each of
            // questions 26 to 35, select the option...". Its text runs until the
            // next question.
            if (($range = $this->blockStart($line)) !== null) {
                $this->startBlock($line, $range);

                continue;
            }

            if ($this->context === 'block') {
                // Inside a passage a year on its own is a citation ("Adopted
                // from VANGUARD, 19th March," then "2008"), so only a heading
                // that names an examination ends the passage.
                if (preg_match(self::HEADING_WORDS_PATTERN, $line) && ($heading = $this->yearHeading($line)) !== null) {
                    $this->closeQuestion();
                    $this->openSection($heading, $line);

                    continue;
                }

                $this->block['text'] .= "\n".$line;

                continue;
            }

            // A true/false or yes/no line is the whole of that question's
            // choices. Checked before the option pattern, though neither
            // "TRUE" nor "YES" begins with a label letter, so that the two
            // rules are read in the order they are written.
            if ($this->current !== null && $this->current['options'] === [] && preg_match(self::BOOLEAN_OPTIONS_PATTERN, $line, $m)) {
                $this->current['options'] = [
                    ['label' => 'A', 'text' => $this->booleanWord($m[1])],
                    ['label' => 'B', 'text' => $this->booleanWord($m[2])],
                ];

                // Nothing continues a pair of choices; a line after this
                // belongs to the question, not to "False".
                $this->context = null;

                continue;
            }

            // Only a question already opened can take options. Without this a
            // document's own preamble, "A. Answer all questions", would
            // become an orphan option.
            if ($this->current !== null && preg_match(self::OPTION_PATTERN, $line, $m)) {
                foreach ($this->splitInlineOptions(strtoupper($m[1]), trim($m[2])) as $option) {
                    $this->current['options'][] = $option;
                }

                $this->context = 'option';

                continue;
            }

            // Checked only after every structural pattern has been tried, so
            // an option like "B. 1960" is read as the option it is. What is
            // left, a short line that is essentially just a year, marks where
            // one paper ends and the next begins.
            if (($heading = $this->yearHeading($line)) !== null) {
                $this->closeQuestion();
                $this->openSection($heading, $line);

                continue;
            }

            if ($this->isSectionTitle($line)) {
                $this->settleBlock();
                $this->openPassage = null;
                $this->context = null;

                continue;
            }

            if ($this->current === null) {
                $preamble[] = $line;

                continue;
            }

            $this->appendContinuation($this->current, $this->context, $line);
        }

        $this->closeQuestion();
        $this->applySectionKey();

        return [
            'questions' => $this->questions,
            'instructions' => $this->instructionsFrom($preamble),
        ];
    }

    /**
     * Start a new paper: a year heading, or a plain section heading.
     */
    private function openSection(?int $year, string $heading): void
    {
        $this->applySectionKey();

        if ($year !== null) {
            $this->year = $year;
            $this->subject = $this->headingSubject($heading, $year) ?? $this->subject;
        }

        $this->section++;
        $this->sectionStart = count($this->questions);
        $this->sectionKey = [];
        $this->rangedPassages = [];
        $this->openPassage = null;
        $this->block = null;
        $this->context = null;
        $this->lastNumber = 0;
        $this->headingSinceQuestion = true;
    }

    private function openQuestion(int $number, string $text): void
    {
        // The passage or instruction printed above this question now belongs
        // to the questions it names.
        $this->settleBlock();

        $restarted = false;

        // Only a real restart counts: back to 1, or far below where the paper
        // had got to. A PDF that prints two columns out of order (43, 44, 40)
        // is still one paper.
        if (($number === 1 || $number < intdiv($this->lastNumber, 2)) && $number <= $this->lastNumber && ! $this->headingSinceQuestion) {
            // Numbering went back without a heading to say a new paper began.
            // Kept apart as its own section, and flagged, because the year it
            // belongs to cannot be known.
            $this->applySectionKey();
            $this->section++;
            $this->sectionStart = count($this->questions);
            $this->sectionKey = [];
            $this->rangedPassages = [];
            $this->openPassage = null;
            $restarted = true;
        }

        $this->current = $this->start($number, $text);
        $this->current['numbering_restarted'] = $restarted;
        $this->context = 'question';
        $this->lastNumber = $number;
        $this->headingSinceQuestion = false;
    }

    private function closeQuestion(): void
    {
        if ($this->current === null) {
            return;
        }

        $this->recoverInlineOptions($this->current);
        $this->resplitOptions($this->current);
        $this->liftPassageHeading($this->current);
        $question = $this->finish($this->current, $this->year);
        $question['subject'] = $this->subject;
        $question['section'] = $this->section;
        $question['numbering_restarted'] = (bool) ($this->current['numbering_restarted'] ?? false);
        $question['passage'] = $this->passageFor((int) $question['number']);

        // A "Questions 6 to 10 are based on..." heading welded to the end of
        // this question's last option belongs to the questions it names.
        if (filled($this->current['lifted_passage'] ?? null)) {
            $this->startBlock($this->current['lifted_passage'], $this->rangeOf($this->current['lifted_passage']));
            $this->settleBlock();
        }

        $this->questions[] = $question;
        $this->current = null;
        $this->context = null;
    }

    /**
     * Split options that only showed their neighbours once their wrapped lines
     * were joined: "A. stubborn children B." then "negligent parents C." reads
     * as one option until the lines meet.
     *
     * @param  array<string, mixed>  $current
     */
    private function resplitOptions(array &$current): void
    {
        if ($current['options'] === []) {
            return;
        }

        $rebuilt = [];

        foreach ($current['options'] as $option) {
            array_push($rebuilt, ...$this->splitInlineOptions($option['label'], $option['text']));
        }

        $labels = array_column($rebuilt, 'label');

        // Kept only when it found more options and every label is still unique.
        if (count($rebuilt) === count($current['options']) || count($labels) !== count(array_unique($labels))) {
            return;
        }

        $current['options'] = $rebuilt;
    }

    /**
     * @param  array{0: int, 1: int}|false  $range  false when the block names no questions
     */
    private function startBlock(string $line, array|false $range): void
    {
        // An instruction and the passage that follows it ("COMPREHENSION: Read
        // each passage..." then "PASSAGE I") are one shared text until a
        // question has used them.
        if ($this->block !== null && ! $this->block['used'] && $range === false && $this->block['range'] === false) {
            $this->block['text'] .= "\n".$line;
            $this->context = 'block';

            return;
        }

        // "Questions 41 to 50 are based on Literary Appreciation. Use the
        // extract below to answer" then "questions 41 and 42." The second line
        // finishes the first one's sentence. The general heading still covers
        // the whole range; the finished sentence covers the narrower one.
        if ($this->block !== null && ! $this->block['used'] && $range !== false && $this->block['range'] !== false
            && ! preg_match('/[\.\:\?\!]["”’\']?$/u', rtrim($this->block['text']))) {
            $text = $this->block['text'];
            $cut = max((int) mb_strrpos($text, '. '), (int) mb_strrpos($text, ".\n"));

            if ($cut > 0) {
                $this->rangedPassages[] = [
                    'from' => $this->block['range'][0],
                    'to' => $this->block['range'][1],
                    'text' => trim(mb_substr($text, 0, $cut + 1)),
                ];
                $text = trim(mb_substr($text, $cut + 1));
            }

            $this->block = ['text' => trim($text.' '.$line), 'range' => $range, 'used' => false];
            $this->context = 'block';

            return;
        }

        $this->settleBlock();

        $this->block = ['text' => $line, 'range' => $range, 'used' => false];
        $this->context = 'block';

        if ($range === false) {
            $this->openPassage = null;
        }
    }

    /**
     * File the block that has just ended under the questions it applies to.
     */
    private function settleBlock(): void
    {
        if ($this->block === null) {
            return;
        }

        $text = trim($this->block['text']);
        [$text, $gaps] = $this->clozeQuestions($text);

        if ($gaps > 0) {
            // A gap-fill passage has become its own questions; it is not also
            // a passage for whatever follows it.
            $this->block = null;

            if ($this->context === 'block') {
                $this->context = null;
            }

            return;
        }

        if ($this->block['range'] !== false) {
            [$from, $to] = $this->block['range'];
            $this->rangedPassages[] = ['from' => $from, 'to' => $to, 'text' => $text];
        } else {
            $this->openPassage = ['text' => $text, 'remaining' => self::UNRANGED_PASSAGE_QUESTIONS];
        }

        $this->block = null;

        if ($this->context === 'block') {
            $this->context = null;
        }
    }

    /**
     * Turn a gap-fill passage into one question per gap.
     *
     * JAMB prints these as "...the … 16 … [A. ideology B. phenomenon C. idea
     * D. component] is usually accompanied by...". Each gap is a question: its
     * options are in the brackets and the passage, with the brackets taken
     * out, is what the candidate reads.
     *
     * @return array{0: string, 1: int} the passage without its brackets, and how many gaps were found
     */
    private function clozeQuestions(string $text): array
    {
        // The gap is written many ways in real papers: "… 16 …", "……16….",
        // "… .. 17….", ".... 18…". The choices sit in square or round brackets.
        $pattern = '/[…\.][…\.\s]*?(\d{1,3})\s*[…\.]+\s*[\[\(]([^\]\)]{3,400})[\]\)]/u';

        if (! preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return [$text, 0];
        }

        $passage = trim((string) preg_replace($pattern, '…$1…', $text));
        $gaps = 0;

        foreach ($matches as $match) {
            $options = $this->clozeOptions(trim((string) preg_replace('/\s+/u', ' ', $match[2])));

            if (count($options) < self::MINIMUM_RECOVERED_OPTIONS) {
                continue;
            }

            $number = (int) $match[1];

            $this->questions[] = [
                'number' => $number,
                'question_text' => "Choose the option that best fills gap {$number} in the passage.",
                'options' => $options,
                'passage' => $passage,
                'correct_label' => null,
                'answer_source' => 'not_found',
                'has_diagram' => false,
                'diagram_description' => null,
                'marks' => null,
                'explanation' => null,
                'year' => $this->year,
                'subject' => $this->subject,
                'section' => $this->section,
                'numbering_restarted' => false,
            ];

            $this->lastNumber = max($this->lastNumber, $number);
            $gaps++;
        }

        return [$passage, $gaps];
    }

    /**
     * The choices inside a gap's brackets, in whichever style the paper used:
     * "A. well-define, B. fast-paced, c. favorable, D. social" or
     * "A repercussions B clouds C pressure D implication".
     *
     * Labels are looked for in order, A then B then C, so the article "a" in
     * "B a foremost" is never taken for a label: only the next letter due can
     * open the next option.
     *
     * @return list<array{label: string, text: string}>
     */
    private function clozeOptions(string $choices): array
    {
        $found = [];
        $offset = 0;

        foreach (['A', 'B', 'C', 'D', 'E'] as $label) {
            $pattern = '/(?:^|[\s,;])\(?'.$label.'(?:\s*[\.\)\:]\s*|\s+)/iu';

            if (! preg_match($pattern, $choices, $m, PREG_OFFSET_CAPTURE, $offset)) {
                break;
            }

            $found[] = ['label' => $label, 'start' => (int) $m[0][1], 'end' => (int) $m[0][1] + strlen($m[0][0])];
            $offset = (int) $m[0][1] + strlen($m[0][0]);
        }

        if ($found === [] || $found[0]['label'] !== 'A' || trim(substr($choices, 0, $found[0]['start'])) !== '') {
            return [];
        }

        $options = [];

        foreach ($found as $index => $label) {
            $end = $found[$index + 1]['start'] ?? strlen($choices);
            $text = trim(substr($choices, $label['end'], $end - $label['end']), " \t,;.");

            if ($text !== '') {
                $options[] = ['label' => $label['label'], 'text' => $text];
            }
        }

        return $options;
    }

    /**
     * The shared text a question should be shown with, if any.
     */
    private function passageFor(int $number): ?string
    {
        foreach (array_reverse($this->rangedPassages) as $passage) {
            if ($number >= $passage['from'] && $number <= $passage['to']) {
                return $passage['text'];
            }
        }

        if ($this->openPassage !== null) {
            $text = $this->openPassage['text'];

            // A passage that names no range cannot be allowed to run on for
            // the rest of the paper.
            if (--$this->openPassage['remaining'] <= 0) {
                $this->openPassage = null;
            }

            return $text;
        }

        return null;
    }

    /**
     * Whether a line opens a shared passage or instruction, and the question
     * range it names.
     *
     * @return array{0: int, 1: int}|false|null null when the line opens nothing,
     *                                          false when it opens a block naming no range
     */
    private function blockStart(string $line): array|false|null
    {
        foreach (self::RANGED_BLOCK_PATTERNS as $pattern) {
            if (preg_match($pattern, $line)) {
                return $this->rangeOf($line) ?: false;
            }
        }

        foreach (self::UNRANGED_BLOCK_PATTERNS as $pattern) {
            if (preg_match($pattern, $line)) {
                return false;
            }
        }

        return null;
    }

    /**
     * "questions 26 to 35" -> [26, 35].
     *
     * @return array{0: int, 1: int}|false
     */
    private function rangeOf(string $line): array|false
    {
        if (! preg_match('/questions?\s*,?\s*(\d{1,3})\s*(?:to|and|[-–])\s*(\d{1,3})/iu', $line, $m)) {
            return false;
        }

        $from = (int) $m[1];
        $to = (int) $m[2];

        return $from <= $to ? [$from, $to] : false;
    }

    /**
     * Read one line of an answer-key block. False when it is not one.
     */
    private function readKeyLine(string $line): bool
    {
        if (preg_match('/^\s*\d{1,3}\s*[\.\)\:\-–]?\s*(?:no\s+answer|nil|none|-+)\s*$/iu', $line)) {
            return true;
        }

        if (! preg_match_all(self::KEY_ENTRY_PATTERN, $line, $matches, PREG_SET_ORDER)) {
            return false;
        }

        // The whole line has to be key entries. A question line that happens
        // to begin "1. A..." has words after the letter.
        $rest = trim((string) preg_replace(self::KEY_ENTRY_PATTERN, '', $line), " \t,;|");

        if ($rest !== '') {
            return false;
        }

        foreach ($matches as $match) {
            $this->sectionKey[(int) $match[1]] = strtoupper($match[2]);
        }

        return true;
    }

    /**
     * Answer this section's unanswered questions from its key.
     */
    private function applySectionKey(): void
    {
        if ($this->sectionKey === []) {
            return;
        }

        for ($index = $this->sectionStart; $index < count($this->questions); $index++) {
            $question = $this->questions[$index];
            $label = $this->sectionKey[$question['number']] ?? null;

            if ($label === null || $question['correct_label'] !== null) {
                continue;
            }

            // A key naming an option the question does not have has drifted
            // out of step, and is left for a person rather than trusted.
            if (! in_array($label, array_column($question['options'], 'label'), true)) {
                continue;
            }

            $this->questions[$index]['correct_label'] = $label;
            $this->questions[$index]['answer_source'] = 'found_in_answer_key';
        }

        $this->sectionKey = [];
    }

    /**
     * The subject a year heading names: "UTME 2010 USE OF ENGLISH QUESTIONS"
     * -> "Use of English".
     */
    private function headingSubject(string $heading, int $year): ?string
    {
        $text = str_replace((string) $year, ' ', $heading);
        $text = (string) preg_replace(self::HEADING_NOISE_PATTERN, ' ', $text);
        $text = trim((string) preg_replace('/[\s\-–—:|•,\/()]+/u', ' ', $text));

        if (mb_strlen($text) < 3 || mb_strlen($text) > 60 || ! preg_match('/\p{L}{3}/u', $text)) {
            return null;
        }

        $words = array_map(
            fn (string $word) => in_array(mb_strtolower($word), ['of', 'in', 'and'], true) ? mb_strtolower($word) : mb_convert_case($word, MB_CASE_TITLE),
            explode(' ', $text),
        );

        return implode(' ', $words);
    }

    /**
     * Turn HTML entities back into the characters they stand for.
     *
     * Papers are routinely assembled from a web page and saved to Word, and
     * the conversion carries the entities across literally: a document arrives
     * containing "&#039;" where it means an apostrophe and "&quot;" where it
     * means a quotation mark. Nothing downstream decodes them, and the student
     * view prints text rather than markup, quite rightly, so a candidate sat
     * an examination reading `&quot;If you touch me...&quot;`.
     *
     * Decoding here rather than in the view is deliberate: the stored question
     * should hold the sentence a student is meant to read, not markup that
     * every future reader of the row has to know to undo.
     */
    private function decodeEntities(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private function start(int $number, string $text): array
    {
        return [
            'number' => $number,
            'question_text' => $text,
            'options' => [],
            'correct_label' => null,
            'explanation' => null,
        ];
    }

    /**
     * Split an option line that carries more than one option.
     *
     * Printed papers commonly set options in two columns:
     *
     *     A. passing of entries      B. consistency convention
     *     C. matching concept        D. adjusting for revenue
     *
     * A PDF has no columns, it has text in a position, so extraction flattens
     * each row into a single line holding two options. Read naively that gives
     * one option whose text swallows the next, and a question with four choices
     * arrives with two. On a real JAMB paper this affected 506 lines, which was
     * most of the questions in it.
     *
     * The split only happens at the label that comes next in sequence: after A,
     * only a " B." can open a new option. That is what stops an option whose
     * own wording contains a letter and a full stop from being torn in half.
     *
     * @return list<array{label: string, text: string}>
     */
    private function splitInlineOptions(string $label, string $text, bool $requireSpace = true): array
    {
        $options = [];

        while ($label < 'H') {
            $next = chr(ord($label) + 1);

            // Case-insensitive, and the open bracket optional, because "(a)
            // one (b) two" is as ordinary a house style as "A. one B. two".
            // Without the "i" this matched only an upper-case label, so a
            // paper written in lower case split at nothing: the first option
            // swallowed every one that followed, leaving a single option where
            // there were three, and a question with one option is not usable.
            // That is one flag, and it failed whole documents.
            //
            // A welded document has no space to require, so the separator
            // carries the weight there instead: see the two run patterns.
            $pattern = $requireSpace
                ? '/\s\(?('.$next.')\s*[\)\.\:\-–]\s+/iu'
                : '/\(?('.$next.')\s*[\)\.\:]\s+/iu';

            if (! preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                break;
            }

            // preg_match reports this offset in BYTES, so every slice taken
            // from it must be byte-based as well. Cutting with mb_substr here
            // silently ate one character of an option's text for every
            // multi-byte character earlier in the line, which on a paper using
            // curly quotes turned "Fosuwa and Maidservant" into "suwa and
            // Maidservant". The match boundaries are ASCII, so a byte slice
            // always lands on a character boundary.
            $cut = (int) $m[0][1];
            $before = trim(substr($text, 0, $cut));

            // A label with nothing before it is not a second column; it is the
            // start of this option's own text.
            if ($before === '') {
                break;
            }

            $options[] = ['label' => $label, 'text' => $before];
            $text = trim(substr($text, $cut + strlen($m[0][0])));
            $label = $next;
        }

        $options[] = ['label' => $label, 'text' => trim($text)];

        return $options;
    }

    /**
     * How a true/false choice should read once imported.
     *
     * Printed in whatever case the paper used, expanded when it was written
     * as a single letter, so the teacher reviewing the question sees "True"
     * and not "t".
     */
    private function booleanWord(string $raw): string
    {
        $word = strtoupper(trim($raw));

        return self::BOOLEAN_WORDS[$word] ?? ucfirst(strtolower($word));
    }

    /**
     * Rescue options that were printed on the question's own line.
     *
     * Every pattern in this class is anchored to the start of a line, which is
     * what stops a capital letter mid-sentence from being read as a label. But
     * a PDF has no lines of its own, it has glyphs at positions, and the text
     * layer a paper produces often puts a question and all of its choices on
     * one of them:
     *
     *     1. Which organelle releases energy? A. Ribosome B. Mitochondrion ...
     *
     * Read line by line that is one question carrying no options at all, and a
     * question with fewer than two options is not usable. So a document laid
     * out this way failed as a whole rather than in part: every question found,
     * none of them importable, and the teacher told the file "needs its layout
     * corrected" when the layout was one this parser simply could not see.
     *
     * The recovery is deliberately narrow. It runs only when a question closed
     * with no options at all, it requires the run to begin at A and to reach at
     * least B, and it keeps the result only if two or more options came out of
     * it. Those three conditions are what stop an ordinary sentence that
     * happens to contain "A." from being torn into pieces.
     *
     * @param  array<string, mixed>  $current
     */
    private function recoverInlineOptions(array &$current): void
    {
        // A question that found its options the ordinary way is never touched.
        if ($current['options'] !== []) {
            return;
        }

        $text = (string) $current['question_text'];

        // The spaced layout is tried first and kept whenever it works, so a
        // document that already parsed is never re-read by the looser rule.
        foreach ([[self::INLINE_OPTION_RUN_PATTERN, true], [self::INLINE_OPTION_RUN_WELDED_PATTERN, false]] as [$pattern, $spaced]) {
            if (! preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $cut = (int) $m[0][1];
            $question = trim(substr($text, 0, $cut));

            // Nothing before the first label means this is a bare list of
            // options with no question above it, not something to guess at.
            if ($question === '') {
                continue;
            }

            $options = $this->splitInlineOptions(
                'A',
                trim(substr($text, $cut + strlen($m[0][0]))),
                $spaced,
            );

            if (count($options) < self::MINIMUM_RECOVERED_OPTIONS) {
                continue;
            }

            $current['question_text'] = $question;
            $current['options'] = $options;

            return;
        }
    }

    /**
     * Lift a "Questions 6 to 10 are based on..." heading off the last option.
     *
     * A welded paper prints the heading immediately after the final option,
     * with nothing between them, so the option imports carrying the reading
     * instruction for the questions that follow. Students then see a choice
     * that is half answer and half instruction.
     *
     * @param  array<string, mixed>  $current
     */
    private function liftPassageHeading(array &$current): void
    {
        if ($current['options'] === []) {
            return;
        }

        $last = count($current['options']) - 1;
        $text = $current['options'][$last]['text'];

        if (! preg_match(self::PASSAGE_HEADING_PATTERN, $text, $m, PREG_OFFSET_CAPTURE)) {
            return;
        }

        $cut = (int) $m[0][1];
        $option = rtrim(trim(substr($text, 0, $cut)), '.');

        // A heading with no option text before it is the whole cell; leave it
        // rather than replace a real choice with an empty string.
        if ($option === '') {
            return;
        }

        $current['options'][$last]['text'] = $option;
        $current['lifted_passage'] = trim(substr($text, $cut));
    }

    /**
     * A line that opened nothing belongs to whatever is currently open.
     *
     * @param  array<string, mixed>  $current
     */
    private function appendContinuation(array &$current, ?string $context, string $line): void
    {
        if ($context === 'option' && $current['options'] !== []) {
            $last = count($current['options']) - 1;
            $current['options'][$last]['text'] = trim($current['options'][$last]['text'].' '.$line);

            return;
        }

        if ($context === 'explanation') {
            $current['explanation'] = trim(((string) $current['explanation']).' '.$line);

            return;
        }

        $current['question_text'] = trim($current['question_text'].' '.$line);
    }

    /**
     * Finish a question: pull out its marks, and note any diagram it mentions.
     *
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function finish(array $current, ?int $year = null): array
    {
        $this->recoverInlineOptions($current);
        $this->takeInlineAnswer($current);

        $text = (string) $current['question_text'];
        $marks = null;

        if (preg_match(self::MARKS_PATTERN, $text, $m)) {
            $marks = (int) $m[1];
            // Taken out of the wording: "[2 marks]" is an instruction to the
            // marker, not part of what the pupil is asked.
            $text = trim((string) preg_replace(self::MARKS_PATTERN, '', $text));
        }

        // A question tagged with the paper it came from, "(JAMB 2010)", is
        // filed under that year, whatever heading it sits under. The tag is
        // taken out of the wording: it tells a student the answer's source,
        // not anything they are being asked.
        [$text, $taggedYear] = $this->takeYearTag($text);

        $mentionsDiagram = (bool) preg_match(self::DIAGRAM_PATTERN, $text);

        return [
            'number' => $current['number'],
            'question_text' => $text,
            'options' => $current['options'],

            // The shared reading a run of questions refers to, filled in by
            // closeQuestion(). Null on an ordinary standalone question.
            'passage' => null,
            'correct_label' => $current['correct_label'],

            // The same vocabulary the importer already reads. A key printed in
            // the document is "found"; anything else is left for a person.
            'answer_source' => $current['correct_label'] !== null ? 'found_in_document' : 'not_found',
            'has_diagram' => $mentionsDiagram,
            'diagram_description' => $mentionsDiagram ? 'The question text refers to a figure or table.' : null,
            'marks' => $marks,
            'explanation' => $current['explanation'],

            // Which paper this question came from, when the document is a
            // multi-year compilation. Null for an ordinary single paper.
            'year' => $taggedYear ?? $year,
        ];
    }

    /**
     * The letter an answer line gives, or null when the line is not one.
     *
     * Accepts "Answer: B", "Ans. (B)", "Correct Answer: Option B", "The
     * correct answer is B", "Correct option: (B) 5", "Answer: B. 5", "Answer:
     * B (5)". The letter must stand alone, or be followed by the separator an
     * option label has, so "Answer all questions" and "Solution: A car
     * travels..." are not read as answers.
     */
    private function answerLabel(string $line): ?string
    {
        $pattern = '/^[\s\p{S}\p{P}]*'.self::ANSWER_LEAD.'\s*[\:\-–\.\)]?\s*(?:option\s*)?(\()?\s*([A-Ha-h])\s*(\))?(.*)$/iu';

        if (! preg_match($pattern, $line, $m)) {
            return null;
        }

        $rest = trim($m[4]);

        if ($rest === '' || preg_match('/^[\.\:]$/u', $rest) || $m[3] !== '' || preg_match('/^(?:[\.\:\-–,]\s*\S|\()/u', $rest)) {
            return strtoupper($m[2]);
        }

        return null;
    }

    /**
     * Take a year tag off the start or end of a question's wording.
     *
     * @return array{0: string, 1: ?int}
     */
    private function takeYearTag(string $text): array
    {
        $year = '((?:19|20)\d{2})';
        $tag = self::EXAM_TAG;
        $inside = '\s*(?:'.$tag.'[\s\/,\-]*)?'.$year.'(?:\s*[\/,\-]?\s*'.$tag.')?\s*';

        $patterns = [
            '/\s*[\(\[]'.$inside.'[\)\]]\s*$/iu',
            '/^\s*[\(\[]'.$inside.'[\)\]]\s*/iu',
            '/\s*[\-–—|]?\s*'.$tag.'\s*'.$year.'\s*$/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                $found = (int) $m[1][0];

                if ($found < 1960 || $found > (int) date('Y') + 1) {
                    continue;
                }

                $remaining = trim(substr_replace($text, ' ', $m[0][1], strlen($m[0][0])));

                // A question that is nothing but its tag keeps its wording.
                return $remaining === '' ? [$text, $found] : [$remaining, $found];
            }
        }

        return [$text, null];
    }

    /**
     * An answer printed on the same line as the last option or the question:
     * "D. spirit beings ✓ Correct Answer: D".
     *
     * @param  array<string, mixed>  $current
     */
    private function takeInlineAnswer(array &$current): void
    {
        $last = count($current['options']) - 1;

        if ($last >= 0 && ($cut = $this->inlineAnswerAt($current['options'][$last]['text'])) !== null) {
            $current['correct_label'] ??= $cut[1];
            $current['options'][$last]['text'] = $cut[0];

            return;
        }

        if (($cut = $this->inlineAnswerAt((string) $current['question_text'])) !== null) {
            $current['correct_label'] ??= $cut[1];
            $current['question_text'] = $cut[0];
        }
    }

    /**
     * An answer welded to the end of a line, "D. 7 Correct Answer: Option B",
     * split off it: the text before, and the letter. Null when the end of the
     * line is not an answer, so the text is left exactly as it was.
     *
     * @return array{0: string, 1: string}|null
     */
    private function inlineAnswerAt(string $text): ?array
    {
        if (! preg_match_all('/(?:^|[\s✓✔•→\*])(?='.self::ANSWER_LEAD.'\b)/iu', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        foreach ($matches[0] as [$match, $offset]) {
            if ($offset === 0) {
                continue;
            }

            $label = $this->answerLabel(substr($text, $offset));
            $before = rtrim(substr($text, 0, $offset), " \t✓✔•→*-–");

            if ($label !== null && trim($before) !== '') {
                return [trim($before), $label];
            }
        }

        return null;
    }

    /**
     * A short line in capitals that names a part of the paper: "LEXIS,
     * STRUCTURE AND ORAL FORMS". It ends whatever passage was running.
     */
    private function isSectionTitle(string $line): bool
    {
        return mb_strlen($line) >= 5
            && mb_strlen($line) <= 60
            && str_word_count($line) >= 2
            && ! preg_match('/[\d\p{Ll}]/u', $line)
            && preg_match('/^[\p{Lu}\s,&\'\-–:\/]+$/u', $line);
    }

    /**
     * The year a heading announces, or null if the line is not one.
     */
    private function yearHeading(string $line): ?int
    {
        if (! preg_match(self::YEAR_HEADING_PATTERN, $line, $m)) {
            return null;
        }

        // "In 1962, a team of scientists..." is a sentence. A heading either
        // names an examination, or is only a few words long with no
        // punctuation of a sentence in it.
        $rest = trim(str_replace($m[1], '', $line), " \t-–—:|•");

        if ($rest !== '' && ! preg_match(self::HEADING_WORDS_PATTERN, $rest) && (str_word_count($rest) > 4 || preg_match('/[,\.;\?!]/u', $rest))) {
            return null;
        }

        $year = (int) $m[1];

        // A plausible examination year. Anything outside this is a figure that
        // happens to look like one.
        return ($year >= 1960 && $year <= (int) date('Y') + 1) ? $year : null;
    }

    /**
     * The paper's rubric, if it printed one before the first question.
     *
     * @param  list<string>  $preamble
     */
    private function instructionsFrom(array $preamble): ?string
    {
        if ($preamble === []) {
            return null;
        }

        // Only lines that read like instructions to a candidate. A title page
        // or a school's name is not a rubric.
        $instructions = array_values(array_filter(
            $preamble,
            fn (string $line) => (bool) preg_match(
                '/\b(?:answer|attempt|instruction|time allowed|shade|choose|select|all questions|section)\b/iu',
                $line,
            ),
        ));

        return $instructions === [] ? null : implode(' ', $instructions);
    }
}
