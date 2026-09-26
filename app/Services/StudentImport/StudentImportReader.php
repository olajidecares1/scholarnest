<?php

namespace App\Services\StudentImport;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

/**
 * Turns an uploaded class list into plain rows of cells.
 *
 * Schools keep their registers in whatever they happen to use: an Excel
 * workbook, a CSV exported from another system, a table typed into Word, or a
 * PDF someone printed. This class only answers "what are the rows and cells in
 * this file"; deciding which cell is a surname and which is a gender is
 * {@see StudentImportParser}'s job.
 *
 * Deliberately reads .xlsx and .docx straight from their XML rather than
 * pulling in a spreadsheet library: both are zip files of plain XML, and the
 * first worksheet or the tables in a document are all an import needs.
 */
class StudentImportReader
{
    /** File types accepted by the upload field, by extension. */
    public const EXTENSIONS = ['csv', 'txt', 'xlsx', 'docx', 'pdf'];

    /**
     * @return list<list<string>>
     *
     * @throws StudentImportException when the file cannot be read at all
     */
    public function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        $path = $file->getRealPath();

        if ($path === false) {
            throw new StudentImportException('The uploaded file could not be read. Please try again.');
        }

        try {
            $rows = match ($extension) {
                'csv', 'txt' => $this->readDelimited((string) file_get_contents($path)),
                'xlsx' => $this->readXlsx($path),
                'docx' => $this->readDocx($path),
                'pdf' => $this->readPdf($path),
                'xls', 'doc' => throw new StudentImportException(
                    'Older Excel (.xls) and Word (.doc) files are not supported. Please open the file and use "Save As" to save it as .xlsx, .docx or .csv, then upload it again.'
                ),
                default => throw new StudentImportException('Please upload a CSV, Excel (.xlsx), Word (.docx) or PDF file.'),
            };
        } catch (StudentImportException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new StudentImportException('We could not read that file. Please check that it opens correctly, or download the template and copy your list into it.');
        }

        // Drop rows with nothing in them, and tidy every cell once here so
        // the parser never has to think about stray whitespace.
        $clean = [];

        foreach ($rows as $row) {
            $cells = array_map(fn ($cell) => $this->tidy((string) $cell), $row);

            if (implode('', $cells) !== '') {
                $clean[] = array_values($cells);
            }
        }

