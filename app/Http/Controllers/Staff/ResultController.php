<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ExamTerm;
use App\Http\Controllers\Concerns\PushesResultsToRepository;
use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\School;
use App\Models\Student;
use App\Models\TeacherRemark;
use App\Services\ExaminationResultCalculator;
use App\Services\ReportCardData;
use App\Services\ResultRepository;
use App\Support\AcademicSession;
use App\Support\SuggestedTeacherRemarks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

        // A to Z by name, like every other list of students; each row still
        // carries its class position.
        $students = $examination
            ? Student::sortAlphabetically(
                ExaminationResultCalculator::summariesFor($examination)->map(fn ($summary) => [
                    ...$summary,
                    'status' => ExaminationResultCalculator::statusFor($examination, $summary['subjectsGraded'], $summary['subjectsMissing']),
                ]),
                fn ($row) => $row['student'],
            )
            : collect();

        $repository = $examination
            ? app(ResultRepository::class)->stateFor($examination)
            : ['published' => collect(), 'staleStudentIds' => []];

        // Each pupil's Class Teacher's Remark, for the comment box on their row.
        $remarks = $examination
            ? ExaminationReport::query()->where('examination_id', $examination->id)->pluck('teacher_remark', 'student_id')
            : collect();

        // The teacher's earlier comments, offered under every comment box.
        TeacherRemark::learnFromClasses($staff, $classes);

        return view('staff.results.index', [
            'savedRemarks' => TeacherRemark::listFor($staff),
            'suggestedRemarks' => SuggestedTeacherRemarks::forPicker(),
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
            'remarks' => $remarks,
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
            // No card_number key at all, for the same reason.
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
        ], [
            'teacher_remark.max' => 'A comment can be at most 1,000 characters.',
        ]);

        $remark = trim((string) ($validated['teacher_remark'] ?? ''));

        $report = ExaminationReport::firstOrCreateFor($examination, $student);
        $previous = $report->teacher_remark;
        $report->update(['teacher_remark' => $remark === '' ? null : $remark]);

        // The teacher's list of comments to pick from next time keeps itself:
        // this one is remembered, the draft it replaced forgotten.
        $staff = $request->user('staff');
        TeacherRemark::replace($staff, $previous, $remark);

        return response()->json([
            'status' => 'Remarks saved.',
            'teacher_remark' => $report->teacher_remark,
            'saved_remarks' => TeacherRemark::listFor($staff),

            // A card already pushed to the Repository keeps the old comment
            // until it is pushed again; the teacher is told so.
            'needs_repush' => in_array($student->id, app(ResultRepository::class)->stateFor($examination)['staleStudentIds'], true),
        ]);
    }

    /**
     * The Save button: a comment the teacher wants on their list for good,
     * typed into the list itself or already in a pupil's box.
     */
    public function keepRemark(Request $request, School $school): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ], [
            'body.required' => 'Write the comment first.',
            'body.max' => 'A comment can be at most 1,000 characters.',
        ]);

        $staff = $request->user('staff');
        TeacherRemark::keep($staff, $validated['body']);

        return response()->json(['saved_remarks' => TeacherRemark::listFor($staff)]);
    }

    /**
     * Take a comment off the teacher's own list. Report cards already
     * carrying it keep it: this tidies the list, it does not unsay anything.
     */
    public function forgetRemark(Request $request, School $school, TeacherRemark $teacherRemark): JsonResponse
    {
        $staff = $request->user('staff');

        abort_unless($teacherRemark->staff_id === $staff->id, 404);

        $teacherRemark->delete();

        return response()->json(['saved_remarks' => TeacherRemark::listFor($staff)]);
    }

    /**
     * Push to Repository: publish one pupil's finished card.
     *
     * A Class Teacher may publish for the class they are the Class Teacher of,
     * and no other. They cannot see the Repository itself, publishing into it
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
