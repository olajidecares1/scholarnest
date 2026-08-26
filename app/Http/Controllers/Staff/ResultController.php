<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ExamTerm;
use App\Http\Controllers\Concerns\PushesResultsToRepository;
use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\School;
use App\Models\Student;
use App\Services\ExaminationResultCalculator;
use App\Services\ReportCardData;
use App\Services\ResultRepository;
use App\Support\AcademicSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResultController extends Controller
{
    use PushesResultsToRepository;

    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');
        $classes = $staff->classesAsClassTeacher();
        $requestedClass = $request->string('class')->toString();
        $className = ($requestedClass && $classes->contains($requestedClass)) ? $requestedClass : $classes->first();

        $termOptions = ExamTerm::cases();
        $session = $request->filled('session') ? $request->string('session')->toString() : $school->currentSession();
        $term = $request->filled('term')
            ? (ExamTerm::tryFrom($request->string('term')->toString()) ?? $termOptions[0])
            : $termOptions[0];

        $examination = $className
            ? $school->examinations()->where('class_name', $className)->where('session', $session)->where('term', $term)->first()
            : null;

        $students = $examination
            ? ExaminationResultCalculator::summariesFor($examination)->map(fn ($summary) => [
                ...$summary,
                'status' => ExaminationResultCalculator::statusFor($examination, $summary['subjectsGraded']),
            ])
            : collect();

        $repository = $examination
            ? app(ResultRepository::class)->stateFor($examination)
            : ['published' => collect(), 'staleStudentIds' => []];

        return view('staff.results.index', [
            'school' => $school,
            'classes' => $classes,
            'className' => $className,
            'sessionOptions' => AcademicSession::options(),
            'termOptions' => $termOptions,
            'selectedSession' => $session,
            'selectedTerm' => $term,
            'examination' => $examination,
            'students' => $students,
            'publishedResults' => $repository['published'],
            'staleStudentIds' => $repository['staleStudentIds'],
        ]);
    }

    public function show(Request $request, School $school, Examination $examination, Student $student): JsonResponse
    {
        $this->authorizeClassTeacher($request, $examination, $student);

        $data = [
            ...ReportCardData::for($examination, $student),
            'canEditTeacherRemark' => true,
            'canEditPrincipalRemark' => false,
        ];

        return response()->json([
            'card_number' => $student->admission_number,
            'details_html' => view('school-admin.results._details', $data)->render(),
            'report_card_html' => view('school-admin.results._report-card', $data)->render(),
            'has_scores' => $data['subjects']->contains(fn ($subject) => $subject->scores->isNotEmpty()),
            'guardian_count' => $student->guardians()->count(),
            'last_sent_at' => $data['report']->last_sent_at?->format('M j, Y g:ia'),
        ]);
    }

    public function updateRemarks(Request $request, School $school, Examination $examination, Student $student): JsonResponse
    {
        $this->authorizeClassTeacher($request, $examination, $student);

        $validated = $request->validate([
            'teacher_remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = ExaminationReport::firstOrCreateFor($examination, $student);
        $report->update($validated);

        return response()->json(['status' => 'Remarks saved.']);
    }

    public function print(Request $request, School $school, Examination $examination, Student $student): View
    {
        $this->authorizeClassTeacher($request, $examination, $student);

        return view('school-admin.results.print', ReportCardData::for($examination, $student));
    }

    public function pdf(Request $request, School $school, Examination $examination, Student $student): Response
    {
        $this->authorizeClassTeacher($request, $examination, $student);

        $pdf = Pdf::loadView('school-admin.results.pdf.report-card', ReportCardData::for($examination, $student))
            ->setPaper('a4');

        return $pdf->download("report-card-{$student->admission_number}.pdf");
    }

    /**
     * Push to Repository: publish one pupil's finished card.
     *
     * A Class Teacher may publish for the class they are the Class Teacher of,
     * and no other. They cannot see the Repository itself - publishing into it
     * and administering it are different jobs, and only one of them is theirs.
     */
    public function push(Request $request, School $school, Examination $examination, Student $student): JsonResponse|RedirectResponse
    {
        $this->authorizeClassTeacher($request, $examination, $student);

        $staff = $request->user('staff');

        return $this->pushResultToRepository(
            $request,
            $examination,
            $student,
            $staff,
            trim($staff->first_name.' '.$staff->last_name),
        );
    }

    /**
     * The same, for every pupil in their class whose card is ready.
     */
    public function pushClass(Request $request, School $school, Examination $examination): JsonResponse|RedirectResponse
    {
        $this->authorizeClassTeacherOfExamination($request, $examination);

        $staff = $request->user('staff');

        return $this->pushClassToRepository(
            $request,
            $examination,
            $staff,
            trim($staff->first_name.' '.$staff->last_name),
        );
    }

    private function authorizeClassTeacher(Request $request, Examination $examination, Student $student): void
    {
        $this->authorizeClassTeacherOfExamination($request, $examination);

        abort_unless($student->school_id === $examination->school_id, 403);
    }

    private function authorizeClassTeacherOfExamination(Request $request, Examination $examination): void
    {
        $staff = $request->user('staff');

        abort_unless($examination->school_id === $staff->school_id, 403);
        abort_unless($staff->classesAsClassTeacher()->contains($examination->class_name), 403, 'You are not the Class Teacher of this class.');
    }
}
