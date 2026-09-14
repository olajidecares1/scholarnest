<?php

namespace App\Services\DocumentExtraction;

/**
 * Reads the heading of a question paper, and its answer key if it has one.
 *
 * Two jobs that both work on the parts of a document that are NOT questions:
 * the block before the first question, and any block after the last one.
 *
 * Kept apart from QuestionParser because that class walks the document once,
 * line by line, holding the question it is building. Threading heading state
 * and a trailing key through the same loop would tangle two problems that only
 * happen to share a file.
 */
class HeadingReader
{
    /**
     * "Subject: Mathematics", "SUBJECT, English Language".
     */
    private const LABELLED = '/^\s*%s\s*[\:\-–]\s*(.+?)\s*$/iu';

    /**
     * How far into a document a heading can be.
     *
     * Headings sit at the top. Reading the whole document for them would find
     * "Class" inside a comprehension passage and file the paper under it.
     */
    private const HEADING_LINES = 30;

    /**
     * An answer-key line: "1. B", "1) B", "1, B", "Q1: B".
     */
    private const KEY_ENTRY = '/(?:^|\s)(?:Q(?:uestion)?\s*\.?\s*)?(\d{1,3})\s*[\.\)\:\-–]\s*\(?([A-Ha-h])\)?(?=\s|$|,|;)/u';

    /**
     * The line that announces a key block.
     */
    private const KEY_HEADING = '/^\s*(?:answer\s*key|answers?|marking\s*(?:scheme|guide)|solutions?)\s*[\:\-–]?\s*$/iu';

    /**
     * What the paper says it is.
     */
    public function metadata(string $text): DocumentMetadata
    {
        $lines = array_slice(preg_split('/\R/u', $text) ?: [], 0, self::HEADING_LINES);

        return new DocumentMetadata(
            subject: $this->labelled($lines, 'Subject'),
            className: $this->labelled($lines, '(?:Class|Level|Form|Grade)'),
            session: $this->session($lines),
            term: $this->term($lines),
            title: $this->title($lines),
        );
    }

    /**
     * The paper without its answer key.
     *
     * Must be parsed instead of the whole document, because a key entry,
     * "1. B", is indistinguishable from the start of a question followed by
     * an option. Handed the raw text, the parser reads a three-question paper
     * with a key as six questions, three of them nonsense.
     */
    public function bodyOf(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $start = $this->keyBlockStart($lines);

        if ($start === null) {
            return $text;
        }

        // Back one line, to drop the "Answer Key:" heading itself.
        return implode("\n", array_slice($lines, 0, max(0, $start - 1)));
    }

    /**
     * Fill in correct answers from a key printed at the end of the paper.
     *
     * Only where the question does not already carry one. An answer written
     * beside the question is the more specific statement, and a key that
     * disagreed with it would be the wrong one to believe, keys are typed
     * separately and drift when questions are reordered.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return list<array<string, mixed>>
     */
    public function applyAnswerKey(array $questions, string $text): array
    {
        $key = $this->answerKey($text, $questions);

        if ($key === []) {
            return $questions;
        }

        foreach ($questions as $index => $question) {
            $number = $question['number'] ?? null;

            if ($question['correct_label'] !== null || ! isset($key[$number])) {
                continue;
            }

            $label = $key[$number];

            // A key entry naming an option the question does not have is a key
            // that has drifted out of step. Left alone for a person to settle
            // rather than written in as though it were certain.
            //
            // Options are a LIST of ['label' => 'A', 'text' => '...'], so the
            // labels are a column of it rather than its keys.
            if (! in_array($label, array_column($question['options'] ?? [], 'label'), true)) {
                continue;
            }

            $questions[$index]['correct_label'] = $label;
            $questions[$index]['answer_source'] = 'found_in_answer_key';
        }

        return $questions;
    }

    /**
     * Question number => option label, from a key block at the end.
     *
     * Searched only AFTER the last question, so the numbered questions
     * themselves, "1. What is..." followed by "A. ...", cannot be misread as
     * key entries.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return array<int, string>
     */
    private function answerKey(string $text, array $questions): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $start = $this->keyBlockStart($lines);

        if ($start === null) {
            return [];
        }

        $key = [];
        $known = array_column($questions, 'number');

        foreach (array_slice($lines, $start) as $line) {
            if (preg_match_all(self::KEY_ENTRY, $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $number = (int) $match[1];

                    // Only numbers this paper actually asked. A stray "2024 B"
                    // in a footer is not question 2024's answer.
                    if (in_array($number, $known, true)) {
                        $key[$number] = strtoupper($match[2]);
                    }
                }
            }
        }

        return $key;
    }

    /**
     * The line the key block starts on, if the paper has one.
     *
     * @param  list<string>  $lines
     */
    private function keyBlockStart(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            if (preg_match(self::KEY_HEADING, trim($line))) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    private function labelled(array $lines, string $label): ?string
    {
        foreach ($lines as $line) {
            if (preg_match(sprintf(self::LABELLED, $label), (string) $line, $m)) {
                $value = trim($m[1]);

                // A label with nothing after it, or a whole sentence after it,
                // is not the answer to "what subject is this?".
                if ($value !== '' && mb_strlen($value) <= 60) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * "2025/2026", or a labelled year.
     *
     * @param  list<string>  $lines
     */
    private function session(array $lines): ?string
    {
        if ($labelled = $this->labelled($lines, '(?:Session|Academic\s*(?:Year|Session))')) {
            return $labelled;
        }

        foreach ($lines as $line) {
            if (preg_match('/\b(20\d{2}\s*\/\s*20\d{2})\b/u', (string) $line, $m)) {
                return preg_replace('/\s+/', '', $m[1]);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    private function term(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/\b(first|second|third|1st|2nd|3rd)\s+term\b/iu', (string) $line, $m)) {
                return match (strtolower($m[1])) {
                    'first', '1st' => 'first',
                    'second', '2nd' => 'second',
                    default => 'third',
                };
            }
        }

        return null;
    }

    /**
     * The paper's own name for itself.
     *
     * A labelled title if there is one; otherwise the first line that reads
     * like a heading rather than a field or a question.
     *
     * @param  list<string>  $lines
     */
    private function title(array $lines): ?string
    {
        if ($labelled = $this->labelled($lines, '(?:Title|Exam(?:ination)?\s*Title|Paper)')) {
            return $labelled;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);

            if ($line === '' || mb_strlen($line) > 90) {
                continue;
            }

            // Not a "Subject: x" field, not a numbered question, not an option.
            if (preg_match('/^[A-Za-z ]{3,20}\s*[\:\-–]/u', $line) || preg_match('/^\s*\(?[A-Ha-h0-9]\s*[\)\.\:]/u', $line)) {
                continue;
            }

            if (preg_match('/\b(?:examination|exam|test|paper|assessment|quiz)\b/iu', $line)) {
                return $line;
            }
        }

        return null;
    }
}
