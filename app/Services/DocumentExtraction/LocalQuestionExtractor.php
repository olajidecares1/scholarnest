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

        $parsed = $this->parser->parse($document->text);

        return new ExtractionResult(
            questions: $parsed['questions'],
            instructions: $parsed['instructions'],
            images: $document->images,
            warnings: $document->warnings,
            looksScanned: $document->looksScanned,
        );
    }
}
