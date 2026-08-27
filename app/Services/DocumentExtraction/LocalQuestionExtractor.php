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

        // The key block is cut off before parsing: a key entry ("1. B") is
        // indistinguishable from a question followed by an option, so a paper
        // with a key would otherwise come out with phantom questions on the end.
        $parsed = $this->parser->parse($this->heading->bodyOf($document->text));

        // A key printed at the end fills in the questions that did not carry
        // their answer beside them. Applied after parsing, because it needs
        // to know which questions exist before it can match numbers to them.
        $questions = $this->heading->applyAnswerKey($parsed['questions'], $document->text);

        return new ExtractionResult(
            questions: $questions,
            instructions: $parsed['instructions'],
            images: $document->images,
            warnings: $document->warnings,
            looksScanned: $document->looksScanned,

            // What the paper says it is - subject, class, session, term,
            // title. Suggestions for the review screen, never applied on
            // their own. See DocumentMetadata.
            metadata: $this->heading->metadata($document->text),
        );
    }
}
