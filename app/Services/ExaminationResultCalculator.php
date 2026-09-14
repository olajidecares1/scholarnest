<?php

namespace App\Services;

use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\Student;
use Illuminate\Support\Collection;

class ExaminationResultCalculator
{
    /**
     * Every active student in the examination's class, with subjects-graded
     * count, raw average score, average percentage, and tie-aware rank
     * position (nulls last).
     *
     * @return Collection<int, array{student: Student, subjectsGraded: int, averageScore: ?float, average: ?float, position: ?int}>
     */
    public static function summariesFor(Examination $examination): Collection
    {
        $students = Student::where('school_id', $examination->school_id)
            ->where('class_name', $examination->class_name)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get();

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
     * @return array{student: Student, subjectsGraded: int, averageScore: ?float, average: ?float, position: ?int}
     */
    public static function summaryForStudent(Student $student, Collection $subjects): array
    {
        $scores = $subjects->map(fn ($subject) => $subject->scores->firstWhere('student_id', $student->id))->filter();
        $percentages = $scores->map(fn ($score) => $score->percentage());

        return [
            'student' => $student,
            'subjectsGraded' => $scores->count(),
            'averageScore' => $scores->isNotEmpty() ? round($scores->avg(fn ($score) => (float) $score->score), 1) : null,
            'average' => $percentages->isNotEmpty() ? round($percentages->avg(), 1) : null,
            'position' => null,
        ];
    }

    /**
     * A student's computed result status for the given examination, based
     * on how many of its subjects they have a recorded score for.
     */
    public static function statusFor(Examination $examination, int $subjectsGraded): string
    {
        $subjectCount = $examination->subjects->count();

        return match (true) {
            $subjectCount === 0 || $subjectsGraded === 0 => 'Not Started',
            $subjectsGraded < $subjectCount => 'In Progress',
            default => 'Complete',
        };
    }
}
