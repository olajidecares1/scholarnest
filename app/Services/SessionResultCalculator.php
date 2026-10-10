<?php

namespace App\Services;

use App\Enums\CumulativeAverageBasis;
use App\Enums\ExamTerm;
use App\Models\Examination;
use App\Models\GradeBand;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Cumulative session results: First + Second + Third Term, for one student or
 * a whole class.
 *
 * READ ONLY. Nothing here writes a mark, a grade or a report, and nothing in
 * the term-by-term result processing calls it, so turning the setting on or off
 * cannot change a single term result. It only reads what those term results
 * already are.
 *
 * Built on the school's own rules rather than a second set of them:
 *
 *   - a term's average is ExaminationResultCalculator's average, the same
 *     figure printed on that term's report card;
 *   - a subject's term mark is ExaminationScore::percentage(), the same figure
 *     that term's grade was read from;
 *   - every grade and remark is read from the school's grade bands through
 *     GradeBand, so a school with its own scale is graded on it here too;
 *   - ranking breaks ties exactly as the term position does.
 *
 * What it returns is plain arrays, so a published report card can store it
 * as it is (see PublishedResultData) and never recalculate it later.
 */
class SessionResultCalculator
{
    /**
     * Every student in a class, ranked on their session average.
     *
     * @return Collection<int, array<string, mixed>> each row is a session result plus 'student'
     */
    public function forClass(School $school, string $className, string $session): Collection
    {
        $students = Student::where('school_id', $school->id)
            ->where('class_name', $className)
            ->where('is_active', true)
            ->alphabetical()
            ->get();

        return $this->build($school, $students, $className, $session);
    }

