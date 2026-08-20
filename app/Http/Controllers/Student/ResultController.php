<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\School;
use App\Services\ReportCardData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResultController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $results = $student->examinationScores()
            ->with(['subject.examination'])
            ->get()
            ->sortByDesc(fn ($score) => $score->subject->examination->exam_date)
            ->groupBy(fn ($score) => $score->subject->examination->name.' — '.$score->subject->examination->session);

        return view('student.results.index', [
            'school' => $school,
            'student' => $student,
            'results' => $results,
        ]);
    }

    public function show(Request $request, School $school, Examination $examination): JsonResponse
    {
        $student = $this->authorizeExamination($request, $examination);

        $data = [
            ...ReportCardData::for($examination, $student),
            'canEditTeacherRemark' => false,
            'canEditPrincipalRemark' => false,
        ];

        return response()->json([
            'card_number' => $student->admission_number,
            'details_html' => view('school-admin.results._details', $data)->render(),
            'report_card_html' => view('school-admin.results._report-card', $data)->render(),
            'has_scores' => $data['subjects']->contains(fn ($subject) => $subject->scores->isNotEmpty()),
            'guardian_count' => 0,
            'last_sent_at' => $data['report']->last_sent_at?->format('M j, Y g:ia'),
        ]);
    }

    public function print(Request $request, School $school, Examination $examination): View
    {
        $student = $this->authorizeExamination($request, $examination);

        return view('school-admin.results.print', ReportCardData::for($examination, $student));
    }

    public function pdf(Request $request, School $school, Examination $examination): Response
    {
        $student = $this->authorizeExamination($request, $examination);

        $pdf = Pdf::loadView('school-admin.results.pdf.report-card', ReportCardData::for($examination, $student))
            ->setPaper('a4');

        return $pdf->download("report-card-{$student->admission_number}.pdf");
    }

    private function authorizeExamination(Request $request, Examination $examination)
    {
        $student = $request->user('student');

        abort_unless($examination->school_id === $student->school_id, 403);
        abort_unless($examination->class_name === $student->class_name, 403);

        return $student;
    }
}
