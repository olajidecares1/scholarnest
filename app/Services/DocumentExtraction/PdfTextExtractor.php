<?php

namespace App\Services\DocumentExtraction;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Text out of a PDF, locally.
 *
 * A PDF is two quite different things wearing one extension. One carries real
 * selectable text and gives it up readily. The other is a photograph of paper
 * with a PDF wrapper, and no amount of parsing will find words in it, because
 * there are none - only pixels.
 *
 * This tells those two apart rather than reporting the second as an empty
 * document, because "your PDF is a scan and needs OCR" is something a teacher
 * can act on, while "no questions found" sends them hunting through a file that
 * was never going to work.
 */
class PdfTextExtractor
{
    /**
     * Below this many characters of extracted text, a PDF is treated as a scan.
     *
     * Not zero: an image-only PDF often still yields a few stray characters
     * from a header, a page number, or the producer's watermark. A handful of
     * characters spread over pages is not a document anyone can mark.
     */
    private const MINIMUM_TEXT_CHARACTERS = 40;

    public function extract(string $absolutePath): ExtractedDocument
    {
        try {
            $pdf = (new Parser)->parseFile($absolutePath);
            $text = $this->tidy($pdf->getText());
        } catch (Throwable $e) {
            // A PDF this library cannot open is not necessarily corrupt - some
            // encrypted or unusual producers defeat it. Either way the file is
            // kept and the person is told plainly.
            return new ExtractedDocument(
                text: '',
                warnings: ['The PDF could not be opened for reading ('.$e->getMessage().').'],
            );
        }

        if (mb_strlen(trim($text)) < self::MINIMUM_TEXT_CHARACTERS) {
            return new ExtractedDocument(
                text: $text,
                warnings: ['This PDF appears to be scanned or image-based and does not contain selectable text.'],
                looksScanned: true,
            );
        }

        return new ExtractedDocument(text: $text);
    }

    /**
     * Straighten out what the parser hands back.
     *
     * PDF text arrives with the page's visual quirks baked in: soft hyphens at
     * line ends, runs of spaces standing in for layout, and blank lines where
     * the page broke. Left alone these split a question across what look like
     * separate lines, which is exactly what the parser downstream keys on.
     */
    private function tidy(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Non-breaking and other exotic spaces, which regexes for "A." miss.
        $text = preg_replace('/\x{00A0}|\x{2007}|\x{202F}/u', ' ', $text) ?? $text;

        // A word broken across a line break by a hyphen belongs back together.
        $text = preg_replace('/(\p{L})-\n(\p{L})/u', '$1$2', $text) ?? $text;

        $lines = array_map(
            fn (string $line) => trim(preg_replace('/[ \t]+/', ' ', $line) ?? $line),
            explode("\n", $text),
        );

        // Collapse runs of blank lines to one, so paragraph boundaries survive
        // without leaving wide gaps the parser has to step over.
        $out = [];
        $lastWasBlank = false;

        foreach ($lines as $line) {
            $isBlank = $line === '';

            if ($isBlank && $lastWasBlank) {
                continue;
            }

            $out[] = $line;
            $lastWasBlank = $isBlank;
        }

        return trim(implode("\n", $out));
    }
}
