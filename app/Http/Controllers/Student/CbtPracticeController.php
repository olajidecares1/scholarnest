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

    /**
     * Past papers for one exam body, a year at a time.
     *
     * The years are whichever years actually have questions a student can sit,
     * newest first. Nothing is listed for a year until the AkademicNest Team
     * has published it, so this list grows as papers are added and never
     * promises a paper that turns out to be empty.
     */
    public function show(Request $request, School $school, CbtExamBody $examBody): View
    {
        $this->authorizeExamBody($request, $examBody);

        $exams = $examBody->exams()
            ->with('subject')
            ->withCount(['publishedQuestions as questions_count'])
            ->orderByDesc('year')
            ->get()
            ->filter(fn (CbtExam $exam) => $exam->questions_count > 0)
            ->values();

        $years = $exams->pluck('year')->unique()->sortDesc()->values();
        $requestedYear = (int) $request->integer('year');

        return view('student.cbt-practice.show', [
            'school' => $school,
            'examBody' => $examBody,
            'years' => $years,
            'selectedYear' => $years->contains($requestedYear) ? $requestedYear : $years->first(),
            'examsByYear' => $exams->groupBy('year'),
        ]);
    }

    public function start(Request $request, School $school, CbtExam $exam): RedirectResponse
    {
        $this->authorizeExamBody($request, $exam->examBody);

        $student = $request->user('student');
        $total = $exam->publishedQuestions()->count();

        if ($total === 0) {
            return back()->withErrors(['exam' => 'This paper has no questions yet. Try another year or subject.']);
        }

        $attempt = CbtAttempt::create([
            'student_id' => $student->id,
            'cbt_exam_id' => $exam->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($exam->duration_minutes),
            'total_questions' => $total,
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
