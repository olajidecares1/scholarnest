<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\UnlocksResultsWithToken;
use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Examination;
use App\Models\School;
use App\Models\Student;
use App\Services\ReportCardData;
use App\Services\ResultAccessPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResultController extends Controller
{
    use UnlocksResultsWithToken;

    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        // The current term first, then everything else newest-first. A parent
        // opening this page has almost always come for the term just ended,
        // and making them scan for it among four years of cards is the small
        // daily cost of sorting purely by date.
        $currentTerm = AcademicTerm::currentFor($school);

        $results = $student->examinationScores()
            ->with(['subject.examination'])
            ->get()
            ->sortBy(fn ($score) => [
                $currentTerm
                    && $score->subject->examination->session === $currentTerm->session
                    && $score->subject->examination->term === $currentTerm->term ? 0 : 1,
                -($score->subject->examination->exam_date?->timestamp ?? 0),
            ])
            ->groupBy(fn ($score) => $score->subject->examination->name.' ('.$score->subject->examination->session.')');

        return view('student.results.index', [
            'school' => $school,
            'student' => $student,
            'results' => $results,

            // Which results this session has already opened with a token, so
            // the page draws a button for those and a padlock for the rest.
            'unlocked' => $results->mapWithKeys(function ($scores) use ($student) {
                $examination = $scores->first()->subject->examination;

                return [$examination->id => $this->resultIsUnlocked($student, $examination)];
            }),
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

        $this->assertResultIsNotWithheld($student, $examination);
        $this->assertResultIsUnlocked($student, $examination);

        return $student;
    }

    /**
     * Refuse a result the school is withholding over unpaid fees.
     *
     * On the server, on every route that can produce the document, the JSON
     * view, the printable page and the PDF, because the portal's own list
     * only stops someone who uses the portal. Editing the address is not a way
     * round a balance.
     */
    private function assertResultIsNotWithheld(Student $student, Examination $examination): void
    {
        abort_if(
            app(ResultAccessPolicy::class)->isLocked($student, $examination),
            403,
            'This result is on hold until the outstanding school-fee balance is settled.',
        );
    }

    /**
     * Open one result for this session, on the strength of its exam token.
     */
    public function unlock(Request $request, School $school, Examination $examination): RedirectResponse
    {
        $student = $request->user('student');

        abort_unless($examination->school_id === $student->school_id, 403);
        abort_unless($examination->class_name === $student->class_name, 403);

        // Fees first. A token does not buy a result the school is withholding
        // over money, it decides who may see one, not whether there is one to
        // see, and checking it the other way round would spend a token on a
        // result that stays shut anyway.
        $this->assertResultIsNotWithheld($student, $examination);

        $this->redeemTokenFor($request, $school, $student, $examination, $student);

        return back()->with('status', 'Result unlocked. You can now view and download it.');
    }
}
