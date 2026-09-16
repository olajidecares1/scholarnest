<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * One question as it came out of an uploaded document, checked before it is
 * allowed to become a real CBT question.
 *
 * Extraction reads a document written for humans and returns a structure. Most
 * of the time that structure is sound, but the failures are quiet ones: a
 * question whose options were lost, two options that both came back labelled
 * "B", or an answer key naming an option the question does not have. None of
 * those look wrong in a list of extracted questions, they look like ordinary
 * questions, and every one of them produces a test a student cannot pass.
 *
 * The worst of them was silent. If the answer key said "E" and the options ran
 * A to D, nothing matched, so no option was stored as correct, and the question
 * was not flagged for review because an answer key had after all been found.
 * The teacher saw a normal question, published it, and every student who
 * answered it was marked wrong whatever they chose.
 *
 * So this class holds one rule: a question is usable only if a student could
 * actually answer it and be marked fairly. Anything short of that is recorded
 * with the specific reason, and the reason is what the teacher is shown.
 */
final class ExtractedQuestion
{
    /**
     * Below this a question cannot be answered in any meaningful way, a
     * "multiple choice" question with one option is not a question.
     */
    public const MINIMUM_OPTIONS = 2;

    /**
     * Labels handed out when a document's own are missing or unusable, in
     * order. Longer than four because some papers run to F.
     */
    private const FALLBACK_LABELS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    /**
     * @param  list<array{label: string, text: string, is_correct: bool}>  $options
     * @param  list<string>  $problems
     */
    private function __construct(
        public readonly int $number,
        public readonly string $text,
        public readonly array $options,
        public readonly ?string $correctLabel,
        public readonly bool $hasDiagram,
        public readonly ?string $diagramDescription,
        public readonly int $marks,
        public readonly array $problems,
        public readonly ?string $passage = null,
        public readonly ?string $explanation = null,
        public readonly ?int $imageIndex = null,
        public readonly bool $numberingRestarted = false,
    ) {}

    /**
     * An [[image:N]] marker the document reader left where a picture sat.
     */
    public const IMAGE_MARKER = '/\[\[image:(\d+)\]\]/';

    /**
     * A correct answer printed in the wording itself, which must never reach
     * a candidate.
     */
    private const LEAKED_ANSWER = '/[✓✔]?\s*\bCorrect\s+Answer\s*[\:\-–]\s*\(?[A-Ha-h]\)?/u';

