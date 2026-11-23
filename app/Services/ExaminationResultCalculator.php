<?php

namespace App\Services;

use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Student;
use Illuminate\Support\Collection;

class ExaminationResultCalculator
{
    /**
     * Who sat an examination, the class list its results are drawn from.
     *
     * THE CURRENT SESSION: every active student in the class now, exactly as
     * it always was, because this is the class being taught and scored, and a
     * pupil with no marks yet still belongs on the score sheet.
     *
     * A PAST SESSION: the students who have a recorded score in it, whatever
     * class they are in today and whether or not they are still active. The
     * class list used to be today's class list for every examination, so the
     * moment pupils were moved up a class their old results fell out of the
     * old class: last year's JSS 1 First Term showed this year's JSS 1 pupils,
     * all blank, and the pupils who actually sat it could not be found there.
     * Their scores were never lost; they were being looked for in the wrong
     * place. Now a past term is shown as it was saved, with nothing to
     * re-enter.
     *
     * @return Collection<int, Student>
     */
    public static function rosterFor(Examination $examination): Collection
    {
        $school = $examination->relationLoaded('school') ? $examination->school : $examination->school()->first();

        // "Past" means OLDER than the school's current session. A session
        // that is current or newer is still being taught, including for a
        // school that has not yet moved its current session on in Settings.
        if ($school === null || strcmp((string) $examination->session, $school->currentSession()) >= 0) {
            return Student::where('school_id', $examination->school_id)
                ->where('class_name', $examination->class_name)
                ->where('is_active', true)
                ->alphabetical()
                ->get();
        }

        return Student::where('school_id', $examination->school_id)
            ->whereIn('id', ExaminationScore::query()
                ->whereNotNull('score')
                ->whereIn('examination_subject_id', $examination->subjects()->select('id'))
                ->select('student_id'))
            ->alphabetical()
            ->get();
    }

    /**
     * Every student who sat the examination (see rosterFor), with subjects-graded
     * count, raw average score, average percentage, and tie-aware rank
     * position (nulls last).
     *
     * @return Collection<int, array{student: Student, subjectsGraded: int, subjectsMissing: int, averageScore: ?float, average: ?float, position: ?int}>
     */
    public static function summariesFor(Examination $examination): Collection
    {
        $students = self::rosterFor($examination);

        $subjects = $examination->subjects;

        $summaries = $students->map(fn ($student) => self::summaryForStudent($student, $subjects))
            ->sortByDesc(fn ($summary) => $summary['average'] ?? -1)->values();

        $rank = 0;
        $lastAverage = null;

        return $summaries->map(function ($summary) use (&$rank, &$lastAverage) {
            if ($summary['average'] !== null && $summary['average'] !== $lastAverage) {
                $rank++;
                $lastAverage = $summary['average'];
            }
            $summary['position'] = $summary['average'] !== null ? $rank : null;

            return $summary;
        });
    }

    /**
     * A single student's average/subjects-graded, computed independently of
     * the class-wide summary list (which only covers active students whose
     * class_name matches the examination), used as a fallback for students
     * who fall outside that list, e.g. transferred or since-deactivated.
     *
     * @param  Collection<int, ExaminationSubject>  $subjects
     * @return array{student: Student, subjectsGraded: int, subjectsMissing: int, averageScore: ?float, average: ?float, position: ?int}
     */
    public static function summaryForStudent(Student $student, Collection $subjects): array
    {
        $scores = $subjects->map(fn ($subject) => $subject->scores->firstWhere('student_id', $student->id))->filter();
        $percentages = $scores->map(fn ($score) => $score->percentage());

        return [
            'student' => $student,
            'subjectsGraded' => $scores->count(),

            // Subjects still to be scored, counting a combined subject and
            // its separate subjects as alternatives (CombinedSubjects).
            'subjectsMissing' => count(CombinedSubjects::missing(
                $subjects->pluck('name'),
                $subjects->filter(fn ($subject) => $subject->scores->firstWhere('student_id', $student->id) !== null)->pluck('name'),
            )),
            'averageScore' => $scores->isNotEmpty() ? round($scores->avg(fn ($score) => (float) $score->score), 1) : null,
            'average' => $percentages->isNotEmpty() ? round($percentages->avg(), 1) : null,
            'position' => null,
        ];
    }

    /**
     * A student's computed result status for the given examination, based
     * on how many of its subjects they have a recorded score for.
     */
    public static function statusFor(Examination $examination, int $subjectsGraded, ?int $subjectsMissing = null): string
    {
        $subjectCount = $examination->subjects->count();
        $subjectsMissing ??= max(0, $subjectCount - $subjectsGraded);

        return match (true) {
            $subjectCount === 0 || $subjectsGraded === 0 => 'Not Started',
            $subjectsMissing > 0 => 'In Progress',
            default => 'Complete',
        };
    }
}
