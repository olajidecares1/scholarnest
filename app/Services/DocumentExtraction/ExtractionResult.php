<?php

namespace App\Services\DocumentExtraction;

use RuntimeException;

/**
 * What one pass over a document produced.
 *
 * Carries the questions in the shape the importers already read, plus the two
 * things a teacher needs when it goes wrong: whether the file was readable at
 * all, and what to do about it.
 */
final class ExtractionResult
{
    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  list<string>  $images
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly array $questions,
        public readonly ?string $instructions = null,
        public readonly array $images = [],
        public readonly array $warnings = [],
        public readonly bool $looksScanned = false,
        public readonly ?DocumentMetadata $metadata = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->questions === [];
    }

    /**
     * The shape the existing importers consume.
     *
     * @return array{questions: list<array<string, mixed>>, instructions: ?string}
     */
    public function toPayload(): array
    {
        return [
            'questions' => $this->questions,
            'instructions' => $this->instructions,

            // Carried alongside rather than applied: the importer stores it
            // so the review screen can offer it, and a person decides.
            'detected' => $this->metadata?->found() ?? [],
        ];
    }

    /**
     * Why nothing came out, phrased for the teacher who uploaded it.
     *
     * Deliberately never a stack trace, a parser message or an exception - a
     * teacher can act on "this looks like a scan" and can do nothing whatever
     * with "TypeError in PdfParser.php line 412". The technical detail is
     * logged for whoever maintains the server.
     */
    public function failureReason(): string
    {
        if ($this->looksScanned) {
            return 'This PDF appears to be scanned or image-based, so it contains no selectable text to read. '
                .'Questions cannot be extracted from a scan. Please upload a Word document, or a PDF exported '
                .'from a word processor rather than photographed or scanned.';
        }

        if ($this->warnings !== []) {
            return 'The document could not be read. '.$this->warnings[0]
                .' The file is stored safely — you can fix it and use "Try extracting again" without uploading it afresh.';
        }

        return 'No questions could be read from this document. Check that it contains numbered questions with '
            .'lettered options (for example "1." followed by "A.", "B.", "C.", "D."), then use '
            .'"Try extracting again". The file is stored safely.';
    }

    public function toFailure(): RuntimeException
    {
        return new RuntimeException($this->failureReason());
    }
}
