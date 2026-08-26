<?php

namespace App\Services\DocumentExtraction;

use App\Services\CbtDocxTextExtractor;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One way in for every supported document type.
 *
 * The callers upstream know they have "a file the teacher uploaded"; they
 * should not also have to know which library reads it. Adding a format later -
 * or adding OCR for scanned PDFs - means adding a branch here and nothing else.
 */
class DocumentTextExtractor
{
    public function __construct(
        private readonly PdfTextExtractor $pdf,
        private readonly CbtDocxTextExtractor $docx,
    ) {}

    /**
     * @param  string  $absolutePath  The stored original, never a temp upload.
     * @param  string|null  $mimeType  What the upload recorded; the extension
     *                                 is used as a fallback, since a mime type
     *                                 is only ever a claim.
     */
    public function extract(string $absolutePath, ?string $mimeType = null): ExtractedDocument
    {
        return match ($this->format($absolutePath, $mimeType)) {
            'pdf' => $this->pdf->extract($absolutePath),
            'docx' => $this->fromDocx($absolutePath),
            'text' => new ExtractedDocument(text: (string) file_get_contents($absolutePath)),
            default => new ExtractedDocument(
                text: '',
                warnings: ['This file type cannot be read for questions.'],
            ),
        };
    }

    private function fromDocx(string $absolutePath): ExtractedDocument
    {
        try {
            // PHPWord already walks tables and pulls embedded images out of the
            // archive, so this is a straight adaptation rather than new work.
            $result = $this->docx->extract($absolutePath);
        } catch (Throwable $e) {
            // A .docx is a zip, and a truncated or mislabelled upload is not
            // one. PHPWord throws for that; the PDF path already turns its own
            // equivalent into a message, and these two must behave the same or
            // a corrupt Word file crashes where a corrupt PDF explains itself.
            Log::warning('DOCX could not be opened', ['path' => $absolutePath, 'exception' => $e]);

            return new ExtractedDocument(
                text: '',
                warnings: ['The Word document could not be opened — it may be corrupted, incomplete, or not a real .docx file.'],
            );
        }

        $document = new ExtractedDocument(
            text: $result['text'],
            images: $result['images'],
        );

        // An image in the file is not a failure, but a question that referred
        // to a diagram will read oddly without it, and somebody should know.
        return $result['images'] === []
            ? $document
            : $document->withWarning(sprintf(
                'The document contains %d image(s). Any question that refers to a diagram needs its image attached by hand.',
                count($result['images']),
            ));
    }

    private function format(string $absolutePath, ?string $mimeType): string
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if ($mimeType === 'application/pdf' || $extension === 'pdf') {
            return 'pdf';
        }

        if (in_array($extension, ['docx', 'doc'], true) || str_contains((string) $mimeType, 'wordprocessingml')) {
            return 'docx';
        }

        return in_array($extension, ['txt', 'csv'], true) ? 'text' : 'unknown';
    }
}
