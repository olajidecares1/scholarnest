<?php

namespace App\Services;

use App\Enums\ExamTerm;
use App\Enums\ResultCheckingPinStatus;
use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\ResultTokenBatch;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Exam tokens for exactly the students a School Admin picked, across as many
 * classes as they like, in one run.
 *
 * The whole-class button issues to everybody in one class. This issues to a
 * hand-picked list: ten pupils in Basic 5, fifteen in Basic 6, twenty in
 * JSS 1, whoever has paid, whoever is sitting. Each student's token is bound
 * to THEIR OWN class's examination for the chosen term, worked out from the
 * student record, never from anything the form says about their class.
 *
 * NOBODY GETS A SECOND VALID TOKEN. A student who already holds a usable
 * token for that examination is skipped, both when the list is shown (they
 * cannot be ticked) and again at the moment of issue, inside a lock on the
 * examination, so two admins confirming at once, or one admin pressing the
 * button twice, still leaves each student with one token.
 */
class BulkResultTokenGenerator
{
    public function __construct(
        private readonly ResultTokenIssuer $issuer,
        private readonly ExaminationResolver $examinations,
    ) {}

    /**
     * Every active student in the given classes, in the school's class order
     * and alphabetically within each class, flagged with whether they already
     * hold a valid token for that term.
     *
     * Read only: no examination is created by looking.
     *
     * @param  list<string>  $classNames
     * @return Collection<string, Collection<int, array{student: Student, has_token: bool}>>
     */
    public function roster(School $school, string $session, ExamTerm $term, array $classNames): Collection
    {
        $order = array_flip($school->configuredClassNames());

        $students = $school->students()
            ->where('is_active', true)
            ->whereIn('class_name', $classNames)
            ->alphabetical()
            ->get();

        $holders = $this->holders($school, $session, $term, $students);

        return $students
            ->groupBy('class_name')
            ->sortBy(fn ($group, $className) => $order[$className] ?? PHP_INT_MAX)
            ->map(fn (Collection $group) => $group->map(fn (Student $student): array => [
                'student' => $student,
                'has_token' => isset($holders[$student->id]),
            ])->values());
    }

    /**
     * Issue one token to each of these students, for their own class's
     * examination in this term, as one batch.
     *
     * The batch is null when nobody needed one (everyone already held a valid
     * token by the time this ran).
     *
     * @param  Collection<int, Student>  $students  Already checked to be this school's active students.
     * @return array{batch: ?ResultTokenBatch, issued: int, skipped: Collection<int, Student>}
     */
    public function generate(School $school, string $session, ExamTerm $term, Collection $students, User $issuedBy): array
    {
        return DB::transaction(function () use ($school, $session, $term, $students, $issuedBy): array {
            $batch = ResultTokenBatch::create([
                'school_id' => $school->id,
                'generated_by' => $issuedBy->id,
                'session' => $session,
                'term' => $term,
            ]);

            $issued = 0;
            $skipped = collect();

            foreach ($students->groupBy('class_name') as $className => $group) {
                $examination = $this->examinations->forTokens($school, $session, $term, (string) $className);

                // Serialises concurrent runs for the same examination, so the
                // check below and the issue after it cannot be interleaved by
                // another request issuing to the same students.
                Examination::query()->whereKey($examination->id)->lockForUpdate()->first();

                $holding = $this->validTokens($school)
                    ->where('examination_id', $examination->id)
                    ->whereIn('bound_student_id', $group->pluck('id'))
                    ->pluck('bound_student_id')
                    ->flip();

                foreach ($group as $student) {
                    if (isset($holding[$student->id])) {
                        $skipped->push($student);

                        continue;
                    }

                    $this->issuer->issue($school, $student, $examination, $issuedBy, batch: $batch);
                    $issued++;
                }
            }

            if ($issued === 0) {
                $batch->delete();

                return ['batch' => null, 'issued' => 0, 'skipped' => $skipped];
            }

            $batch->update(['token_count' => $issued]);

            return ['batch' => $batch, 'issued' => $issued, 'skipped' => $skipped];
        });
    }

    /**
     * Which of these students already hold a valid token for their own
     * class's examination in this term, keyed by student id.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, true>
     */
    public function holders(School $school, string $session, ExamTerm $term, Collection $students): array
    {
        $holders = [];

        foreach ($students->groupBy('class_name') as $className => $group) {
            $examination = $this->examinations->existing($school, $session, $term, (string) $className);

            if ($examination === null) {
                continue;
            }

            $this->validTokens($school)
                ->where('examination_id', $examination->id)
                ->whereIn('bound_student_id', $group->pluck('id'))
                ->pluck('bound_student_id')
                ->each(function ($id) use (&$holders): void {
                    $holders[(int) $id] = true;
                });
        }

        return $holders;
    }

    /**
     * Tokens that would still open a result: active or suspended (a
     * suspension is the school's to lift), and not past their expiry. A
     * revoked or expired token is no reason to refuse a new one.
     *
     * @return Builder<ResultCheckingPin>
     */
    private function validTokens(School $school): Builder
    {
        return ResultCheckingPin::query()
            ->forSchool($school)
            ->whereIn('status', [ResultCheckingPinStatus::Active, ResultCheckingPinStatus::Suspended])
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
