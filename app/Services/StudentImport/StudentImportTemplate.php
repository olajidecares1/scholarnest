<?php

namespace App\Services\StudentImport;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;
use RuntimeException;
use ZipArchive;

/**
 * The blank class list a school downloads, fills in and uploads again.
 *
 * Offered in every format {@see StudentImportReader} can read back, so a
 * school can work in whichever program it already uses: CSV, Excel (.xlsx),
 * Word (.docx) or PDF. Each one has the same headings in the same order and
 * one example row, and each is written so the reader recognises it without
 * any change, which the tests check by reading every format straight back.
 */
class StudentImportTemplate
{
    /** Format => [file extension, MIME type, label shown to the school]. */
    public const FORMATS = [
        'csv' => ['csv', 'text/csv; charset=UTF-8', 'CSV (.csv)'],
        'xlsx' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Excel (.xlsx)'],
        'docx' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Word (.docx)'],
        'pdf' => ['pdf', 'application/pdf', 'PDF (.pdf)'],
    ];

    /** Empty rows left under the example in the Word and PDF versions. */
    private const BLANK_ROWS = 20;

    private const HEADINGS = ['Admission Number', 'First Name', 'Last Name', 'Gender', 'Date of Birth (DD/MM/YYYY)', 'House', 'Guardian Name', 'Guardian Phone', 'Guardian Email', 'Student Phone', 'Student Email', 'Address', 'Admission Date (DD/MM/YYYY)', 'Notes'];

    private const SAMPLE = ['ADM-001', 'Chinedu', 'Okafor', 'Male', '14/03/2014', 'Blue House', 'Ngozi Okafor', '08012345678', 'ngozi.okafor@example.com', '', '', '12 Allen Avenue, Ikeja', '09/09/2024', ''];

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    public function columns(bool $autoGenerateAdmissionNumbers): array
    {
        $headings = self::HEADINGS;
        $sample = self::SAMPLE;

        // The school's own numbering fills this in, so it is not asked for.
        if ($autoGenerateAdmissionNumbers) {
            array_shift($headings);
            array_shift($sample);
        }

        return [$headings, $sample];
    }

    public function build(string $format, bool $autoGenerateAdmissionNumbers): string
    {
        [$headings, $sample] = $this->columns($autoGenerateAdmissionNumbers);

        return match ($format) {
            'csv' => $this->csv($headings, $sample),
            'xlsx' => $this->xlsx($headings, $sample),
            'docx' => $this->docx($headings, $sample),
            'pdf' => $this->pdf($headings, $sample),
            default => throw new RuntimeException("Unknown template format [{$format}]."),
        };
    }

    public function filename(string $format): string
    {
        return 'student-import-template.'.self::FORMATS[$format][0];
    }

    public function contentType(string $format): string
    {
        return self::FORMATS[$format][1];
    }

    /**
     * @param  list<string>  $headings
     * @param  list<string>  $sample
     */
    private function csv(array $headings, array $sample): string
    {
        $out = fopen('php://temp', 'r+');
        // A byte-order mark, so Excel opens names with accents correctly.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headings, ',', '"', '');
        fputcsv($out, $sample, ',', '"', '');
        rewind($out);
        $contents = (string) stream_get_contents($out);
        fclose($out);

        return $contents;
    }

    /**
     * A minimal but complete workbook, written as XML the same way the reader
     * reads it. Every column is formatted as Text so phone numbers keep their
     * leading 0 and dates are not turned into Excel serial numbers.
     *
     * @param  list<string>  $headings
     * @param  list<string>  $sample
     */
    private function xlsx(array $headings, array $sample): string
    {
        $count = count($headings);
        $lastColumn = $this->columnLetter($count - 1);

        $cols = '';
        foreach ($headings as $index => $heading) {
            $width = max(14, min(34, mb_strlen($heading) + 4));
            $n = $index + 1;
            $cols .= "<col min=\"{$n}\" max=\"{$n}\" width=\"{$width}\" style=\"2\" customWidth=\"1\"/>";
        }

        $rowXml = function (int $rowNumber, array $values, int $style): string {
            $cells = '';
            foreach ($values as $index => $value) {
                $ref = $this->columnLetter($index).$rowNumber;
                $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= "<c r=\"{$ref}\" s=\"{$style}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">{$text}</t></is></c>";
            }

            return "<row r=\"{$rowNumber}\">{$cells}</row>";
        };

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            ."<dimension ref=\"A1:{$lastColumn}2\"/>"
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            ."<cols>{$cols}</cols>"
            .'<sheetData>'.$rowXml(1, $headings, 1).$rowXml(2, $sample, 2).'</sheetData>'
            .'</worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Students" sheetId="1" r:id="rId1"/></sheets>'
                .'</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            'xl/worksheets/sheet1.xml' => $sheet,
            'xl/styles.xml' => $styles,
        ];

