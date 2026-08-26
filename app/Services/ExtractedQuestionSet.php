<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Everything one uploaded document produced, judged as a whole.
 *
 * A single bad question is a thing a teacher fixes in a minute. A document
 * where most questions came back malformed is a different problem: the file
 * itself needs correcting, and telling someone to "review 43 questions" is not
 * a useful thing to say. This class draws that line so the two get different
 * messages.
 */
final class ExtractedQuestionSet
{
    /**
     * Below this share of usable questions, the document is treated as having
     * failed rather than as needing review. Set where it is because a document
     * that produced mostly rubble is almost always the wrong file, a scan too
     * poor to read, or a layout the extractor could not follow - none of which
     * a teacher can fix question by question.
     */
    private const MINIMUM_USABLE_RATIO = 0.5;

    /**
     * @param  Collection<int, ExtractedQuestion>  $questions
     */
    private function __construct(public readonly Collection $questions) {}

    /**
     * @param  array<string, mixed>  $payload  The raw extraction response.
     */
    public static function fromExtraction(array $payload): self
    {
        $questions = collect($payload['questions'] ?? [])
            ->values()
            ->map(fn (mixed $raw, int $index) => ExtractedQuestion::fromExtraction(
                is_array($raw) ? $raw : [],
                $index + 1,
            ));

        return new self($questions);
    }

    public function count(): int
    {
        return $this->questions->count();
    }

    /**
     * @return Collection<int, ExtractedQuestion>
     */
    public function usable(): Collection
    {
        return $this->questions->filter(fn (ExtractedQuestion $question) => $question->isUsable());
    }

    public function needingReviewCount(): int
    {
        return $this->questions->filter(fn (ExtractedQuestion $question) => $question->needsReview())->count();
    }

    /**
     * Did this document produce something worth importing at all?
     */
    public function isAcceptable(): bool
    {
        if ($this->count() === 0) {
            return false;
        }

        return ($this->usable()->count() / $this->count()) >= self::MINIMUM_USABLE_RATIO;
    }

    /**
     * Why the document was rejected, phrased for the person who uploaded it.
     *
     * Says what to do next, because "extraction failed" on its own leaves
     * someone with a stored file and no idea whether to fix it or re-upload it.
     */
    public function rejectionReason(): ?string
    {
        if ($this->isAcceptable()) {
            return null;
        }

        if ($this->count() === 0) {
            return 'No questions could be read from this document. Check that it contains numbered multiple-choice '
                .'questions with their answer options, and that any scanned pages are legible, then upload it again.';
        }

        return sprintf(
            'Only %d of the %d questions in this document could be read properly - the rest were missing their text or '
                .'answer options. The document likely needs its layout corrected before it can be turned into a CBT. '
                .'Nothing has been added to the test.',
            $this->usable()->count(),
            $this->count(),
        );
    }
}
