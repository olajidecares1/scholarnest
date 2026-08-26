<?php

namespace App\Services;

use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Is this report card finished enough to publish?
 *
 * The repository is what a parent reads, so a half-entered card must not reach
 * it. A parent who opens a report card with three subjects on it and comes
 * back to find nine has not been given a corrected result - they were given an
 * unfinished one and told it was final.
 *
 * The answer is a LIST of what is missing rather than a yes or no, because
 * "this result is incomplete" is not something a teacher can act on. "No score
 * recorded for Mathematics and Civic Education" is.
 *
 * Remarks are not required. A class-teacher comment is expected on a Nigerian
 * report card and its absence is worth mentioning, but it is prose rather than
 * a mark: blocking a whole class's results on one unwritten sentence would
 * push schools into typing a full stop to get past it, which is worse than an
 * empty remark honestly shown.
 */
class ResultCompleteness
{
    /**
     * Everything standing between this result and the repository.
     *
     * @return list<string> Empty when the result may be published.
     */
    public function blockers(Examination $examination, Student $student): array
    {
        $subjects = $this->subjectsWithScoresFor($examination, $student);

        if ($subjects->isEmpty()) {
            return ['This examination has no subjects yet, so there is no result to publish.'];
        }

        $blockers = [];

        $ungraded = $subjects->filter(fn (ExaminationSubject $subject) => $subject->scores->isEmpty());

        if ($ungraded->isNotEmpty()) {
            $blockers[] = 'No score has been recorded for '.$this->listOf($ungraded->pluck('name')).'.';
        }

        // A total with no test or exam mark behind it is a figure nobody can
        // check. The score-entry grid always writes both, so this catches
        // rows that predate it or were written some other way.
        $incomplete = $subjects->filter(function (ExaminationSubject $subject) {
            $score = $subject->scores->first();

            return $score !== null && ($score->test_score === null || $score->exam_score === null);
        });

        if ($incomplete->isNotEmpty()) {
            $blockers[] = 'Both the test and examination marks are needed for '
                .$this->listOf($incomplete->pluck('name')).'.';
        }

        return $blockers;
    }

    public function isComplete(Examination $examination, Student $student): bool
    {
        return $this->blockers($examination, $student) === [];
    }

    /**
     * Worth saying, but not worth refusing over.
     *
     * @return list<string>
     */
    public function advisories(Examination $examination, Student $student): array
    {
        $report = $examination->reports()
            ->where('student_id', $student->id)
            ->first();

        $advisories = [];

        if (blank($report?->teacher_remark)) {
            $advisories[] = 'There is no class teacher remark on this result.';
        }

        if (blank($report?->principal_remark)) {
            $advisories[] = 'There is no principal remark on this result.';
        }

        return $advisories;
    }

    /**
     * @return Collection<int, ExaminationSubject>
     */
    private function subjectsWithScoresFor(Examination $examination, Student $student): Collection
    {
        return $examination->subjects()
            ->with(['scores' => fn ($query) => $query->where('student_id', $student->id)])
            ->get();
    }

    /**
     * "Mathematics", or "Mathematics and English", or "Mathematics, English
     * and Civic Education" - a sentence rather than a comma-separated dump.
     *
     * @param  Collection<int, string>  $names
     */
    private function listOf(Collection $names): string
    {
        $names = $names->values();

        if ($names->count() === 1) {
            return $names->first();
        }

        return $names->slice(0, -1)->implode(', ').' and '.$names->last();
    }
}
