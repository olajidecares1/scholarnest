<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ExamTerm;
use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\Student;
use App\Services\ExaminationResultCalculator;
use App\Support\AcademicSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExaminationController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $examinations = $school->examinations()
            ->withCount('subjects')
            ->when($request->filled('class'), fn ($query) => $query->where('class_name', $request->string('class')))
            ->when($request->filled('term'), fn ($query) => $query->where('term', $request->string('term')))
            ->orderByDesc('exam_date')
            ->paginate(15)
            ->withQueryString();

        return view('school-admin.examinations.index', [
            'examinations' => $examinations,
            'academicLevels' => $school->academicLevels()->with('classes')->get(),
            'termOptions' => ExamTerm::cases(),
            'sessionOptions' => AcademicSession::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate($this->examinationRules());

        $examination = $school->examinations()->create($validated);

        return redirect()->route('examinations.show', $examination)->with('status', "{$examination->name} was created. Add subjects to start recording scores.");
    }

    public function show(Examination $examination): View
    {
        $this->authorizeExamination($examination);

        return view('school-admin.examinations.show', [
            'examination' => $examination,
            'subjects' => $examination->subjects()->withCount('scores')->get(),
            'studentCount' => Student::where('school_id', $examination->school_id)->where('class_name', $examination->class_name)->where('is_active', true)->count(),
            'offeredSubjects' => $examination->school->offeredSubjectsFor($examination->class_name),
        ]);
    }

    public function update(Request $request, Examination $examination): RedirectResponse
    {
        $this->authorizeExamination($examination);

        $validated = $request->validate($this->examinationRules());

        $examination->update($validated);

        return back()->with('status', "{$examination->name} was updated.");
    }

    public function destroy(Examination $examination): RedirectResponse
    {
        $this->authorizeExamination($examination);

        $name = $examination->name;
        $examination->delete();

        return redirect()->route('examinations.index')->with('status', "{$name} was deleted.");
    }

    public function storeSubject(Request $request, Examination $examination): RedirectResponse
    {
        $this->authorizeExamination($examination);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('examination_subjects', 'name')->where('examination_id', $examination->id),
            ],
        ]);

        $examination->subjects()->create([...$validated, 'max_score' => 100]);

        return back()->with('status', "{$validated['name']} was added to {$examination->name}.");
    }

    public function destroySubject(ExaminationSubject $subject): RedirectResponse
    {
        $this->authorizeSubject($subject);

        $examination = $subject->examination;
        $subject->delete();

        return redirect()->route('examinations.show', $examination)->with('status', "{$subject->name} was removed.");
    }

    public function scores(ExaminationSubject $subject): View
    {
        $this->authorizeSubject($subject);

        $examination = $subject->examination;

        $students = Student::where('school_id', $examination->school_id)
            ->where('class_name', $examination->class_name)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get();

        $existing = $subject->scores()->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');

        return view('school-admin.examinations.scores', [
            'examination' => $examination,
            'subject' => $subject,
            'students' => $students,
            'existing' => $existing,
            'school' => $examination->school,
        ]);
    }

    public function storeScores(Request $request, ExaminationSubject $subject): RedirectResponse
    {
        $this->authorizeSubject($subject);

        $school = $subject->examination->school;

        $validated = $request->validate([
            'test_scores' => ['required', 'array'],
            'test_scores.*' => ['nullable', 'numeric', 'min:0', 'max:'.$subject->testMaxScore()],
            'exam_scores' => ['required', 'array'],
            'exam_scores.*' => ['nullable', 'numeric', 'min:0', 'max:'.$subject->examMaxScore()],
            'grade_overrides' => ['nullable', 'array'],
            'grade_overrides.*' => ['nullable', 'string', 'max:3'],
        ]);

        $studentIds = array_unique([...array_keys($validated['test_scores']), ...array_keys($validated['exam_scores'])]);
        $students = Student::where('school_id', $subject->examination->school_id)
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        $saved = 0;
        foreach ($studentIds as $studentId) {
            $testScore = $validated['test_scores'][$studentId] ?? null;
            $examScore = $validated['exam_scores'][$studentId] ?? null;

            if (($testScore === null && $examScore === null) || ! $students->has($studentId)) {
                continue;
            }

            $subject->scores()->updateOrCreate(
                ['student_id' => $studentId],
                [
                    'test_score' => $testScore,
                    'exam_score' => $examScore,
                    'score' => (float) $testScore + (float) $examScore,
                    'grade_override' => $school->automatic_grading ? null : ($validated['grade_overrides'][$studentId] ?? null),
                ],
            );
            $saved++;
        }

        return redirect()->route('examinations.show', $subject->examination)->with('status', "Saved scores for {$saved} student(s) in {$subject->name}.");
    }

    public function reportCards(Examination $examination): View
    {
        $this->authorizeExamination($examination);

        return view('school-admin.examinations.report-cards', [
            'examination' => $examination,
            'summaries' => ExaminationResultCalculator::summariesFor($examination),
            'subjectCount' => $examination->subjects->count(),
        ]);
    }

    public function reportCard(Examination $examination, Student $student): View
    {
        $this->authorizeExamination($examination);
        abort_unless($student->school_id === $examination->school_id, 403);

        $subjects = $examination->subjects()->with(['scores' => fn ($query) => $query->where('student_id', $student->id)])->get();

        return view('school-admin.examinations.report-card', [
            'examination' => $examination,
            'student' => $student,
            'subjects' => $subjects,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function examinationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'class_name' => ['required', 'string', 'max:50'],
            'term' => ['required', Rule::enum(ExamTerm::class)],
            'session' => ['required', Rule::in(AcademicSession::options())],
            'exam_date' => ['nullable', 'date'],
        ];
    }

    private function authorizeExamination(Examination $examination): void
    {
        abort_unless($examination->school_id === auth()->user()->school_id, 403);
    }

    private function authorizeSubject(ExaminationSubject $subject): void
    {
        abort_unless($subject->examination->school_id === auth()->user()->school_id, 403);
    }
}
