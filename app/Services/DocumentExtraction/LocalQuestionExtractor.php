<?php

namespace App\Services\DocumentExtraction;

/**
 * Reads questions out of a document using nothing but PHP.
 *
 * The whole pipeline this belongs to used to depend on a hosted model, which
 * meant an unpaid API bill stopped teachers uploading question papers at all.
 * Nothing here touches the network: PHPWord reads .docx, smalot/pdfparser reads
 * .pdf, and {@see QuestionParser} finds the questions in the text. It works with
 * no key, no credit and no internet.
 */
class LocalQuestionExtractor implements QuestionExtractionProvider
{
    public function __construct(
        private readonly DocumentTextExtractor $text,
        private readonly QuestionParser $parser,
        private readonly HeadingReader $heading,
    ) {}

    public function extract(string $absolutePath, ?string $mimeType = null): ExtractionResult
    {
        $document = $this->text->extract($absolutePath, $mimeType);

        // A scan yields no text, and no parser can find questions in pixels.
        // Returned as its own outcome so the teacher is told about OCR rather
        // than being told their questions were formatted wrongly.
        if (! $document->hasText()) {
            return new ExtractionResult(
                questions: [],
                images: $document->images,
                warnings: $document->warnings,
                looksScanned: $document->looksScanned,
            );
        }

        // The whole document goes to the parser, answer keys included: a
        // past-questions compilation prints a key after EVERY year, and the
        // parser reads each one against its own year's questions, so a key
        // is never mistaken for questions and 2011's question 1 is never
        // answered from 2010's key.
        //
        // A PDF may offer more than one reading of itself (see
        // PdfTextExtractor). Each is parsed and the one that produced the
        // better questions is kept, which is a judgement only the parser can
        // make: the text alone does not say which reading a document suits.
        $best = null;
        $text = $document->text;

        foreach ($document->readings() as $reading) {
            $parsed = $this->parser->parse($reading);

            if ($best === null || $this->score($parsed['questions']) > $this->score($best['questions'])) {
                $best = $parsed;
                $text = $reading;
            }
        }

        return new ExtractionResult(
            questions: $best['questions'],
            instructions: $best['instructions'],
            images: $document->images,
            warnings: $document->warnings,
            looksScanned: $document->looksScanned,

            // What the paper says it is, subject, class, session, term,
            // title. Suggestions for the review screen, never applied on
            // their own. See DocumentMetadata.
            metadata: $this->heading->metadata($text),
        );
    }

    /**
     * How usable a reading of a document turned out to be.
     *
     * Counted in what a student needs, not in how much text came out: a
     * question with its full set of lettered options is worth something, and
     * one that also has its answer is worth more, because it can be marked
     * without anybody typing the answer in by hand. A reading that produces
     * more questions by cutting them in half scores lower than one that
     * produces fewer whole ones.
     *
     * @param  list<array<string, mixed>>  $questions
     */
    private function score(array $questions): int
    {
        $score = 0;

        foreach ($questions as $question) {
            $options = count($question['options'] ?? []);

            if ($options >= 4 && $options <= 5) {
                $score++;
            }

            if (filled($question['correct_label'] ?? null)) {
                $score += 2;
            }
        }

        return $score;
    }
}