        return $this->zip($files);
    }

    /**
     * A landscape Word table: headings, the example row, then empty rows to
     * type into. The reader takes every table row, so nothing else is needed.
     *
     * @param  list<string>  $headings
     * @param  list<string>  $sample
     */
    private function docx(array $headings, array $sample): string
    {
        $word = new PhpWord;
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(8);

        $section = $word->addSection([
            'orientation' => 'landscape',
            'marginLeft' => 500,
            'marginRight' => 500,
            'marginTop' => 700,
            'marginBottom' => 700,
        ]);

        $section->addText('Student Import Template', ['bold' => true, 'size' => 13]);
        $section->addText('Fill in one student per row under the headings, remove the example row, then upload this file. Keep the heading row as it is.', ['size' => 8, 'color' => '6B7280']);

        $table = $section->addTable([
            'borderSize' => 4,
            'borderColor' => 'D1D5DB',
            'cellMargin' => 50,
            'alignment' => JcTable::CENTER,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);

        $table->addRow(null, ['tblHeader' => true]);
        foreach ($headings as $heading) {
            $table->addCell(null, ['bgColor' => '2563EB'])->addText($heading, ['bold' => true, 'color' => 'FFFFFF', 'size' => 7.5]);
        }

        $table->addRow();
        foreach ($sample as $value) {
            $table->addCell()->addText($value, ['size' => 7.5]);
        }

        for ($i = 0; $i < self::BLANK_ROWS; $i++) {
            $table->addRow(320);
            foreach ($headings as $heading) {
                $table->addCell()->addText('');
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'tpl');

        try {
            WordIOFactory::createWriter($word, 'Word2007')->save($path);

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * A landscape A4 sheet, for schools that print the list and fill it in
     * by hand, or fill it in with a PDF editor. The reader rebuilds the
     * columns from where the headings sit, so it reads straight back.
     *
     * @param  list<string>  $headings
     * @param  list<string>  $sample
     */
    private function pdf(array $headings, array $sample): string
    {
        $e = fn (string $value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        // A heading that wraps onto two lines comes back from the PDF as two
        // half-headings, so the date hints move under the table and every
        // heading stays on one line.
        $headings = array_map(fn ($h) => trim(str_replace('(DD/MM/YYYY)', '', $h)), $headings);
        $head = implode('', array_map(fn ($h) => '<th>'.$e($h).'</th>', $headings));
        $example = implode('', array_map(fn ($v) => '<td>'.$e($v).'</td>', $sample));
        $blank = str_repeat('<tr class="blank">'.str_repeat('<td>&nbsp;</td>', count($headings)).'</tr>', self::BLANK_ROWS);

        $html = <<<HTML
<!doctype html>
<html><head><meta charset="utf-8"><style>
    @page { margin: 22px 20px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #111827; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #2563eb; color: #fff; font-weight: bold; text-align: left; padding: 4px 3px; border: 1px solid #1d4ed8; white-space: nowrap; font-size: 6.5px; }
    p.hint { margin: 8px 0 0; color: #6b7280; }
    td { padding: 4px 3px; border: 1px solid #d1d5db; white-space: nowrap; font-size: 6.5px; }
    tr.blank td { height: 16px; }
</style></head><body>
<table><thead><tr>{$head}</tr></thead><tbody><tr>{$example}</tr>{$blank}</tbody></table>
<p class="hint">Dates as DD/MM/YYYY. One student per row; remove the example row before uploading.</p>
</body></html>
HTML;

        return Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output();
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the template file.');
        }

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        try {
            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function columnLetter(int $index): string
    {
        $letters = '';

        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + (($n - 1) % 26)).$letters;
        }

        return $letters;
    }
}
