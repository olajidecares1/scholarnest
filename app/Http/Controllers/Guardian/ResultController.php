<?php

namespace App\Http\Controllers\Guardian;

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

    public function index(Request $request, School $school, Student $student): View
    {
        $this->authorizeChild($request, $student);

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
            ->groupBy(fn ($score) => $score->subject->examination->name.' — '.$score->subject->examination->session);

        return view('guardian.children.results', [
            'school' => $school,
            'activeChild' => $student,
            'student' => $student,
            'results' => $results,
            'unlocked' => $results->mapWithKeys(function ($scores) use ($student) {
                $examination = $scores->first()->subject->examination;

                return [$examination->id => $this->resultIsUnlocked($student, $examination)];
            }),
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

        $this->assertResultIsNotWithheld($student, $examination);
        $this->assertResultIsUnlocked($student, $examination);
    }

    /**
     * Refuse a result the school is withholding over unpaid fees.
     *
     * On the server, on every route that can produce the document - the JSON
     * view, the printable page and the PDF - because the portal's own list
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
     * Open one child's result for this session, on the strength of its token.
     */
    public function unlock(Request $request, School $school, Student $student, Examination $examination): RedirectResponse
    {
        $this->authorizeChild($request, $student);

        abort_unless($examination->school_id === $student->school_id, 403);
        abort_unless($examination->class_name === $student->class_name, 403);

        $this->assertResultIsNotWithheld($student, $examination);

        // Checked against THIS child. A guardian with two children at the
        // school holds two tokens, and the one for the other child must not
        // open this one.
        $this->redeemTokenFor($request, $school, $student, $examination, $request->user('guardian'));

        return back()->with('status', 'Result unlocked. You can now view and download it.');
    }
}
