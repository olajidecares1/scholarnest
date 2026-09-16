<?php

namespace App\Services;

use App\Services\DocumentExtraction\DocxReader;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;

/**
 * Text and pictures out of a Word document.
 *
 * A .docx (a zip of XML) is read by {@see DocxReader}, which keeps line
 * breaks, headings, automatic numbering, equations and where each picture
 * sits. An old binary .doc file is not XML at all, so it falls back to
 * PHPWord's reader, which recovers the text but not the finer layout.
 */
class CbtDocxTextExtractor
{
    public function __construct(private readonly DocxReader $reader) {}

    /**
     * @return array{text: string, images: list<string>}
     */
    public function extract(string $absolutePath): array
    {
        if ($this->isZip($absolutePath)) {
            return $this->reader->read($absolutePath);
        }

        return ['text' => $this->legacyText($absolutePath), 'images' => []];
    }

    /**
     * Only the words, for previews that have no use for pictures.
     */
    public function plainText(string $absolutePath): string
    {
        $text = $this->extract($absolutePath)['text'];

        return trim((string) preg_replace('/\[\[image:\d+\]\]\n?/', '', $text));
    }

    private function isZip(string $absolutePath): bool
    {
        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            return false;
        }

        $signature = (string) fread($handle, 4);
        fclose($handle);

        return $signature === "PK\x03\x04";
    }

    /**
     * A Word 97-2003 document, through PHPWord's binary reader.
     */
    private function legacyText(string $absolutePath): string
    {
        $phpWord = IOFactory::load($absolutePath, 'MsDoc');
        $lines = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $lines[] = $this->elementToText($element);
            }
        }

        return implode("\n", array_filter($lines, fn (string $line) => trim($line) !== ''));
    }

    private function elementToText(mixed $element): string
    {
        if ($element instanceof TextBreak) {
            return "\n";
        }

        if ($element instanceof Text) {
            return html_entity_decode((string) $element->getText(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if ($element instanceof Title) {
            $text = $element->getText();

            return '## '.(is_string($text) ? $text : $this->elementToText($text));
        }

        if ($element instanceof TextRun || $element instanceof AbstractContainer) {
            $parts = [];

            foreach ($element->getElements() as $child) {
                $parts[] = $this->elementToText($child);
            }

            return implode('', $parts);
        }

        if ($element instanceof Table) {
            $rows = [];

            foreach ($element->getRows() as $row) {
                $cells = [];

                foreach ($row->getCells() as $cell) {
                    foreach ($cell->getElements() as $cellElement) {
                        $cells[] = $this->elementToText($cellElement);
                    }
                }

                $rows[] = implode('    ', array_filter($cells));
            }

            return implode("\n", $rows);
        }

        return '';
    }
}
