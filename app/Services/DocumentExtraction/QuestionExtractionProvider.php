<?php

namespace App\Services\DocumentExtraction;

/**
 * Whatever turns an uploaded document into questions.
 *
 * There is one implementation, {@see LocalQuestionExtractor}, and it needs no
 * network, no account and no key. The interface exists so that a different
 * engine, an OCR pass for scanned pages, or a hosted model, can be added
 * later as an alternative, not as a prerequisite.
 *
 * That distinction is the point of the interface. This pipeline previously
 * called a hosted API, which meant an unpaid bill stopped teachers uploading
 * question papers. Anything added here in future must be switchable off, and
 * the local extractor must remain the default that works on its own.
 */
interface QuestionExtractionProvider
{
    /**
     * Read questions out of a stored document.
     *
     * @param  string  $absolutePath  The stored original.
     * @param  string|null  $mimeType  What the upload recorded.
     */
    public function extract(string $absolutePath, ?string $mimeType = null): ExtractionResult;
}
