<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ExamTerm;
use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\Student;
use App\Models\SubjectOffering;
use App\Services\ExaminationResultCalculator;
use App\Support\AcademicSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * Score entry the way an administrator thinks about it: a class, a year, a
     * term, a subject.
     *
     * The examination is a record the school keeps; it is not how anybody
     * describes the marking they sat down to do. Reaching scores through the
     * examination list meant knowing which examination held "Primary 4,
     * Mathematics, second term" before you could open it. This finds it from
     * the four things the administrator already knows.
     *
     * Unlike a teacher, an administrator is not restricted to classes or
     * subjects assigned to them - they are responsible for all of them - so
     * there is no assignment check here, only the school boundary.
     */
    public function scoreEntry(Request $request): View
    {
        $school = $request->user()->school;

        $className = $request->string('class_name')->toString();
        $session = $request->filled('session')
            ? $request->string('session')->toString()
            : $school->currentSession();
        $term = ExamTerm::tryFrom($request->string('term')->toString());

        $examination = ($className !== '' && $term !== null)
            ? $school->examinations()
                ->where('class_name', $className)
                ->where('session', $session)
                ->where('term', $term)
                ->with('subjects')
                ->first()
            : null;

        $subject = null;
        $students = collect();
        $existing = collect();

        if ($examination) {
            $subject = $request->filled('subject')
                ? $examination->subjects->firstWhere('name', $request->string('subject')->toString())
                : $examination->subjects->first();
        }

        if ($subject) {
            $students = Student::where('school_id', $school->id)
                ->where('class_name', $examination->class_name)
                ->where('is_active', true)
                ->orderBy('last_name')
                ->get();

            $existing = $subject->scores()->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');
        }

        return view('school-admin.examinations.score-entry', [
            'school' => $school,
            'examination' => $examination,
            'subject' => $subject,
            'students' => $students,
            'existing' => $existing,
            'classNames' => $school->configuredClassNames(),
            'sessionOptions' => AcademicSession::options(),
            'termOptions' => ExamTerm::cases(),
            'selectedClass' => $className,
            'selectedSession' => $session,
            'selectedTerm' => $term,

            // Whether the class has subjects to build a paper from, so the page
            // can offer to create the missing examination instead of sending
            // the admin away to do it and come back.
            'offeredSubjectCount' => $className === '' ? 0 : SubjectOffering::where('school_id', $school->id)
                ->where('class_name', $className)
                ->count(),
        ]);
    }

    /**
     * Create the missing examination from the score-entry page itself.
     *
     * Reaching score entry only to be told to go and set up an examination,
     * then come back and re-choose the same three things, is the kind of errand
     * that gets a feature written off as broken. The paper is built from the
     * subjects the class is actually offered, so it matches the timetable
     * rather than being an empty shell that still cannot take a mark.
     */
    public function storeExaminationForEntry(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'class_name' => ['required', 'string', 'max:50'],
            'session' => ['required', 'string', 'max:20'],
            'term' => ['required', Rule::enum(ExamTerm::class)],
        ]);

        $term = ExamTerm::from($validated['term']);

        $subjects = SubjectOffering::with('subject')
            ->where('school_id', $school->id)
            ->where('class_name', $validated['class_name'])
            ->get()
            ->pluck('subject.name')
            ->filter()
            ->values();

        if ($subjects->isEmpty()) {
            return back()->withErrors([
                'class_name' => "No subjects are set up for {$validated['class_name']} yet. Add them on the Class Subjects page, then come back.",
            ]);
        }

        $examination = DB::transaction(function () use ($school, $validated, $term, $subjects) {
            $examination = $school->examinations()->firstOrCreate(
                [
                    'class_name' => $validated['class_name'],
                    'session' => $validated['session'],
                    'term' => $term,
                ],
                [
                    'name' => $term->label().' Examination',
                    'exam_date' => now()->toDateString(),
                ],
            );

            foreach ($subjects as $name) {
                $examination->subjects()->firstOrCreate(['name' => $name], ['max_score' => 100]);
            }

            return $examination;
        });

        return redirect()->route('examinations.score-entry', [
            'class_name' => $examination->class_name,
            'session' => $examination->session,
            'term' => $examination->term->value,
        ])->with('status', "{$examination->name} was created with {$subjects->count()} subject(s). You can enter scores now.");
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
                    // The two marks are what a teacher enters. The total is
                    // their sum, worked out here rather than accepted from the
                    // form, so a total can never disagree with the marks it
                    // came from - and the grade and remark are derived from it
                    // on read, against this school's own bands.
                    'test_score' => $testScore,
                    'exam_score' => $examScore,
                    'score' => (float) $testScore + (float) $examScore,
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
