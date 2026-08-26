<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ExamTerm;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Concerns\PushesResultsToRepository;
use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\Student;
use App\Notifications\ResultAvailableNotification;
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
    use AuthorizesSchoolOwnership, PushesResultsToRepository;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $academicLevels = $school->academicLevels()->with('classes')->get();
        $classOptions = $academicLevels->flatMap->classes->pluck('name', 'name')->all();
        $defaultClass = array_key_exists('Primary 1', $classOptions) ? 'Primary 1' : array_key_first($classOptions);

        $termOptions = ExamTerm::cases();

        $class = $request->filled('class') ? $request->string('class')->toString() : $defaultClass;
        $session = $request->filled('session') ? $request->string('session')->toString() : $school->currentSession();
        $term = $request->filled('term')
            ? (ExamTerm::tryFrom($request->string('term')->toString()) ?? $termOptions[0])
            : $termOptions[0];

        $examination = $class
            ? $school->examinations()->where('class_name', $class)->where('session', $session)->where('term', $term)->first()
            : null;

        $students = $examination
            ? ExaminationResultCalculator::summariesFor($examination)->map(fn ($summary) => [
                ...$summary,
                'status' => ExaminationResultCalculator::statusFor($examination, $summary['subjectsGraded']),
            ])
            : collect();

        // Which of these have been pushed, and which have been corrected
        // since. Two queries for the page rather than one per row - see
        // ResultRepository::stateFor().
        $repository = $examination
            ? app(ResultRepository::class)->stateFor($examination)
            : ['published' => collect(), 'staleStudentIds' => []];

        return view('school-admin.results.index', [
            'classOptions' => $classOptions,
            'sessionOptions' => AcademicSession::options(),
            'termOptions' => $termOptions,
            'selectedClass' => $class,
            'selectedSession' => $session,
            'selectedTerm' => $term,
            'examination' => $examination,
            'students' => $students,
            'publishedResults' => $repository['published'],
            'staleStudentIds' => $repository['staleStudentIds'],
        ]);
    }

    public function show(Request $request, Examination $examination, Student $student): JsonResponse
    {
        $this->authorizeResult($examination, $student);

        $data = [
            ...ReportCardData::for($examination, $student),
            'canEditTeacherRemark' => true,
            'canEditPrincipalRemark' => true,
        ];

        return response()->json([
            'card_number' => $student->admission_number,
            'details_html' => view('school-admin.results._details', $data)->render(),
            'report_card_html' => view('school-admin.results._report-card', $data)->render(),
            'has_scores' => $data['subjects']->contains(fn ($subject) => $subject->scores->isNotEmpty()),
            'teacher_remark' => $data['report']->teacher_remark,
            'principal_remark' => $data['report']->principal_remark,
            'guardian_count' => $student->guardians()->count(),
            'last_sent_at' => $data['report']->last_sent_at?->format('M j, Y g:ia'),
        ]);
    }

    public function updateRemarks(Request $request, Examination $examination, Student $student): RedirectResponse|JsonResponse
    {
        $this->authorizeResult($examination, $student);

        $validated = $request->validate([
            'teacher_remark' => ['nullable', 'string', 'max:1000'],
            'principal_remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = ExaminationReport::firstOrCreateFor($examination, $student);
        $report->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Remarks saved.']);
        }

        return back()->with('status', 'Remarks saved.');
    }

    public function send(Request $request, Examination $examination, Student $student): RedirectResponse|JsonResponse
    {
        $this->authorizeResult($examination, $student);

        $validated = $request->validate([
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['in:student,guardians'],
        ]);

        $recipients = $validated['recipients'];
        $guardiansNotified = 0;

        if (in_array('student', $recipients, true)) {
            $student->notify(new ResultAvailableNotification($examination, $student));
        }

        if (in_array('guardians', $recipients, true)) {
            $guardians = $student->guardians;
            $guardians->each(fn ($guardian) => $guardian->notify(new ResultAvailableNotification($examination, $student)));
            $guardiansNotified = $guardians->count();
        }

        $report = ExaminationReport::firstOrCreateFor($examination, $student);
        $report->update(['last_sent_at' => now(), 'last_sent_by' => $request->user()->id]);

        $message = in_array('guardians', $recipients, true) && $guardiansNotified === 0
            ? "{$student->fullName()}'s result was sent, but no parent/guardian is linked to this student yet."
            : "{$student->fullName()}'s result was sent successfully.";

        if ($request->wantsJson()) {
            return response()->json(['status' => $message]);
        }

        return back()->with('status', $message);
    }

    public function print(Examination $examination, Student $student): View
    {
        $this->authorizeResult($examination, $student);

        return view('school-admin.results.print', ReportCardData::for($examination, $student));
    }

    public function pdf(Examination $examination, Student $student): Response
    {
        $this->authorizeResult($examination, $student);

        $pdf = Pdf::loadView('school-admin.results.pdf.report-card', ReportCardData::for($examination, $student))
            ->setPaper('a4');

        return $pdf->download("report-card-{$student->admission_number}.pdf");
    }

    /**
     * Push to Repository: publish one pupil's finished card.
     */
    public function push(Request $request, Examination $examination, Student $student): JsonResponse|RedirectResponse
    {
        $this->authorizeResult($examination, $student);

        return $this->pushResultToRepository(
            $request,
            $examination,
            $student,
            $request->user(),
            $request->user()->name,
        );
    }

    /**
     * The same, for every pupil in the class whose card is ready.
     */
    public function pushClass(Request $request, Examination $examination): JsonResponse|RedirectResponse
    {
        $this->authorizeSchoolOwnership($examination);

        return $this->pushClassToRepository(
            $request,
            $examination,
            $request->user(),
            $request->user()->name,
        );
    }

    private function authorizeResult(Examination $examination, Student $student): void
    {
        $this->authorizeSchoolOwnership($examination);
        abort_unless($student->school_id === $examination->school_id, 403);
    }
}
