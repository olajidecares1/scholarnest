<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\IdentifierChange;
use App\Models\ReleasedIdentifier;
use App\Models\RepositoryResult;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Gives a school's existing students new admission numbers (or its staff new
 * Staff IDs) built from its School Code, after the code was set or changed.
 *
 * A school that registered students before choosing its code has numbers
 * built from the random code it was given at sign-up. Regenerating:
 *
 *   1. clears the generated numbers (hand-typed ones are kept unless the
 *      admin asks for those to be replaced too),
 *   2. issues new ones in order of registration, oldest first, in exactly the
 *      format a new registration is given (see IdentifierGenerator),
 *   3. moves the school's counter on to the next number in line, so the next
 *      student registered continues the sequence with no gap and no repeat.
 *
 * Every old number is written to identifier_changes, so old receipts and
 * report cards stay traceable and the old number still signs the person in.
 * Every other record (results, fees, attendance) points at the student or
 * staff member by id, so it shows the new number immediately; the only copies
 * of the number itself, inside published report cards, are rewritten here.
 */
class IdentifierRegenerator
{
    public function __construct(private readonly IdentifierGenerator $generator) {}

    /**
     * How many records a regeneration would touch.
     *
     * generated: numbers this system made, under the current or a previous
     * code. manual: typed by hand. outdated: generated under a code the
     * school no longer uses.
     *
     * @return array{total: int, generated: int, manual: int, outdated: int}
     */
    public function summary(School $school, string $type): array
    {
        $numbers = $this->records($school, $type)->pluck($this->column($type));

        $generated = $numbers->filter(fn ($number) => $this->isGenerated($school, $type, $number));
        $outdated = $generated->reject(fn ($number) => $this->usesCode($type, (string) $school->school_code, (string) $number));

        return [
            'total' => $numbers->count(),
            'generated' => $generated->count(),
            'manual' => $numbers->count() - $generated->count(),
            'outdated' => $outdated->count(),
        ];
    }

