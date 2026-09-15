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
     * The answer key: "Answer: B", "Correct Answer, C", "Ans: (D)", "Key: A".
     */
    private const ANSWER_PATTERN = '/^[\s\p{S}\p{P}]*(?:Correct\s+)?(?:Answer|Ans|Key|Correct|Solution)\s*(?:Answer)?\s*[\:\-–\.\)]?\s*\(?\s*([A-Ha-h])\s*\)?\s*\.?\s*$/iu';

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
    private const PASSAGE_HEADING_PATTERN = '/Questions?\s+\d{1,3}\s*(?:to|and|[-–])\s*\d{1,3}\s+(?:are\s+|is\s+)?based\s+on\b/iu';

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
     * the importers, is untouched by this change.
     *
     * @return array{questions: list<array<string, mixed>>, instructions: ?string}
     */
    public function parse(string $text): array
    {
        $lines = preg_split('/\R/u', $this->decodeEntities($text)) ?: [];

        /** @var list<array<string, mixed>> $questions */
        $questions = [];
        $current = null;
        $context = null;          // 'question' | 'option' | 'explanation'
        $preamble = [];
        $year = null;

        foreach ($lines as $rawLine) {
            $line = trim((string) $rawLine);

            if ($line === '') {
                continue;
            }

            // A year heading between questions marks where one paper ends and
            // the next begins, which is how a "past questions 2010-2018"
            // compilation is laid out. Checked before the question pattern so
            // a line like "2019" cannot be read as question 2019.
            // An answer key belongs to the question already open; checked
            // before the option pattern because "Ans: B" would otherwise be
            // read as an option labelled A.
            if ($current !== null && preg_match(self::ANSWER_PATTERN, $line, $m)) {
                $current['correct_label'] = strtoupper($m[1]);
                $context = null;

                continue;
            }

            if ($current !== null && preg_match(self::EXPLANATION_PATTERN, $line, $m)) {
                $current['explanation'] = trim($m[1]);
                $context = 'explanation';

                continue;
            }

            if (preg_match(self::QUESTION_PATTERN, $line, $m)) {
                if ($current !== null) {
                    $questions[] = $this->finish($current, $year);
                }

                $current = $this->start((int) $m[1], trim($m[2]));
                $context = 'question';

                continue;
            }

            // A true/false or yes/no line is the whole of that question's
            // choices. Checked before the option pattern, though neither
            // "TRUE" nor "YES" begins with a label letter, so that the two
            // rules are read in the order they are written.
            if ($current !== null && $current['options'] === [] && preg_match(self::BOOLEAN_OPTIONS_PATTERN, $line, $m)) {
                $current['options'] = [
                    ['label' => 'A', 'text' => $this->booleanWord($m[1])],
                    ['label' => 'B', 'text' => $this->booleanWord($m[2])],
                ];

                // Nothing continues a pair of choices; a line after this
                // belongs to the question, not to "False".
                $context = null;

                continue;
            }

            // Only a question already opened can take options. Without this a
            // document's own preamble, "A. Answer all questions", would
            // become an orphan option.
            if ($current !== null && preg_match(self::OPTION_PATTERN, $line, $m)) {
                foreach ($this->splitInlineOptions(strtoupper($m[1]), trim($m[2])) as $option) {
                    $current['options'][] = $option;
                }

                $context = 'option';

                continue;
            }

            // Checked only after every structural pattern has been tried, so
            // an option like "B. 1960" is read as the option it is. What is
            // left, a short line that is essentially just a year, marks where
            // one paper ends and the next begins, which is how a "past
            // questions 2010-2018" compilation is laid out.
            if (($heading = $this->yearHeading($line)) !== null) {
                // Closed against the year it was printed under, not the one
                // just announced: the question above a heading belongs to the
                // paper that ended, not the one beginning.
                if ($current !== null) {
                    $questions[] = $this->finish($current, $year);
                    $current = null;
                    $context = null;
                }

                $year = $heading;

                continue;
            }

            if ($current === null) {
                $preamble[] = $line;

                continue;
            }

            $this->appendContinuation($current, $context, $line);
        }

        if ($current !== null) {
            $questions[] = $this->finish($current, $year);
        }

        return [
            'questions' => $questions,
            'instructions' => $this->instructionsFrom($preamble),
        ];
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
        $current['passage'] = trim(substr($text, $cut));
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
        $this->liftPassageHeading($current);

        $text = (string) $current['question_text'];
        $marks = null;

        if (preg_match(self::MARKS_PATTERN, $text, $m)) {
            $marks = (int) $m[1];
            // Taken out of the wording: "[2 marks]" is an instruction to the
            // marker, not part of what the pupil is asked.
            $text = trim((string) preg_replace(self::MARKS_PATTERN, '', $text));
        }

        $mentionsDiagram = (bool) preg_match(self::DIAGRAM_PATTERN, $text);

        return [
            'number' => $current['number'],
            'question_text' => $text,
            'options' => $current['options'],

            // The shared reading a run of questions refers to, when the paper
            // printed one. Null on an ordinary standalone question.
            'passage' => $current['passage'] ?? null,
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
            'year' => $year,
        ];
    }

    /**
     * The year a heading announces, or null if the line is not one.
     */
    private function yearHeading(string $line): ?int
    {
        if (! preg_match(self::YEAR_HEADING_PATTERN, $line, $m)) {
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