        return $clean;
    }

    /**
     * CSV or tab-separated text. The separator is whichever of comma,
     * semicolon or tab appears most in the first line, because European
     * Excel saves "CSV" with semicolons.
     *
     * @return list<list<string>>
     */
    public function readDelimited(string $contents): array
    {
        $contents = $this->toUtf8($contents);
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        $firstLine = strtok($contents, "\r\n") ?: '';
        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);
        $delimiter = (string) array_key_first($counts);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($cell) => (string) $cell, $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * The first worksheet of an Excel workbook.
     *
     * @return list<list<string>>
     */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new StudentImportException('That Excel file appears to be damaged. Please re-save it and try again.');
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheetXml = $zip->getFromName($this->firstSheetPath($zip));

            if ($sheetXml === false) {
                throw new StudentImportException('That Excel file has no worksheet we could read.');
            }

            $sheet = $this->loadXml($sheetXml);
            $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            $rows = [];

            foreach ($sheet->xpath('//m:sheetData/m:row') ?: [] as $rowNode) {
                $cells = [];

                foreach ($rowNode->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->c as $cell) {
                    // Plain (un-namespaced) attributes; $cell came from a
                    // namespaced children() call, which would otherwise
                    // look for r and t in the spreadsheet namespace.
                    $reference = (string) ($cell->attributes()['r'] ?? '');
                    $column = $reference !== '' ? $this->columnIndex($reference) : count($cells);
                    $cells[$column] = $this->cellValue($cell, $sharedStrings);
                }

                if ($cells === []) {
                    continue;
                }

                // Fill the gaps Excel leaves for empty cells, so column
                // positions line up with the header row.
                $row = array_fill(0, max(array_keys($cells)) + 1, '');

                foreach ($cells as $index => $value) {
                    $row[$index] = $value;
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = [];
        $doc = $this->loadXml($xml);

        foreach ($doc->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->si as $item) {
            $item->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $parts = $item->xpath('.//m:t') ?: [];
            $strings[] = implode('', array_map(fn ($t) => (string) $t, $parts));
        }

        return $strings;
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $book = $this->loadXml($workbook);
            $book->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $first = ($book->xpath('//m:sheets/m:sheet') ?: [])[0] ?? null;
            $relId = $first ? (string) ($first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '') : '';

            if ($relId !== '') {
                $relsDoc = $this->loadXml($rels);

                foreach ($relsDoc->children('http://schemas.openxmlformats.org/package/2006/relationships') as $rel) {
                    if ((string) ($rel->attributes()['Id'] ?? '') === $relId) {
                        $target = ltrim((string) ($rel->attributes()['Target'] ?? ''), '/');

                        return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell->attributes()['t'] ?? '');
        $ns = $cell->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return match ($type) {
            's' => $sharedStrings[(int) $ns->v] ?? '',
            'inlineStr' => (string) ($ns->is->t ?? ''),
            'b' => ((string) $ns->v) === '1' ? 'TRUE' : 'FALSE',
            default => $this->numberToText((string) $ns->v),
        };
    }

    /**
     * Excel stores 8012345678 as "8012345678" but 1001 as "1001" and some
     * writers as "1001.0"; whole numbers come back without the decimal.
     */
    private function numberToText(string $value): string
    {
        if (is_numeric($value) && (float) $value == floor((float) $value) && abs((float) $value) < 1e15) {
            return number_format((float) $value, 0, '.', '');
        }

        return $value;
    }

    private function columnIndex(string $reference): int
    {
        $letters = strtoupper((string) preg_replace('/\d+/', '', $reference));
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * Every table in a Word document, one row per table row. A document with
     * no table falls back to its paragraphs, split like a CSV line.
     *
     * @return list<list<string>>
     */
    private function readDocx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new StudentImportException('That Word file appears to be damaged. Please re-save it and try again.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new StudentImportException('That Word file has no content we could read.');
        }

        $doc = $this->loadXml($xml);
        $doc->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $rows = [];

        foreach ($doc->xpath('//w:tbl/w:tr') ?: [] as $tr) {
            $tr->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $row = [];

            foreach ($tr->xpath('./w:tc') ?: [] as $tc) {
                $tc->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $paragraphs = [];

                foreach ($tc->xpath('.//w:p') ?: [] as $p) {
                    $p->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                    $paragraphs[] = implode('', array_map(fn ($t) => (string) $t, $p->xpath('.//w:t') ?: []));
                }

                $row[] = implode(' ', array_filter($paragraphs, fn ($t) => trim($t) !== ''));
            }

            $rows[] = $row;
        }

        if ($rows !== []) {
            return $rows;
        }

        $lines = [];

        foreach ($doc->xpath('//w:body/w:p') ?: [] as $p) {
            $p->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $text = '';

            foreach ($p->xpath('.//w:t|.//w:tab') ?: [] as $node) {
                $text .= $node->getName() === 'tab' ? "\t" : (string) $node;
            }

            $lines[] = $text;
        }

        return $this->splitLines($lines);
    }

    /**
     * A PDF has no real table structure, only positioned text. Columns are
     * recovered from the tabs or wide gaps between them, which works for
     * lists exported from Excel or Word; the preview step shows the school
     * exactly what was understood before anything is saved.
     *
     * @return list<list<string>>
     */
    private function readPdf(string $path): array
    {
        $document = (new PdfParser)->parseFile($path);

        $rows = $this->pdfRowsByPosition($document);

        if ($rows !== []) {
            return $rows;
        }

        $text = $document->getText();

        if (trim($text) === '') {
            throw new StudentImportException('That PDF has no readable text (it may be a scanned image). Please upload the list as an Excel, CSV or Word file instead.');
        }

        return $this->splitLines(preg_split('/\R/u', $text) ?: []);
    }

    /**
     * Rebuild the table from where each piece of text sits on the page:
     * pieces at the same height form a row, and each piece goes to the
     * column (taken from the widest row near the top, normally the headings)
     * whose left edge it is closest to.
     *
     * @return list<list<string>>
     */
    private function pdfRowsByPosition(Document $document): array
    {
        $lines = [];

        foreach ($document->getPages() as $pageNumber => $page) {
            $byHeight = [];

            foreach ($page->getDataTm() as [$matrix, $text]) {
                if (trim((string) $text) === '') {
                    continue;
                }

                $y = (float) ($matrix[5] ?? 0);
                $key = null;

                foreach (array_keys($byHeight) as $existing) {
                    if (abs($existing - $y) <= 2.5) {
                        $key = $existing;
                        break;
                    }
                }

                $byHeight[$key ?? $y][] = ['x' => (float) ($matrix[4] ?? 0), 'text' => trim((string) $text)];
            }

            // Top of the page first (PDF y grows upwards).
            krsort($byHeight);

            foreach ($byHeight as $pieces) {
                usort($pieces, fn ($a, $b) => $a['x'] <=> $b['x']);
                $lines[] = $pieces;
            }
        }

        if ($lines === []) {
            return [];
        }

        $reference = collect(array_slice($lines, 0, 15))->sortByDesc(fn ($pieces) => count($pieces))->first();
        $starts = array_map(fn ($piece) => $piece['x'], $reference);

        if (count($starts) < 2) {
            return [];
        }

        $rows = [];

        foreach ($lines as $pieces) {
            $row = array_fill(0, count($starts), '');

            foreach ($pieces as $piece) {
                $nearest = 0;

                foreach ($starts as $index => $start) {
                    if (abs($piece['x'] - $start) < abs($piece['x'] - $starts[$nearest])) {
                        $nearest = $index;
                    }
                }

                $row[$nearest] = trim($row[$nearest].' '.$piece['text']);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $lines
     * @return list<list<string>>
     */
    private function splitLines(array $lines): array
    {
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            if (str_contains($line, "\t") || preg_match('/\S {2,}\S/u', $line)) {
                $rows[] = preg_split('/\t+| {2,}/u', trim($line)) ?: [];
            } elseif (str_contains($line, ',')) {
                $rows[] = str_getcsv($line, ',', '"', '');
            } elseif (str_contains($line, '|')) {
                $rows[] = explode('|', trim($line, ' |'));
            } else {
                $rows[] = [$line];
            }
        }

        return $rows;
    }

    private function loadXml(string $xml): \SimpleXMLElement
    {
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

        if ($doc === false) {
            throw new StudentImportException('We could not read that file. Please re-save it and try again.');
        }

        return $doc;
    }

    private function toUtf8(string $contents): string
    {
        if (str_starts_with($contents, "\xFF\xFE") || str_starts_with($contents, "\xFE\xFF")) {
            return (string) mb_convert_encoding($contents, 'UTF-8', 'UTF-16');
        }

        return mb_check_encoding($contents, 'UTF-8')
            ? $contents
            : (string) mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }

    private function tidy(string $value): string
    {
        $value = str_replace("\u{00A0}", ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