    /**
     * Regenerate the numbers. Returns how many changed and the batch id.
     *
     * @return array{changed: int, kept: int, batch: string}
     */
    public function regenerate(School $school, string $type, bool $includeManual = false, ?User $actor = null): array
    {
        if (! in_array($type, [IdentifierGenerator::ADMISSION, IdentifierGenerator::STAFF], true)) {
            throw new RuntimeException("Unknown identifier type [{$type}].");
        }

        if (! $school->school_code) {
            throw new RuntimeException('Set a school code before regenerating numbers.');
        }

        $batch = (string) Str::uuid();

        $result = DB::transaction(function () use ($school, $type, $includeManual, $actor, $batch) {
            // The same lock IdentifierGenerator takes, so no registration can
            // be given a number half-way through a regeneration.
            $locked = School::whereKey($school->id)->lockForUpdate()->firstOrFail();
            $column = $this->column($type);

            [$replace, $keep] = $this->records($locked, $type)
                ->partition(fn ($record) => $includeManual || $this->isGenerated($locked, $type, $record->{$column}));

            $keptNumbers = $keep->mapWithKeys(fn ($record) => [mb_strtolower((string) $record->{$column}) => true])->all();
            $keptSequences = $this->sequencesHeld($locked, $type, array_keys($keptNumbers));

            $assigned = $this->assign($locked, $type, $replace, $keptNumbers, $keptSequences);
            $changes = $assigned->filter(fn (array $row) => $row['old'] !== $row['new']);

            $this->write($locked, $type, $changes);

            $highest = max([0, $assigned->max('sequence') ?? 0, ...array_keys($keptSequences)]);

            $locked->update([
                $type === IdentifierGenerator::ADMISSION ? 'next_admission_sequence' : 'next_staff_sequence' => $highest + 1,
            ]);

            // The numbering now runs 1, 2, 3... with nothing freed in between.
            ReleasedIdentifier::query()->where('school_id', $locked->id)->where('type', $type)->delete();

            $now = now();
            foreach ($changes->chunk(500) as $chunk) {
                IdentifierChange::query()->insert($chunk->map(fn (array $row) => [
                    'school_id' => $locked->id,
                    'type' => $type,
                    'holder_id' => $row['id'],
                    'old_value' => $row['old'],
                    'new_value' => $row['new'],
                    'batch' => $batch,
                    'changed_by' => $actor?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all());
            }

            $this->updatePublishedResults($locked, $type, $changes);

            return ['changed' => $changes->count(), 'kept' => $keep->count()];
        });

        if ($result['changed'] > 0) {
            AuditLog::record(
                $type === IdentifierGenerator::ADMISSION ? 'students.admission_numbers.regenerated' : 'staff.ids.regenerated',
                sprintf(
                    'Regenerated %d %s using school code %s%s.',
                    $result['changed'],
                    $type === IdentifierGenerator::ADMISSION ? 'admission number(s)' : 'Staff ID(s)',
                    $school->school_code,
                    $result['kept'] > 0 ? sprintf(', keeping %d typed by hand', $result['kept']) : '',
                ),
                $school,
            );
        }

        return $result + ['batch' => $batch];
    }

    /**
     * Was this number made by the system, under the school's current code or
     * one it used before, rather than typed in by hand?
     */
    public function isGenerated(School $school, string $type, ?string $number): bool
    {
        if ($number === null || $number === '') {
            return false;
        }

        foreach ($this->codes($school) as $code) {
            if ($this->usesCode($type, $code, $number)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the number have the generated shape under this code?
     * {code}-{session}-{level}-{sequence} or {code}-STAFF-{sequence}.
     */
    private function usesCode(string $type, string $code, string $number): bool
    {
        if ($code === '') {
            return false;
        }

        $quoted = preg_quote(mb_strtolower($code), '/');
        $pattern = $type === IdentifierGenerator::ADMISSION
            ? '/^'.$quoted.'-.+-[^-]+-\d+$/u'
            : '/^'.$quoted.'-staff-\d+$/u';

        return (bool) preg_match($pattern, mb_strtolower($number));
    }

    /**
     * The current code first, then every code the school used before.
     *
     * @return list<string>
     */
    private function codes(School $school): array
    {
        return collect([$school->school_code, ...($school->previous_school_codes ?? [])])
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->unique(fn ($code) => mb_strtolower($code))
            ->values()
            ->all();
    }

    /**
     * Oldest registration first; the id breaks a tie between two records
     * saved in the same second, so the order is always the same.
     *
     * @return Collection<int, Student>|Collection<int, Staff>
     */
    private function records(School $school, string $type): Collection
    {
        $query = $type === IdentifierGenerator::ADMISSION
            ? Student::query()->select(['id', 'school_id', 'admission_number', 'class_name', 'created_at'])
            : Staff::query()->select(['id', 'school_id', 'staff_number', 'created_at']);

        return $query->where('school_id', $school->id)->orderBy('created_at')->orderBy('id')->get();
    }

    private function column(string $type): string
    {
        return $type === IdentifierGenerator::ADMISSION ? 'admission_number' : 'staff_number';
    }

    /**
     * The sequences a kept (hand-typed) number already holds in the current
     * code's format, which must not be issued to anyone else.
     *
     * @param  list<string>  $numbers  lower-cased
     * @return array<int, true>
     */
    private function sequencesHeld(School $school, string $type, array $numbers): array
    {
        $sequences = [];

        foreach ($numbers as $number) {
            if ($this->usesCode($type, (string) $school->school_code, $number) && preg_match('/-(\d+)$/', $number, $m)) {
                $sequences[(int) $m[1]] = true;
            }
        }

        return $sequences;
    }

    /**
     * Work out every new number, in order, without saving anything.
     *
     * @param  array<string, true>  $keptNumbers
     * @param  array<int, true>  $keptSequences
     * @return Collection<int, array{id: int, old: string, new: string, sequence: int}>
     */
    private function assign(School $school, string $type, Collection $records, array $keptNumbers, array $keptSequences): Collection
    {
        $session = $school->current_session ?: 'NOSESSION';
        $levelCodes = [];
        $sequence = 0;

        return $records->map(function ($record) use ($school, $type, $session, &$levelCodes, &$sequence, $keptNumbers, $keptSequences) {
            if ($type === IdentifierGenerator::ADMISSION) {
                $class = (string) $record->class_name;
                $level = $levelCodes[$class] ??= $this->generator->levelCodeFor($school, $record->class_name);
                $format = fn (int $n) => sprintf('%s-%s-%s-%03d', $school->school_code, $session, $level, $n);
            } else {
                $format = fn (int $n) => sprintf('%s-STAFF-%03d', $school->school_code, $n);
            }

            do {
                $sequence++;
            } while (isset($keptSequences[$sequence]) || isset($keptNumbers[mb_strtolower($format($sequence))]));

            return [
                'id' => (int) $record->id,
                'old' => (string) $record->{$this->column($type)},
                'new' => $format($sequence),
                'sequence' => $sequence,
            ];
        })->values();
    }

    /**
     * Save the new numbers. In two passes: a new number can be one another
     * record still holds until its own turn comes, and the (school, number)
     * pair is unique, so everything changing first steps aside to a
     * placeholder no real number can look like.
     *
     * @param  Collection<int, array{id: int, old: string, new: string, sequence: int}>  $changes
     */
    private function write(School $school, string $type, Collection $changes): void
    {
        $table = $type === IdentifierGenerator::ADMISSION ? 'students' : 'staff';
        $column = $this->column($type);
        $now = now();

        foreach ($changes as $row) {
            DB::table($table)->where('id', $row['id'])->where('school_id', $school->id)
                ->update([$column => '~regenerating~'.$row['id']]);
        }

        foreach ($changes as $row) {
            DB::table($table)->where('id', $row['id'])->where('school_id', $school->id)
                ->update([$column => $row['new'], 'updated_at' => $now]);
        }
    }

    /**
     * Published report cards keep a copy of the pupil's admission number, and
     * of the class teacher's Staff ID, so they are updated to match.
     *
     * @param  Collection<int, array{id: int, old: string, new: string, sequence: int}>  $changes
     */
    private function updatePublishedResults(School $school, string $type, Collection $changes): void
    {
        if ($changes->isEmpty()) {
            return;
        }

        if ($type === IdentifierGenerator::ADMISSION) {
            $byStudent = $changes->keyBy('id');

            RepositoryResult::query()
                ->where('school_id', $school->id)
                ->whereIn('student_id', $byStudent->keys())
                ->select(['id', 'student_id', 'payload'])
                ->chunkById(200, function ($results) use ($byStudent) {
                    foreach ($results as $result) {
                        $payload = $result->payload;

                        if (! is_array($payload) || ! isset($payload['student']) || ! is_array($payload['student'])) {
                            continue;
                        }

                        $payload['student']['admission_number'] = $byStudent[$result->student_id]['new'];

                        DB::table('repository_results')->where('id', $result->id)->update(['payload' => json_encode($payload)]);
                    }
                });

            return;
        }

        $byOldNumber = $changes->mapWithKeys(fn (array $row) => [mb_strtolower($row['old']) => $row['new']]);

        RepositoryResult::query()
            ->where('school_id', $school->id)
            ->select(['id', 'payload'])
            ->chunkById(200, function ($results) use ($byOldNumber) {
                foreach ($results as $result) {
                    $payload = $result->payload;
                    $current = $payload['classTeacher']['staff_number'] ?? null;

                    if (! is_string($current) || ! isset($byOldNumber[mb_strtolower($current)])) {
                        continue;
                    }

                    $payload['classTeacher']['staff_number'] = $byOldNumber[mb_strtolower($current)];

                    DB::table('repository_results')->where('id', $result->id)->update(['payload' => json_encode($payload)]);
                }
            });
    }
}
