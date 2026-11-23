<?php

namespace App\Services;

use App\Enums\ExamTerm;
use App\Models\Examination;
use App\Models\School;
use App\Models\Student;
use App\Support\AcademicSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every result a school has ever saved, organised by session, term and class.
 *
 * Nothing is copied or re-entered to make this: a term's results are the
 * marks saved against its examination, and they stay there. What this adds is
 * the way back to them. The Results page offered only the current year and two
 * either side, and only today's class names, so a term from three years ago,
 * or a class that has since been renamed, could not be opened at all even
 * though every mark was still on file.
 */
class ResultArchive
{
    /**
     * Every academic session a school can look at: each one it has results
     * (or examinations) in, plus the usual years around today, newest first.
     *
     * @return list<string>
     */
    public function sessions(School $school): array
    {
        return Examination::query()
            ->where('school_id', $school->id)
            ->distinct()
            ->pluck('session')
            ->merge(AcademicSession::options())
            ->push($school->currentSession())
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * The classes to offer for a session: the school's classes in their
     * usual order, then any class name its examinations that session were
     * held under that no longer exists (renamed, merged, retired).
     *
     * @return array<string, string> name => name
     */
    public function classes(School $school, ?string $session = null): array
    {
        $configured = $school->academicLevels()->with('classes')->get()->flatMap->classes->pluck('name');

        $held = Examination::query()
            ->where('school_id', $school->id)
            ->when($session !== null, fn ($query) => $query->where('session', $session))
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name');

        return $configured->merge($held)->filter()->unique()->mapWithKeys(fn ($name) => [$name => $name])->all();
    }

    /**
     * How many students have saved results in each class, term and session.
     *
     * One query for the whole school.
     *
     * @return Collection<string, Collection<string, Collection<int, array{examination_id: int, uuid: string, class_name: string, students: int, subjects: int}>>>
     *                                                                                                                                                             session => term => classes
     */
    public function overview(School $school): Collection
    {
        $rows = DB::table('examinations')
            ->join('examination_subjects', 'examination_subjects.examination_id', '=', 'examinations.id')
            ->join('examination_scores', 'examination_scores.examination_subject_id', '=', 'examination_subjects.id')
            ->where('examinations.school_id', $school->id)
            ->whereNotNull('examination_scores.score')
            ->groupBy('examinations.id', 'examinations.uuid', 'examinations.session', 'examinations.term', 'examinations.class_name')
            ->select([
                'examinations.id',
                'examinations.uuid',
                'examinations.session',
                'examinations.term',
                'examinations.class_name',
                DB::raw('COUNT(DISTINCT examination_scores.student_id) as students'),
                DB::raw('COUNT(DISTINCT examination_subjects.id) as subjects'),
            ])
            ->get();

        $classOrder = array_flip(array_keys($this->classes($school)));
        $termOrder = array_flip(array_map(fn (ExamTerm $term) => $term->value, ExamTerm::cases()));

        return $rows
            ->sortBy(fn ($row) => [$classOrder[$row->class_name] ?? PHP_INT_MAX, $row->class_name])
            ->groupBy('session')
            ->sortKeysDesc()
            ->map(fn (Collection $session) => $session
                ->groupBy('term')
                ->sortBy(fn ($group, $term) => $termOrder[$term] ?? PHP_INT_MAX)
                ->map(fn (Collection $classes) => $classes->map(fn ($row) => [
                    'examination_id' => (int) $row->id,
                    'uuid' => (string) $row->uuid,
                    'class_name' => (string) $row->class_name,
                    'students' => (int) $row->students,
                    'subjects' => (int) $row->subjects,
                ])->values()));
    }

    /**
     * One student's results for every term they have ever sat, newest
     * session first and in term order within it, each with the class they
     * sat it in and their position in that class.
     *
     * @return Collection<string, list<array<string, mixed>>> session => terms
     */
    public function historyFor(Student $student): Collection
    {
        $termOrder = array_flip(array_map(fn (ExamTerm $term) => $term->value, ExamTerm::cases()));

        $examinations = Examination::query()
            ->where('school_id', $student->school_id)
            ->whereHas('subjects.scores', fn ($query) => $query->where('student_id', $student->id)->whereNotNull('score'))
            ->with(['school', 'subjects.scores'])
            ->get()
            ->sortBy(fn (Examination $examination) => [$termOrder[$examination->term->value], $examination->id]);

        return $examinations
            ->groupBy('session')
            ->sortKeysDesc()
            ->map(fn (Collection $terms) => $terms->map(function (Examination $examination) use ($student): array {
                $examination->subjects->each(fn ($subject) => $subject->scores->each->setRelation('subject', $subject));

                $summary = ExaminationResultCalculator::summariesFor($examination)->firstWhere('student.id', $student->id);
                $recorded = $examination->subjects->filter(
                    fn ($subject) => $subject->scores->contains(fn ($score) => $score->student_id === $student->id && $score->score !== null),
                );

                return [
                    'examination' => $examination,
                    'term' => $examination->term,
                    'class_name' => $examination->class_name,
                    'subjects' => $recorded->count(),
                    'total' => round($recorded->sum(fn ($subject) => (float) $subject->scores->firstWhere('student_id', $student->id)->score), 2),
                    'max' => (int) $recorded->sum('max_score'),
                    'average' => $summary['average'] ?? ExaminationResultCalculator::summaryForStudent($student, $examination->subjects)['average'],
                    'position' => $summary['position'] ?? null,
                    'number_in_class' => ExaminationResultCalculator::rosterFor($examination)->count(),
                ];
            })->values()->all());
    }
}
