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
    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');
        $assignments = $staff->subjectAssignments();

        $examinations = $school->examinations()
            ->whereIn('class_name', $assignments->pluck('class_name')->unique())
            ->with('subjects')
            ->orderByDesc('exam_date')
            ->get()
            ->flatMap(function (Examination $examination) use ($assignments) {
                $subjectNames = $assignments->where('class_name', $examination->class_name)->pluck('subject');

                return $examination->subjects
                    ->filter(fn (ExaminationSubject $subject) => $subjectNames->contains($subject->name))
                    ->map(fn (ExaminationSubject $subject) => ['examination' => $examination, 'subject' => $subject]);
            });

        return view('staff.exams.index', [
            'school' => $school,
            'examinations' => $examinations,
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

        $staff = $request->user('staff');
        $isAssigned = $staff->subjectAssignments()
            ->contains(fn ($assignment) => $assignment['class_name'] === $examination->class_name && $assignment['subject'] === $subject->name);

        abort_unless($isAssigned, 403, 'You are not assigned to teach this subject.');
    }
}