    /**
     * The question's identity for spotting duplicates: its wording and its
     * options, with case, spacing and punctuation ignored.
     */
    public function fingerprint(): string
    {
        $normalise = fn (string $value) => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value));

        return hash('sha256', $normalise($this->text).'|'.implode('|', array_map(
            fn (array $option) => $normalise($option['text']),
            $this->options,
        )));
    }

    /**
     * Build one from the raw extraction payload.
     *
     * @param  array<string, mixed>  $raw
     * @param  int  $fallbackNumber  Position in the document, used when the
     *                               document's own numbering is missing.
     */
    public static function fromExtraction(array $raw, int $fallbackNumber): self
    {
        $problems = [];

        // Pictures the reader marked in the wording. The first one in the
        // question becomes its image; any others are left for a person.
        $images = [];
        $text = self::takeImages((string) ($raw['question_text'] ?? ''), $images);

        foreach (is_array($raw['options'] ?? null) ? $raw['options'] : [] as $index => $option) {
            if (is_array($option)) {
                $optionImages = [];
                $raw['options'][$index]['text'] = self::takeImages((string) ($option['text'] ?? ''), $optionImages);

                if ($optionImages !== []) {
                    $problems[] = 'An answer option contains a picture, which options cannot show. Attach it to the question or retype the option.';
                }
            }
        }

        $passageImages = [];
        $passage = self::takeImages((string) ($raw['passage'] ?? ''), $passageImages);
        $images = [...$passageImages, ...$images];

        if (count($images) > 1) {
            $problems[] = sprintf('This question has %d pictures in the document; only the first was attached.', count($images));
        }

        // A correct answer printed inside the wording would be shown to the
        // candidate. It is taken out, and the question flagged.
        if (preg_match(self::LEAKED_ANSWER, $text)) {
            $text = trim((string) preg_replace(self::LEAKED_ANSWER, '', $text));
            $problems[] = 'The document printed the correct answer inside the question. It was removed; check the wording.';
        }

        $text = trim($text);

        if ($text === '') {
            $problems[] = 'No question text was found.';
        }

        [$options, $optionProblems] = self::normaliseOptions($raw['options'] ?? []);
        $problems = [...$problems, ...$optionProblems];

        $correctLabel = self::resolveCorrectLabel($raw, $options, $problems);

        // Mark the winning option, now that the label is known to exist.
        $options = array_map(
            fn (array $option): array => [
                ...$option,
                'is_correct' => $correctLabel !== null && $option['label'] === $correctLabel,
            ],
            $options,
        );

        // A figure the question mentions needs no manual attaching when the
        // document's own picture was found beside it.
        $hasDiagram = (bool) ($raw['has_diagram'] ?? false) && $images === [];
        $diagramDescription = trim((string) ($raw['diagram_description'] ?? '')) ?: null;

        return new self(
            number: (int) ($raw['number'] ?? 0) ?: $fallbackNumber,
            text: $text,
            options: $options,
            correctLabel: $correctLabel,
            hasDiagram: $hasDiagram,
            diagramDescription: $diagramDescription,
            marks: max(1, (int) ($raw['marks'] ?? 1)),
            problems: $problems,
            passage: trim($passage) !== '' ? trim($passage) : null,
            explanation: filled($raw['explanation'] ?? null) ? trim((string) $raw['explanation']) : null,
            imageIndex: $images[0] ?? null,
            numberingRestarted: (bool) ($raw['numbering_restarted'] ?? false),
        );
    }

    /**
     * Remove the image markers from a piece of text, collecting their indexes.
     *
     * @param  list<int>  $images
     */
    private static function takeImages(string $text, array &$images): string
    {
        if (preg_match_all(self::IMAGE_MARKER, $text, $matches)) {
            array_push($images, ...array_map('intval', $matches[1]));
            $text = (string) preg_replace(self::IMAGE_MARKER, '', $text);
        }

        return trim((string) preg_replace("/[ \t]*\n[ \t]*\n+/", "\n", $text));
    }

    /**
     * Clean up the option list and report what was wrong with it.
     *
     * @return array{0: list<array{label: string, text: string, is_correct: bool}>, 1: list<string>}
     */
    private static function normaliseOptions(mixed $rawOptions): array
    {
        $problems = [];
        $options = [];
        $seenLabels = [];

        foreach (is_array($rawOptions) ? $rawOptions : [] as $index => $rawOption) {
            $optionText = trim((string) (is_array($rawOption) ? ($rawOption['text'] ?? '') : ''));

            // An option with no text is not an option. Dropping it is what
            // makes the "fewer than two options" check below meaningful.
            if ($optionText === '') {
                $problems[] = 'An answer option was empty and could not be read.';

                continue;
            }

            $label = self::normaliseLabel(is_array($rawOption) ? ($rawOption['label'] ?? '') : '');

            if ($label === null) {
                $label = self::FALLBACK_LABELS[$index] ?? (string) ($index + 1);
                $problems[] = "An answer option had no label; it was labelled {$label}.";
            }

            if (in_array($label, $seenLabels, true)) {
                $problems[] = "Two answer options were both labelled {$label}.";

                continue;
            }

            $seenLabels[] = $label;
            $options[] = ['label' => $label, 'text' => $optionText, 'is_correct' => false];
        }

        if (count($options) < self::MINIMUM_OPTIONS) {
            $problems[] = sprintf(
                'Only %d answer option(s) were found; a question needs at least %d.',
                count($options),
                self::MINIMUM_OPTIONS,
            );
        }

        return [$options, $problems];
    }

    /**
     * Reduce a printed label to a bare letter.
     *
     * Documents write the same label as "A", "A.", "A)", "(A)" or "a", and all
     * of them have to compare equal to an answer key that may use any of them.
     */
    private static function normaliseLabel(mixed $raw): ?string
    {
        $label = strtoupper(trim((string) $raw));
        $label = trim($label, "().: \t\n\r\0\x0B");

        if ($label === '' || ! Str::of($label)->isMatch('/^[A-Z0-9]{1,2}$/')) {
            return null;
        }

        return $label;
    }

    /**
     * Work out which option the document says is correct, if any.
     *
     * @param  array<string, mixed>  $raw
     * @param  list<array{label: string, text: string, is_correct: bool}>  $options
     * @param  list<string>  $problems
     */
    private static function resolveCorrectLabel(array $raw, array $options, array &$problems): ?string
    {
        $claimsAnswer = in_array($raw['answer_source'] ?? 'not_found', ['found_in_document', 'found_in_answer_key'], true);
        $label = self::normaliseLabel($raw['correct_label'] ?? '');

        if (! $claimsAnswer || $label === null) {
            return null;
        }

        $available = array_column($options, 'label');

        // The silent failure this class exists for: an answer key that names an
        // option the question does not have. Treated as no answer at all, and
        // said out loud, rather than quietly marking every choice wrong.
        if (! in_array($label, $available, true)) {
            $problems[] = $available === []
                ? "The answer key gives {$label}, but no answer options were read for this question."
                : sprintf(
                    'The answer key gives %s, but this question only has options %s.',
                    $label,
                    implode(', ', $available),
                );

            return null;
        }

        return $label;
    }

    /**
     * Could a student answer this and be marked fairly?
     *
     * Note this is not the same as having a correct answer: a question whose
     * answer key was simply absent from the document is still structurally
     * fine, and the teacher only has to pick the answer.
     */
    public function isUsable(): bool
    {
        return $this->text !== '' && count($this->options) >= self::MINIMUM_OPTIONS;
    }

    public function hasCorrectAnswer(): bool
    {
        return $this->correctLabel !== null;
    }

    /**
     * Anything a person has to look at before this question faces a student.
     */
    public function needsReview(): bool
    {
        return ! $this->isUsable()
            || ! $this->hasCorrectAnswer()
            || $this->hasDiagram
            || $this->problems !== [];
    }

    /**
     * What to tell the teacher, in the order they would want to act on it.
     */
    public function reviewNotes(): ?string
    {
        $notes = $this->problems;

        if ($this->problems === [] && ! $this->hasCorrectAnswer()) {
            // Only worth saying on its own; when there are problems, one of
            // them already explains why no answer was resolved.
            $notes[] = 'Correct answer was not found in the source document. Select it manually.';
        }

        if ($this->hasDiagram) {
            $notes[] = $this->diagramDescription
                ? "This question refers to a diagram ({$this->diagramDescription}). Attach the image manually."
                : 'This question refers to a diagram. Attach the image manually.';
        }

        return $notes === [] ? null : implode(' ', $notes);
    }
}
