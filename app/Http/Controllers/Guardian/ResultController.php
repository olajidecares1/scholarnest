<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\School;
use App\Models\Student;
use App\Services\ReportCardData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResultController extends Controller
{
    public function index(Request $request, School $school, Student $student): View
    {
        $this->authorizeChild($request, $student);

        $results = $student->examinationScores()
            ->with(['subject.examination'])
            ->get()
            ->sortByDesc(fn ($score) => $score->subject->examination->exam_date)
            ->groupBy(fn ($score) => $score->subject->examination->name.' — '.$score->subject->examination->session);

        return view('guardian.children.results', [
            'school' => $school,
            'activeChild' => $student,
            'student' => $student,
            'results' => $results,
        ]);
    }

    public function show(Request $request, School $school, Student $student, Examination $examination): JsonResponse
    {
        $this->authorizeChild($request, $student);
        $this->authorizeExamination($student, $examination);

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

    public function print(Request $request, School $school, Student $student, Examination $examination): View
    {
        $this->authorizeChild($request, $student);
        $this->authorizeExamination($student, $examination);

        return view('school-admin.results.print', ReportCardData::for($examination, $student));
    }

    public function pdf(Request $request, School $school, Student $student, Examination $examination): Response
    {
        $this->authorizeChild($request, $student);
        $this->authorizeExamination($student, $examination);

        $pdf = Pdf::loadView('school-admin.results.pdf.report-card', ReportCardData::for($examination, $student))
            ->setPaper('a4');

        return $pdf->download("report-card-{$student->admission_number}.pdf");
    }

    private function authorizeChild(Request $request, Student $student): void
    {
        $guardian = $request->user('guardian');

        abort_unless($guardian->students()->where('students.id', $student->id)->exists(), 403);
    }

    private function authorizeExamination(Student $student, Examination $examination): void
    {
        abort_unless($examination->school_id === $student->school_id, 403);
        abort_unless($examination->class_name === $student->class_name, 403);
    }
}
