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
 * is never the whole layout at once - it is one detail at a time. A paper may
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
     * The answer key: "Answer: B", "Correct Answer - C", "Ans: (D)", "Key: A".
     */
    private const ANSWER_PATTERN = '/^\s*(?:Correct\s+)?(?:Answer|Ans|Key|Correct|Solution)\s*(?:Answer)?\s*[\:\-–\.\)]?\s*\(?\s*([A-Ha-h])\s*\)?\s*\.?\s*$/iu';

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
     * used to produce, so everything downstream - validation, review flagging,
     * the importers - is untouched by this change.
     *
     * @return array{questions: list<array<string, mixed>>, instructions: ?string}
     */
    public function parse(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];

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

            // Only a question already opened can take options. Without this a
            // document's own preamble - "A. Answer all questions" - would
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
            // left - a short line that is essentially just a year - marks where
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
     * A PDF has no columns - it has text in a position - so extraction flattens
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
    private function splitInlineOptions(string $label, string $text): array
    {
        $options = [];

        while ($label < 'H') {
            $next = chr(ord($label) + 1);

            if (! preg_match('/\s('.$next.')\s*[\)\.\:\-–]\s+/u', $text, $m, PREG_OFFSET_CAPTURE)) {
                break;
            }

            $cut = (int) $m[0][1];
            $before = trim(mb_substr($text, 0, $cut));

            // A label with nothing before it is not a second column; it is the
            // start of this option's own text.
            if ($before === '') {
                break;
            }

            $options[] = ['label' => $label, 'text' => $before];
            $text = trim(mb_substr($text, $cut + mb_strlen($m[0][0])));
            $label = $next;
        }

        $options[] = ['label' => $label, 'text' => trim($text)];

        return $options;
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
