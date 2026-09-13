<?php

namespace App\Services;

use App\Services\Uploads\UploadStorage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use ZipArchive;

class CbtDocxTextExtractor
{
    /**
     * Extract plain text (paragraph by paragraph) and every embedded image
     * from a .docx file. Images are pulled directly from the docx's zip
     * structure (word/media/*), which is more reliable than walking
     * PHPWord's element tree for image binaries.
     *
     * @return array{text: string, images: list<string>}
     */
    public function extract(string $absolutePath): array
    {
        return [
            'text' => $this->extractText($absolutePath),
            'images' => $this->extractImages($absolutePath),
        ];
    }

    private function extractText(string $absolutePath): string
    {
        $phpWord = IOFactory::load($absolutePath);
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
        if ($element instanceof Text) {
            return $element->getText();
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
                $rows[] = implode(' | ', array_filter($cells));
            }

            return implode("\n", $rows);
        }

        return '';
    }

    /**
     * @return list<string> public-disk paths of extracted images, in archive order
     */
    private function extractImages(string $absolutePath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            return [];
        }

        $extracted = [];
        $destinationDir = 'cbt-uploads/images/'.(string) Str::uuid();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (! $name || ! str_starts_with($name, 'word/media/')) {
                continue;
            }

            $contents = $zip->getFromIndex($i);

            if ($contents === false) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }

            $path = $destinationDir.'/'.(string) Str::uuid().'.'.$extension;
            app(UploadStorage::class)->putContents('public', $path, $contents);
            $extracted[] = $path;
        }

        $zip->close();

        return $extracted;
    }
}
