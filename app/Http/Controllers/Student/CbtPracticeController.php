<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CbtAttempt;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtExamBodyClassGrant;
use App\Models\School;
use App\Models\Student;
use App\Support\AcademicStageDetector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CbtPracticeController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $examBodies = $this->visibleExamBodiesQuery($student)
            ->withCount(['subjects', 'exams'])
            ->orderBy('name')
            ->get();

        return view('student.cbt-practice.index', [
            'school' => $school,
            'examBodies' => $examBodies,
        ]);
    }

    public function show(Request $request, School $school, CbtExamBody $examBody): View
    {
        $this->authorizeExamBody($request, $examBody);

        return view('student.cbt-practice.show', [
            'school' => $school,
            'examBody' => $examBody,
            'exams' => $examBody->exams()->with('subject')->withCount('questions')->orderByDesc('year')->get(),
        ]);
    }

    public function start(Request $request, School $school, CbtExam $exam): RedirectResponse
    {
        $this->authorizeExamBody($request, $exam->examBody);

        $student = $request->user('student');

        $attempt = CbtAttempt::create([
            'student_id' => $student->id,
            'cbt_exam_id' => $exam->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($exam->duration_minutes),
            'total_questions' => $exam->questions()->count(),
        ]);

        return redirect()->route('student.cbt-practice.attempts.show', [$school, $attempt]);
    }

    /**
     * @return Builder<CbtExamBody>
     */
    private function visibleExamBodiesQuery(Student $student)
    {
        $stage = AcademicStageDetector::detect($student->class_name);
        $grantedBodyIds = $this->grantedExamBodyIds($student);

        return CbtExamBody::query()->where(function ($query) use ($stage, $grantedBodyIds) {
            if ($stage) {
                $query->whereJsonContains('academic_stages', $stage->value);
            }

            $query->orWhereIn('id', $grantedBodyIds);
        });
    }

    /**
     * @return Collection<int, int>
     */
    private function grantedExamBodyIds(Student $student): Collection
    {
        return CbtExamBodyClassGrant::where('school_id', $student->school_id)
            ->where('class_name', $student->class_name)
            ->pluck('cbt_exam_body_id');
    }

    private function authorizeExamBody(Request $request, CbtExamBody $examBody): void
    {
        $student = $request->user('student');

        $visible = $this->visibleExamBodiesQuery($student)->where('cbt_exam_bodies.id', $examBody->id)->exists();

        abort_unless($visible, 403);
    }
}