    /**
     * One student's session result, positioned within their class.
     *
     * Null when the student has no result in any term of the session.
     *
     * @return array<string, mixed>|null
     */
    public function forStudent(School $school, Student $student, string $className, string $session): ?array
    {
        $students = Student::where('school_id', $school->id)
            ->where('class_name', $className)
            ->where('is_active', true)
            ->alphabetical()
            ->get();

        // A student who has since left the class, or been deactivated, still
        // gets their own figures, just as the term card falls back for them.
        if (! $students->contains('id', $student->id)) {
            $students->push($student);
        }

        $row = $this->build($school, $students, $className, $session)->firstWhere('student.id', $student->id);

        if (! $row || $row['termsCounted'] === 0) {
            return null;
        }

        unset($row['student']);

        return $row;
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array<string, mixed>>
     */
    private function build(School $school, Collection $students, string $className, string $session): Collection
    {
        $school->loadMissing('gradeBands');
        $basis = $school->cumulative_average_basis ?? CumulativeAverageBasis::TermsTaken;
        $studentIds = $students->pluck('id')->all();

        $examinations = Examination::query()
            ->where('school_id', $school->id)
            ->where('session', $session)
            ->with(['subjects' => fn ($query) => $query->orderBy('id'), 'subjects.scores' => fn ($query) => $query->whereIn('student_id', $studentIds)])
            ->get();

        // percentage() reads the subject's maximum through the relation; set
        // it here so a class of forty is not forty queries per subject.
        $examinations->each(fn (Examination $examination) => $examination->subjects->each(
            fn ($subject) => $subject->scores->each->setRelation('subject', $subject),
        ));

        $byTerm = $examinations->groupBy(fn (Examination $examination) => $examination->term->value);

        $rows = $students->map(fn (Student $student) => [
            'student' => $student,
            ...$this->forOne($school, $basis, $student, $className, $session, $byTerm),
        ]);

        return $this->rank($rows)->values();
    }

    /**
     * @param  Collection<string, Collection<int, Examination>>  $byTerm
     * @return array<string, mixed>
     */
    private function forOne(School $school, CumulativeAverageBasis $basis, Student $student, string $className, string $session, Collection $byTerm): array
    {
        $terms = [];
        $subjects = [];

        foreach (ExamTerm::cases() as $term) {
            $examination = $this->examinationFor($byTerm->get($term->value, collect()), $student, $className);

            if (! $examination) {
                $terms[$term->value] = null;

                continue;
            }

            $summary = ExaminationResultCalculator::summaryForStudent($student, $examination->subjects);

            $terms[$term->value] = [
                'label' => $term->label(),
                'total' => round($examination->subjects->sum(fn ($subject) => (float) ($subject->scores->firstWhere('student_id', $student->id)?->score ?? 0)), 2),
                'max' => (int) $examination->subjects->sum('max_score'),
                'average' => $summary['average'],
            ];

            foreach ($examination->subjects as $subject) {
                $score = $subject->scores->firstWhere('student_id', $student->id);
                $key = mb_strtolower(trim($subject->name));

                $subjects[$key] ??= ['name' => $subject->name, 'terms' => []];
                $subjects[$key]['terms'][$term->value] = $score ? $score->percentage() : null;
            }
        }

        $taken = collect($terms)->filter(fn ($term) => $term !== null && $term['average'] !== null);
        $divisor = $basis->divisor($taken->count());
        $average = $taken->isNotEmpty() && $divisor > 0 ? round($taken->sum('average') / $divisor, 1) : null;

        return [
            'session' => $session,
            'className' => $className,
            'basis' => $basis->value,
            'terms' => $terms,
            'subjects' => collect($subjects)->map(fn (array $row) => $this->subjectRow($school, $basis, $row))->values()->all(),
            'total' => round(collect($terms)->filter()->sum('total'), 2),
            'max' => (int) collect($terms)->filter()->sum('max'),
            'termsCounted' => $taken->count(),
            'average' => $average,
            'grade' => $average === null ? 'N/A' : GradeBand::resolve($school, $average),
            'remark' => $average === null ? null : GradeBand::describe($school, $average),
            'position' => null,
            'numberInClass' => 0,
        ];
    }

    /**
     * @param  array{name: string, terms: array<string, ?float>}  $row
     * @return array<string, mixed>
     */
    private function subjectRow(School $school, CumulativeAverageBasis $basis, array $row): array
    {
        $marks = collect(ExamTerm::cases())->mapWithKeys(fn (ExamTerm $term) => [$term->value => $row['terms'][$term->value] ?? null]);
        $present = $marks->filter(fn ($mark) => $mark !== null);
        $divisor = $basis->divisor($present->count());
        $average = $present->isNotEmpty() && $divisor > 0 ? round($present->sum() / $divisor, 1) : null;

        return [
            'name' => $row['name'],
            'terms' => $marks->all(),
            'total' => $present->isNotEmpty() ? round($present->sum(), 1) : null,
            'average' => $average,
            'grade' => $average === null ? 'N/A' : GradeBand::resolve($school, $average),
            'remark' => $average === null ? null : GradeBand::describe($school, $average),
        ];
    }

    /**
     * The term's examination for this student: the class's own examination
     * when they have marks in it, otherwise any examination that term holding
     * their marks (a student moved between arms mid-session).
     *
     * @param  Collection<int, Examination>  $examinations
     */
    private function examinationFor(Collection $examinations, Student $student, string $className): ?Examination
    {
        $hasMarks = fn (Examination $examination) => $examination->subjects->contains(
            fn ($subject) => $subject->scores->contains('student_id', $student->id),
        );

        return $examinations->first(fn (Examination $examination) => $examination->class_name === $className && $hasMarks($examination))
            ?? $examinations->first($hasMarks);
    }

    /**
     * Highest session average first, ties sharing a position, exactly as
     * ExaminationResultCalculator ranks a term.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function rank(Collection $rows): Collection
    {
        $numberInClass = $rows->count();
        $rank = 0;
        $last = null;

        return $rows->sortByDesc(fn ($row) => $row['average'] ?? -1)->values()->map(function ($row) use (&$rank, &$last, $numberInClass) {
            if ($row['average'] !== null && $row['average'] !== $last) {
                $rank++;
                $last = $row['average'];
            }

            $row['position'] = $row['average'] !== null ? $rank : null;
            $row['numberInClass'] = $numberInClass;

            return $row;
        });
    }
}
