<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExaminationController extends Controller
{
    /**
     * Everything this teacher may enter marks against, narrowed by class and
     * subject.
     *
     * The class and subject the teacher picks are checked against what they are
     * actually assigned rather than trusted: a filter is a convenience for
     * finding a row, never a way to reach one that was not already theirs.
     */
    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');

        $rows = $school->examinations()
            ->whereIn('class_name', $staff->scorableClassNames())
            ->with('subjects')
            ->orderByDesc('exam_date')
            ->get()
            ->flatMap(fn (Examination $examination) => $examination->subjects
                ->filter(fn (ExaminationSubject $subject) => $staff->canEnterScoresFor($examination->class_name, $subject->name))
                ->map(fn (ExaminationSubject $subject) => ['examination' => $examination, 'subject' => $subject]));

        // The options offered are drawn from the rows themselves, so a teacher
        // is never shown a class or subject that would return nothing.
        $classOptions = $rows->pluck('examination.class_name')->unique()->sort()->values();
        $subjectOptions = $rows->pluck('subject.name')->unique()->sort()->values();

        $selectedClass = $request->string('class_name')->toString();
        $selectedSubject = $request->string('subject')->toString();

        $examinations = $rows
            ->when($selectedClass !== '', fn ($rows) => $rows->filter(
                fn (array $row) => $row['examination']->class_name === $selectedClass
            ))
            ->when($selectedSubject !== '', fn ($rows) => $rows->filter(
                fn (array $row) => $row['subject']->name === $selectedSubject
            ))
            ->values();

        return view('staff.exams.index', [
            'school' => $school,
            'examinations' => $examinations,
            'classOptions' => $classOptions,
            'subjectOptions' => $subjectOptions,
            'selectedClass' => $selectedClass,
            'selectedSubject' => $selectedSubject,

            // Whether anything exists before filtering, so a filter that
            // matches nothing is not mistaken for having no classes at all.
            'hasRows' => $rows->isNotEmpty(),

            // Told apart so the empty state can say which it is. "No
            // examinations match your subjects" is misleading when the real
            // reason is that nobody has assigned the teacher to a class, or
            // that no examination exists for the term yet.
            'hasAssignments' => $staff->scorableClassNames()->isNotEmpty(),
        ]);
    }

    public function edit(Request $request, School $school, Examination $examination, ExaminationSubject $subject): View
    {
        $this->authorizeSubject($request, $examination, $subject);

        $students = Student::where('school_id', $examination->school_id)
            ->where('class_name', $examination->class_name)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get();

        $existing = $subject->scores()->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');

        return view('staff.exams.scores', [
            'school' => $school,
            'examination' => $examination,
            'subject' => $subject,
            'students' => $students,
            'existing' => $existing,
        ]);
    }

    public function update(Request $request, School $school, Examination $examination, ExaminationSubject $subject): RedirectResponse
    {
        $this->authorizeSubject($request, $examination, $subject);

        $validated = $request->validate([
            'test_scores' => ['required', 'array'],
            'test_scores.*' => ['nullable', 'numeric', 'min:0', 'max:'.$subject->testMaxScore()],
            'exam_scores' => ['required', 'array'],
            'exam_scores.*' => ['nullable', 'numeric', 'min:0', 'max:'.$subject->examMaxScore()],
        ]);

        $studentIds = array_unique([...array_keys($validated['test_scores']), ...array_keys($validated['exam_scores'])]);
        $students = Student::where('school_id', $examination->school_id)
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
                ],
            );
            $saved++;
        }

        return redirect()->route('staff.exams.index', $examination->school)->with('status', "Saved scores for {$saved} student(s) in {$subject->name}.");
    }

    private function authorizeSubject(Request $request, Examination $examination, ExaminationSubject $subject): void
    {
        abort_unless($subject->examination_id === $examination->id, 404);

        // The same authority the listing filters by, so a subject that is
        // listed is always one that can actually be opened.
        abort_unless(
            $request->user('staff')->canEnterScoresFor($examination->class_name, $subject->name),
            403,
            'You are not assigned to enter scores for this subject.',
        );
    }
}
