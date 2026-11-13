<?php

namespace App\Services\StudentImport;

use App\Services\Import\ImportTemplate;

/**
 * The blank class list a school downloads, fills in and uploads again.
 *
 * The files themselves are written by {@see ImportTemplate}, in every format
 * {@see StudentImportReader} can read back; the tests check that by reading
 * every format straight back.
 */
class StudentImportTemplate extends ImportTemplate
{
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

        return $this->render($format, $headings, $sample);
    }

    protected function title(): string
    {
        return 'Student Import Template';
    }

    protected function sheetName(): string
    {
        return 'Students';
    }

    protected function filenameStem(): string
    {
        return 'student-import-template';
    }

    protected function instructions(): string
    {
        return 'Fill in one student per row under the headings, remove the example row, then upload this file. Keep the heading row as it is.';
    }

    protected function pdfHint(): string
    {
        return 'Dates as DD/MM/YYYY. One student per row; remove the example row before uploading.';
    }
}
