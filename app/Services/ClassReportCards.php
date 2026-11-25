<?php

namespace App\Services;

use App\Models\Examination;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * A whole class's report cards as ONE PDF, a card to a page, in the order
 * the class list shows them (A to Z).
 *
 * One file rather than a zip of many: a PDF opens, saves and prints the same
 * way on a phone, a tablet and a laptop, where a zip needs an app to open on
 * a phone and gives the school forty files to print one at a time.
 *
 * Each card is exactly the single-pupil PDF, the same Blade view fed the same
 * ReportCardData, so a card in the class file can never differ from the one
 * downloaded on its own. The cards are rendered one by one, their <body>s
 * joined, and the result laid out by dompdf in a single pass under the first
 * card's stylesheet, which is the same for every pupil of one school.
 */
class ClassReportCards
{
    /**
     * The pupils in this examination who have results, A to Z.
     *
     * @return Collection<int, Student>
     */
    public function studentsFor(Examination $examination): Collection
    {
        return Student::sortAlphabetically(
            ExaminationResultCalculator::summariesFor($examination)->map(fn (array $summary) => $summary['student']),
        )->values();
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    public function pdf(Examination $examination, Collection $students): string
    {
        $head = null;
        $bodies = [];

        // The class's positions, size and the like worked out once for all
        // the cards, not once per card. See ReportCardData::remembering().
        ReportCardData::remembering(function () use ($examination, $students, &$head, &$bodies) {
            $examination->loadMissing('school');

            foreach ($students->values() as $student) {
                $html = view('school-admin.results.pdf.report-card', ReportCardData::for($examination, $student))->render();

                $head ??= $this->between($html, '<head>', '</head>');
                $bodies[] = $this->between($html, '<body>', '</body>');
            }
        });

        $pages = collect($bodies)->map(function (string $body, int $index) use ($bodies) {
            // Relative, so each card's absolutely placed watermark sits on its
            // own page rather than every one of them on the first.
            $breakAfter = $index < count($bodies) - 1 ? ' page-break-after: always;' : '';

            return '<div style="position: relative;'.$breakAfter.'">'.$body.'</div>';
        })->implode("\n");

        $document = '<!DOCTYPE html><html><head>'.$head.'</head><body>'.$pages.'</body></html>';

        return Pdf::loadHTML($document)->setPaper('a4')->output();
    }

    private function between(string $html, string $open, string $close): string
    {
        $start = stripos($html, $open);
        $end = strripos($html, $close);

        if ($start === false || $end === false) {
            return $html;
        }

        $start += strlen($open);

        return substr($html, $start, $end - $start);
    }
}
