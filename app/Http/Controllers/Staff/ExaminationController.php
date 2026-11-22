<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ExamTerm;
use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Services\ExaminationSubjectSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ExaminationController extends Controller
{
    /**
     * The teacher's Test/Exam Score page, laid out like the School Admin's:
     * Class, Academic Year, Term and Subject, then one subject's sheet with a
     * Test and an Examination box for every student, and the subject chips
     * to move from one subject to the next.
     *
     * Only the classes and subjects this teacher is assigned to are ever
     * offered: a class teacher gets every subject of their class, a subject
     * teacher only the subjects they teach. Whatever the query string asks
     * for is checked against that list, never trusted.
     */
    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');

        // The classes the School Admin assigned to this teacher, never a
        // free choice: the class they are class teacher of first, then any
        // class they teach a subject in.
        $classTeacherOf = $staff->classTeacherClassNames()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        $classNames = $classTeacherOf
            ->merge($staff->scorableClassNames()->diff($classTeacherOf)->sort(SORT_NATURAL | SORT_FLAG_CASE))
            ->values();

        // Every class this teacher scores has this term's examination, with
        // all of the class's subjects on it, before anything is shown.
        foreach ($classNames as $className) {
            ExaminationSubjectSync::prepareForScoring($school, (string) $className);
        }

        $examinationsByClass = $this->examinationsByClass($school, $staff, $classNames);

        // Only classes that have something to score can be picked.
        $classOptions = $classNames->filter(fn ($name) => $examinationsByClass->has($name))->values();

        $requestedClass = $request->string('class_name')->toString();
        $selectedClass = $classOptions->contains($requestedClass) ? $requestedClass : (string) $classOptions->first();

        [$currentSession, $currentTerm] = ExaminationSubjectSync::currentTerm($school);
        $classExaminations = $examinationsByClass->get($selectedClass) ?? collect();

        // A link to one examination (e.g. from a saved message) picks its
        // year and term. With nothing picked yet, the page opens on this
        // term's paper, or failing that the class's most recent one.
        $linked = $classExaminations->firstWhere('uuid', $request->string('examination')->toString());

        if (! $linked && ! $request->filled('session') && ! $request->filled('term')) {
            $linked = $classExaminations->first(fn (Examination $option) => $option->session === $currentSession && $option->term === $currentTerm)
                ?? $classExaminations->sortByDesc(fn (Examination $option) => [(string) $option->session, $option->term->value, (int) $option->id])->first();
        }

        // The years this class has papers in, newest first, always including
        // the current one.
        $sessionOptions = $classExaminations->pluck('session')
            ->push($currentSession)
            ->unique()
            ->sortDesc()
            ->values();

        $requestedSession = $linked?->session ?? $request->string('session')->toString();
        $selectedSession = $sessionOptions->contains($requestedSession) ? $requestedSession : $currentSession;

        $selectedTerm = $linked?->term
            ?? ExamTerm::tryFrom($request->string('term')->toString())
            ?? ($selectedSession === $currentSession
                ? $currentTerm
                : $classExaminations->where('session', $selectedSession)->sortByDesc(fn ($e) => $e->term->value)->first()?->term)
            ?? ExamTerm::First;

        $findExamination = fn (Collection $examinations) => $examinations->first(
            fn (Examination $option) => $option->session === $selectedSession && $option->term === $selectedTerm,
        );

        $examination = $findExamination($classExaminations);

        // Another term of the current year, picked before an examination
        // exists for it, gets one, with the class's subjects, as the current
        // term does. Past years are only ever read as they are.
        if (! $examination && $selectedClass !== '' && $selectedSession === $currentSession) {
            ExaminationSubjectSync::prepareForScoring($school, $selectedClass, $selectedTerm);
            $examination = $findExamination($this->examinationsByClass($school, $staff, collect([$selectedClass]))->get($selectedClass) ?? collect());
        }

        $subject = null;
        $students = collect();
        $existing = collect();

        if ($examination) {
            $subject = $examination->subjects->firstWhere('name', $request->string('subject')->toString())
                ?? $examination->subjects->first();

            $students = Student::where('school_id', $school->id)
                ->where('class_name', $examination->class_name)
                ->where('is_active', true)
                ->alphabetical()
                ->get();

            $existing = $subject
                ? $subject->scores()->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id')
                : collect();
        }

        return view('staff.exams.index', [
            'school' => $school,
            'examination' => $examination,
            'subject' => $subject,
            'students' => $students,
            'existing' => $existing,
            'classOptions' => $classOptions,
            'classTeacherOf' => $classTeacherOf,
            'sessionOptions' => $sessionOptions,
            'termOptions' => ExamTerm::cases(),
            'selectedClass' => $selectedClass,
            'selectedSession' => $selectedSession,
            'selectedTerm' => $selectedTerm,

            // Whether anything exists at all, so the empty state can say
            // which of the reasons it is.
            'hasRows' => $classOptions->isNotEmpty(),
            'hasAssignments' => $classNames->isNotEmpty(),
        ]);
    }

    /**
     * This teacher's classes' examinations, each carrying only the subjects
     * this teacher may score, in result-sheet order; examinations with none
     * of those are left out.
     *
     * @param  Collection<int, string>  $classNames
     * @return Collection<string, Collection<int, Examination>>
     */
    private function examinationsByClass(School $school, Staff $staff, Collection $classNames): Collection
    {
        return $school->examinations()
            ->whereIn('class_name', $classNames)
            ->with('subjects')
            ->get()
            ->map(function (Examination $examination) use ($staff) {
                $examination->setRelation('subjects', $examination->subjectsInResultSheetOrder()
                    ->filter(fn (ExaminationSubject $subject) => $staff->canEnterScoresFor($examination->class_name, $subject->name))
                    ->values());

                return $examination;
            })
            ->filter(fn (Examination $examination) => $examination->subjects->isNotEmpty())
            ->groupBy('class_name');
    }

    /**
     * Saves the whole sheet: every subject on the page, every student, Test
     * and Examination.
     *
     * The page sends its scores as one JSON field. A class of 40 with 14
     * subjects is over a thousand boxes, past the number of form fields PHP
     * accepts by default (max_input_vars), and the ones past it would be
     * dropped without a word. The ordinary form fields still work when the
     * script has not run.
     */
    public function updateClass(Request $request, School $school, Examination $examination): RedirectResponse
    {
        abort_unless((int) $examination->school_id === (int) $school->id, 404);

        $staff = $request->user('staff');

        $subjects = $examination->subjects()->get()
            ->filter(fn (ExaminationSubject $subject) => $staff->canEnterScoresFor($examination->class_name, $subject->name))
            ->keyBy('id');

        abort_if($subjects->isEmpty(), 403, 'You are not assigned to enter scores for this class.');

        $scores = $request->input('scores', []);

        if ($request->filled('scores_json')) {
            $request->validate(['scores_json' => ['string', 'max:2000000']]);
            $decoded = json_decode((string) $request->input('scores_json'), true);
            abort_unless(is_array($decoded), 422, 'The scores could not be read. Please try again.');
            $scores = $decoded;
        }

        // Only this paper's subjects that this teacher may score, and only
        // this class's active students. Anything else in the request is
        // ignored, not trusted.
        $scores = collect(is_array($scores) ? $scores : [])
            ->only($subjects->keys()->map(fn ($id) => (string) $id)->all())
            ->all();

        $rules = ['scores' => ['array']];
        $attributes = [];

        foreach ($subjects as $id => $subject) {
            $rules["scores.{$id}"] = ['array'];
            $rules["scores.{$id}.*"] = ['array'];
            $rules["scores.{$id}.*.test"] = ['nullable', 'numeric', 'min:0', 'max:'.$subject->testMaxScore()];
            $rules["scores.{$id}.*.exam"] = ['nullable', 'numeric', 'min:0', 'max:'.$subject->examMaxScore()];
            $attributes["scores.{$id}.*.test"] = "{$subject->name} test score";
            $attributes["scores.{$id}.*.exam"] = "{$subject->name} examination score";
        }

        $validated = Validator::make(['scores' => $scores], $rules, [], $attributes)->validate();

        $studentIds = Student::where('school_id', $school->id)
            ->where('class_name', $examination->class_name)
            ->where('is_active', true)
            ->pluck('id')
            ->flip();

        $saved = 0;
        $subjectsSaved = 0;

        DB::transaction(function () use ($validated, $subjects, $studentIds, &$saved, &$subjectsSaved) {
            foreach ($validated['scores'] ?? [] as $subjectId => $rows) {
                $subject = $subjects->get((int) $subjectId);
                $savedHere = 0;

                foreach ((array) $rows as $studentId => $row) {
                    $test = self::score($row['test'] ?? null);
                    $exam = self::score($row['exam'] ?? null);

                    // Both blank: nothing typed for this student, so nothing
                    // is recorded and nothing already there is touched.
                    if (! $subject || ! $studentIds->has((int) $studentId) || ($test === null && $exam === null)) {
                        continue;
                    }

                    $subject->scores()->updateOrCreate(
                        ['student_id' => (int) $studentId],
                        ['test_score' => $test, 'exam_score' => $exam, 'score' => (float) $test + (float) $exam],
                    );
                    $savedHere++;
                }

                $saved += $savedHere;
                $subjectsSaved += $savedHere > 0 ? 1 : 0;
            }
        });

        return redirect()
            ->route('staff.exams.index', [
                $school,
                'class_name' => $examination->class_name,
                'examination' => $examination->uuid,
            ])
            ->with('status', $saved > 0
                ? "Saved {$saved} score(s) across {$subjectsSaved} subject(s) for {$examination->class_name}."
                : 'Nothing to save: no scores were entered.');
    }

    /**
     * A typed score as a number, or null when the box was left empty.
     */
    private static function score(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    public function edit(Request $request, School $school, Examination $examination, ExaminationSubject $subject): View
    {
        $this->authorizeSubject($request, $examination, $subject);

        $students = Student::where('school_id', $examination->school_id)
            ->where('class_name', $examination->class_name)
            ->where('is_active', true)
            ->alphabetical()
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

        // Back to the same class, year, term and subject, ready for the next.
        return redirect()
            ->route('staff.exams.index', [
                $examination->school,
                'class_name' => $examination->class_name,
                'session' => $examination->session,
                'term' => $examination->term->value,
                'subject' => $subject->name,
            ])
            ->with('status', "Saved scores for {$saved} student(s) in {$subject->name}.");
    }

    private function authorizeSubject(Request $request, Examination $examination, ExaminationSubject $subject): void
    {
        abort_unless($subject->examination_id === $examination->id, 404);

        // Another school's paper is not reachable through this school's
        // portal, whatever its class is called.
        abort_unless((int) $examination->school_id === (int) $request->user('staff')->school_id, 404);

        // The same authority the listing filters by, so a subject that is
        // listed is always one that can actually be opened.
        abort_unless(
            $request->user('staff')->canEnterScoresFor($examination->class_name, $subject->name),
            403,
            'You are not assigned to enter scores for this subject.',
        );
    }
}
