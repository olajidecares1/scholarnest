<?php

namespace App\Services;

use App\Enums\ExamTerm;
use App\Models\Examination;
use App\Models\RepositoryResult;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Publishing a result, and finding one that was published.
 *
 * Every write to the repository goes through here, which is what makes the
 * three rules in the brief properties of the system rather than habits of the
 * controllers that happen to call it:
 *
 *   The result belongs where it is filed. School, pupil, class, session and
 *   term are checked against each other before anything is written, because
 *   they arrive in an HTTP request and could be anything.
 *
 *   Nothing incomplete is published. See {@see ResultCompleteness}.
 *
 *   A second push is an update. The unique key on the table would refuse a
 *   duplicate anyway; this makes the refusal into the intended behaviour -
 *   the card is replaced, the version count goes up, and the school can see
 *   that it was corrected.
 */
class ResultRepository
{
    public function __construct(
        private readonly ResultCompleteness $completeness,
        private readonly PublishedResultData $publishedData,
    ) {}

    /**
     * Publish one pupil's report card.
     *
     * @param  Model  $pushedBy  The School Admin or member of staff responsible.
     *
     * @throws IncompleteResultException
     * @throws RuntimeException when the pupil, examination and school do not belong together.
     */
    public function publish(Examination $examination, Student $student, Model $pushedBy, string $pushedByName): RepositoryResult
    {
        $this->assertBelongTogether($examination, $student);

        if ($blockers = $this->completeness->blockers($examination, $student)) {
            throw new IncompleteResultException($blockers);
        }

        $card = ReportCardData::for($examination, $student);
        $payload = $this->publishedData->snapshot($card);
        $fingerprint = $this->fingerprintOf($examination, $student);

        return DB::transaction(function () use ($examination, $student, $pushedBy, $pushedByName, $payload, $fingerprint) {
            // Locked on the identifying key, so two people pressing the button
            // at the same moment produce one row and one version bump rather
            // than a unique-constraint error in front of whoever was slower.
            $existing = RepositoryResult::query()
                ->where('school_id', $examination->school_id)
                ->where('student_id', $student->id)
                ->where('class_name', $examination->class_name)
                ->where('session', $examination->session)
                ->where('term', $examination->term->value)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'examination_id' => $examination->id,
                'payload' => $payload,
                'source_fingerprint' => $fingerprint,
                'pushed_by_type' => $pushedBy->getMorphClass(),
                'pushed_by_id' => $pushedBy->getKey(),
                'pushed_by_name' => $pushedByName,
                'pushed_at' => now(),
            ];

            if ($existing) {
                $existing->update([...$attributes, 'version' => $existing->version + 1]);

                return $existing->refresh();
            }

            return RepositoryResult::create([
                'school_id' => $examination->school_id,
                'student_id' => $student->id,
                'class_name' => $examination->class_name,
                'session' => $examination->session,
                'term' => $examination->term->value,
                'version' => 1,
                ...$attributes,
            ]);
        });
    }

    /**
     * Publish a whole class at once, skipping the results that are not ready.
     *
     * Skipping rather than refusing the lot: a class of forty with two
     * unfinished cards should publish thirty-eight and say which two are
     * waiting, not make the school find them by hand.
     *
     * @return array{published: int, skipped: array<string, list<string>>}
     */
    public function publishClass(Examination $examination, Model $pushedBy, string $pushedByName): array
    {
        $students = Student::query()
            ->where('school_id', $examination->school_id)
            ->where('class_name', $examination->class_name)
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get();

        $published = 0;
        $skipped = [];

        foreach ($students as $student) {
            try {
                $this->publish($examination, $student, $pushedBy, $pushedByName);
                $published++;
            } catch (IncompleteResultException $e) {
                $skipped[$student->fullName()] = $e->blockers;
            }
        }

        return ['published' => $published, 'skipped' => $skipped];
    }

    /**
     * The published card for one pupil's term, if there is one.
     */
    public function find(School $school, Student $student, Examination $examination): ?RepositoryResult
    {
        return RepositoryResult::query()
            ->forSchool($school)
            ->where('student_id', $student->id)
            ->forTerm($examination->class_name, $examination->session, $examination->term)
            ->first();
    }

    /**
     * A hash of the marks and remarks a card is built from.
     *
     * Not of the card itself: the card also carries the pupil's position and
     * the class size, which move when a CLASSMATE's marks are entered. A
     * fingerprint that included those would tell a school its published cards
     * had changed every time somebody else's score was typed, and a warning
     * that cries wolf is a warning nobody reads.
     */
    public function fingerprintOf(Examination $examination, Student $student): string
    {
        return $this->fingerprintsFor($examination, [$student->id])[$student->id]
            ?? hash('sha256', '');
    }

    /**
     * The same, for a whole class in two queries.
     *
     * @param  list<int>  $studentIds
     * @return array<int, string>
     */
    public function fingerprintsFor(Examination $examination, array $studentIds): array
    {
        $scores = DB::table('examination_scores')
            ->join('examination_subjects', 'examination_scores.examination_subject_id', '=', 'examination_subjects.id')
            ->where('examination_subjects.examination_id', $examination->id)
            ->whereIn('examination_scores.student_id', $studentIds)
            ->orderBy('examination_subjects.id')
            ->get([
                'examination_scores.student_id',
                'examination_subjects.id as subject_id',
                'examination_subjects.name as subject_name',
                'examination_subjects.max_score',
                'examination_scores.test_score',
                'examination_scores.exam_score',
                'examination_scores.score',
                'examination_scores.grade_override',
                'examination_scores.remark',
            ])
            ->groupBy('student_id');

        $remarks = DB::table('examination_reports')
            ->where('examination_id', $examination->id)
            ->whereIn('student_id', $studentIds)
            ->get(['student_id', 'teacher_remark', 'principal_remark'])
            ->keyBy('student_id');

        $fingerprints = [];

        foreach ($studentIds as $studentId) {
            $fingerprints[$studentId] = hash('sha256', json_encode([
                'scores' => $scores->get($studentId, collect())->values()->all(),
                'remarks' => $remarks->get($studentId),
            ]));
        }

        return $fingerprints;
    }

    /**
     * Which of a class's pupils already have a published card, keyed by id.
     *
     * @return Collection<int, RepositoryResult>
     */
    public function publishedFor(Examination $examination): Collection
    {
        return RepositoryResult::query()
            ->where('school_id', $examination->school_id)
            ->forTerm($examination->class_name, $examination->session, $examination->term)
            ->get()
            ->keyBy('student_id');
    }

    /**
     * What a results page needs to know about the repository, in two queries.
     *
     * @return array{published: Collection<int, RepositoryResult>, staleStudentIds: list<int>}
     */
    public function stateFor(Examination $examination): array
    {
        $published = $this->publishedFor($examination);

        if ($published->isEmpty()) {
            return ['published' => $published, 'staleStudentIds' => []];
        }

        $current = $this->fingerprintsFor($examination, $published->keys()->all());

        return [
            'published' => $published,
            'staleStudentIds' => $published
                ->filter(fn (RepositoryResult $record) => $record->isStaleAgainst($current[$record->student_id] ?? null))
                ->keys()
                ->all(),
        ];
    }

    /**
     * The sessions and terms a school has actually published something for.
     *
     * Offered as filter options instead of every session the calendar knows
     * about, so the School Admin is not picking from a list of empty terms.
     *
     * @return array{classes: list<string>, sessions: list<string>, terms: list<ExamTerm>}
     */
    public function filterOptions(School $school): array
    {
        $rows = RepositoryResult::query()
            ->forSchool($school)
            ->get(['class_name', 'session', 'term']);

        return [
            'classes' => $rows->pluck('class_name')->unique()->sort()->values()->all(),
            'sessions' => $rows->pluck('session')->unique()->sortDesc()->values()->all(),
            'terms' => $rows->pluck('term')->unique()->sortBy(fn (ExamTerm $term) => $term->value)->values()->all(),
        ];
    }

    /**
     * @throws RuntimeException
     */
    private function assertBelongTogether(Examination $examination, Student $student): void
    {
        if ($student->school_id !== $examination->school_id) {
            throw new RuntimeException('That pupil does not belong to the school this examination was set by.');
        }

        if ($student->class_name !== $examination->class_name) {
            throw new RuntimeException('That pupil is not in the class this examination was set for.');
        }
    }
}
