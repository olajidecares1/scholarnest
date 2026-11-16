<?php

namespace App\Services\ClassRoster;

use App\Enums\Gender;
use App\Enums\RegistrationSource;
use App\Models\AuditLog;
use App\Models\ClassUpdateBatch;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\IdentifierGenerator;
use App\Services\StudentLicenceAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Applies a reviewed class update, and undoes one.
 *
 * APPLY. Only what the school ticked on the review page (or saved in the
 * editor) is written, and only to the students and fields that changed:
 * every other record and every other field is never touched. Each field is
 * re-checked first: if the record no longer holds the value the review showed
 * (someone edited the student meanwhile), that field is skipped and reported
 * rather than overwritten.
 *
 * BACKUP. Before anything is written, the value of every field about to change
 * is recorded with the value it is changing to, along with the students
 * created and taken out of the class, in one ClassUpdateBatch. All of it
 * happens in a single transaction: the update and its backup are saved
 * together or not at all.
 *
 * ROLLBACK puts the "before" values back wherever a record still holds the
 * "after" ones, puts removed students back in the class, and deletes the
 * students the update created. A field changed again since the update is
 * left as it is now and listed, so undoing an old update never wipes out a
 * later correction.
 */
class ClassRosterWriter
{
    public function __construct(
        private readonly IdentifierGenerator $identifiers,
        private readonly StudentLicenceAllocation $licences,
    ) {}

    /**
     * @param  array<string, mixed>  $diff  from ClassRosterComparer::compare()
     * @param  array{updates?: array<string, list<string>>, additions?: list<string>, removals?: list<string>, new_columns?: list<string>}  $selection
     * @return array{batch: ClassUpdateBatch|null, skipped: list<string>, error: string|null}
     */
    public function apply(School $school, SchoolClass $class, array $diff, array $selection, ?User $user): array
    {
        $selectedNewColumns = array_values(array_intersect($diff['new_columns'], $selection['new_columns'] ?? []));
        $unselectedColumns = array_map(fn ($h) => RosterFields::extraKey($h), array_diff($diff['new_columns'], $selectedNewColumns));

        $updates = [];
        foreach ($selection['updates'] ?? [] as $uuid => $fields) {
            if (! isset($diff['updates'][$uuid])) {
                continue;
            }

            $changes = array_intersect_key($diff['updates'][$uuid]['changes'], array_flip((array) $fields));
            $changes = array_diff_key($changes, array_flip($unselectedColumns));

            if ($changes !== []) {
                $updates[$uuid] = [...$diff['updates'][$uuid], 'changes' => $changes];
            }
        }

        $additions = array_values(array_intersect_key($diff['additions'], array_flip($selection['additions'] ?? [])));
        $removals = array_values(array_intersect_key($diff['removals'], array_flip($selection['removals'] ?? [])));

        if ($updates === [] && $additions === [] && $removals === [] && $selectedNewColumns === [] && $diff['dropped_columns'] === []) {
            return ['batch' => null, 'skipped' => [], 'error' => 'Nothing was selected, so nothing was changed.'];
        }

        // Licences: new active students, and students made active again,
        // less any made inactive by the same update.
        $activating = count(array_filter($additions, fn ($a) => ($a['values']['is_active'] ?? '1') === '1'));
        foreach ($updates as $update) {
            $status = $update['changes']['is_active'] ?? null;
            $activating += $status === null ? 0 : ($status['new'] === '1' ? 1 : -1);
        }

        $work = fn () => $this->write($school, $class, $diff, $updates, $additions, $removals, $selectedNewColumns, $unselectedColumns, $user);

        $result = $activating > 0
            ? $this->licences->withCapacityFor($school, $activating, $work)
            : DB::transaction($work);

        if ($result === null) {
            return ['batch' => null, 'skipped' => [], 'error' => $this->licences->limitReachedMessage($school).' Nothing was changed.'];
        }

        return $result;
    }

    /**
     * @return array{batch: ClassUpdateBatch|null, skipped: list<string>, error: string|null}
     */
    private function write(School $school, SchoolClass $class, array $diff, array $updates, array $additions, array $removals, array $newColumns, array $unselectedColumns, ?User $user): array
    {
        // Two admins updating the same class queue here.
        $class = SchoolClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();

        $skipped = [];
        $snapshot = [
            'updated' => [],
            'created' => [],
            'removed' => [],
            'extra_columns_before' => array_values($class->extra_columns ?? []),
            'extra_columns_after' => null,
        ];
        $fieldCount = 0;

        $students = Student::query()
            ->where('school_id', $school->id)
            ->whereIn('uuid', [...array_keys($updates), ...array_column($removals, 'uuid')])
            ->get()
            ->keyBy('uuid');

        foreach ($updates as $uuid => $update) {
            $student = $students->get($uuid);

            if ($student === null || $student->class_name !== $class->name) {
                $skipped[] = "{$update['name']} was not updated: they are no longer in {$class->name}.";

                continue;
            }

            $before = [];
            $after = [];
            $extra = is_array($student->extra_fields) ? $student->extra_fields : [];

            foreach ($update['changes'] as $field => $change) {
                $now = RosterFields::canonicalFromModel($student, $field);

                if ($now !== $change['old']) {
                    $skipped[] = "{$student->fullName()}: ".RosterFields::label($field).' was not changed, because it was edited by someone else after this update was reviewed (it is now "'.RosterFields::display($field, $now).'").';

                    continue;
                }

                if ($field === 'admission_number' && $change['new'] !== null && $this->admissionNumberTaken($school, $change['new'], $student->id)) {
                    $skipped[] = "{$student->fullName()}: admission number {$change['new']} was not given, because another student/pupil now has it.";

                    continue;
                }

                if (RosterFields::isExtra($field)) {
                    $heading = RosterFields::extraHeading($field);

                    if ($change['new'] === null) {
                        unset($extra[$heading]);
                    } else {
                        $extra[$heading] = $change['new'];
                    }
                } else {
                    $student->setAttribute($field, $this->attribute($field, $change['new']));
                }

                $before[$field] = $change['old'];
                $after[$field] = $change['new'];
            }

            if ($after === []) {
                continue;
            }

            $student->extra_fields = $extra === [] ? null : $extra;
            $student->save();

            $snapshot['updated'][$student->id] = ['name' => $student->fullName(), 'before' => $before, 'after' => $after];
            $fieldCount += count($after);
        }

        foreach ($removals as $removal) {
            $student = $students->get($removal['uuid']);

            if ($student === null || $student->class_name !== $class->name) {
                $skipped[] = "{$removal['name']} was already out of {$class->name}.";

                continue;
            }

            $student->update(['class_name' => null]);
            $snapshot['removed'][$student->id] = ['name' => $student->fullName(), 'class_name' => $class->name];
        }

        foreach ($additions as $addition) {
            $values = array_diff_key($addition['values'], array_flip($unselectedColumns));
            $attributes = ['school_id' => $school->id, 'registration_source' => RegistrationSource::Bulk];
            $extra = [];

            foreach ($values as $field => $value) {
                if ($value === null) {
                    continue;
                }

                if (RosterFields::isExtra($field)) {
                    $extra[RosterFields::extraHeading($field)] = $value;
                } else {
                    $attributes[$field] = $this->attribute($field, $value);
                }
            }

            if ($school->auto_generate_admission_numbers) {
                $attempts = 0;

                do {
                    $attributes['admission_number'] = $this->identifiers->nextAdmissionNumber($school, $attributes['class_name'] ?? $class->name);
                } while ($this->admissionNumberTaken($school, $attributes['admission_number']) && ++$attempts < 1000);
            } elseif ($this->admissionNumberTaken($school, (string) ($attributes['admission_number'] ?? ''))) {
                $skipped[] = "{$addition['name']} was not added: admission number {$attributes['admission_number']} is now used by another student/pupil.";

                continue;
            }

            $student = $school->students()->create([...$attributes, 'extra_fields' => $extra === [] ? null : $extra]);
            $snapshot['created'][] = $student->id;
        }

        $columns = array_values(array_diff(
            array_values(array_unique([...$snapshot['extra_columns_before'], ...$newColumns])),
            $diff['dropped_columns'],
        ));

        if ($columns !== $snapshot['extra_columns_before']) {
            $class->update(['extra_columns' => $columns === [] ? null : $columns]);
        }

        $snapshot['extra_columns_after'] = $columns;

        $summary = [
            'updated' => count($snapshot['updated']),
            'fields' => $fieldCount,
            'added' => count($snapshot['created']),
            'removed' => count($snapshot['removed']),
            'columns' => count(array_diff($columns, $snapshot['extra_columns_before'])),
            'columns_removed' => count(array_diff($snapshot['extra_columns_before'], $columns)),
            'skipped' => count($skipped),
            'scope' => ClassRosterComparer::scope($diff),
        ];

        if ($summary['updated'] + $summary['added'] + $summary['removed'] + $summary['columns'] + $summary['columns_removed'] === 0) {
            return ['batch' => null, 'skipped' => $skipped, 'error' => 'Nothing was changed. '.($skipped === [] ? '' : 'See the notes below.')];
        }

        $batch = ClassUpdateBatch::create([
            'school_id' => $school->id,
            'school_class_id' => $class->id,
            'class_name' => $class->name,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'source' => $diff['source'],
            'file_name' => $diff['file_name'] ?? null,
            'summary' => $summary,
            'snapshot' => $snapshot,
        ]);

        AuditLog::record(
            'class.bulk_updated',
            "Updated {$class->name} ".($diff['source'] === ClassUpdateBatch::SOURCE_FILE ? 'from the file '.($diff['file_name'] ?? '') : 'in the class editor').': '.$batch->describe().'. A backup was kept for rollback.',
            $class,
        );

        return ['batch' => $batch, 'skipped' => $skipped, 'error' => null];
    }

    /**
     * Restore a class to how it was before an update.
     *
     * @return array{restored: int, notes: list<string>, error: string|null}
     */
    public function rollback(ClassUpdateBatch $batch, ?User $user): array
    {
        if ($batch->isRolledBack()) {
            return ['restored' => 0, 'notes' => [], 'error' => 'This update has already been rolled back.'];
        }

        $school = $batch->school;
        $snapshot = $batch->snapshot;

        // Students the update made inactive become active again.
        $activating = 0;
        foreach ($snapshot['updated'] ?? [] as $entry) {
            if (($entry['before']['is_active'] ?? null) === '1' && ($entry['after']['is_active'] ?? null) === '0') {
                $activating++;
            } elseif (($entry['before']['is_active'] ?? null) === '0' && ($entry['after']['is_active'] ?? null) === '1') {
                $activating--;
            }
        }
        $activating -= count($snapshot['created'] ?? []);

        $work = fn () => $this->restore($batch, $user);

        $result = $activating > 0 ? $this->licences->withCapacityFor($school, $activating, $work) : DB::transaction($work);

        return $result ?? ['restored' => 0, 'notes' => [], 'error' => $this->licences->limitReachedMessage($school).' The update was not rolled back.'];
    }

    /**
     * @return array{restored: int, notes: list<string>, error: string|null}
     */
    private function restore(ClassUpdateBatch $batch, ?User $user): array
    {
        $batch = ClassUpdateBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

        if ($batch->isRolledBack()) {
            return ['restored' => 0, 'notes' => [], 'error' => 'This update has already been rolled back.'];
        }

        $schoolId = $batch->school_id;
        $snapshot = $batch->snapshot;
        $notes = [];
        $restored = 0;
        $photos = [];

        foreach ($snapshot['updated'] ?? [] as $id => $entry) {
            $student = Student::query()->where('school_id', $schoolId)->find($id);

            if ($student === null) {
                $notes[] = "{$entry['name']} has been deleted since, so there was nothing to restore.";

                continue;
            }

            $extra = is_array($student->extra_fields) ? $student->extra_fields : [];
            $changed = false;

            foreach ($entry['after'] as $field => $after) {
                $now = RosterFields::canonicalFromModel($student, $field);
                $before = $entry['before'][$field] ?? null;

                if ($now !== $after) {
                    $notes[] = "{$entry['name']}: ".RosterFields::label($field).' was changed again after this update, so it was left as it is now ("'.RosterFields::display($field, $now).'").';

                    continue;
                }

                if ($field === 'admission_number' && $before !== null && $this->admissionNumberTaken($batch->school, $before, $student->id)) {
                    $notes[] = "{$entry['name']}: admission number {$before} has been given to someone else since, so it was not restored.";

                    continue;
                }

                if (RosterFields::isExtra($field)) {
                    $heading = RosterFields::extraHeading($field);

                    if ($before === null) {
                        unset($extra[$heading]);
                    } else {
                        $extra[$heading] = $before;
                    }
                } else {
                    $student->setAttribute($field, $this->attribute($field, $before));
                }

                $changed = true;
            }

            if ($changed) {
                $student->extra_fields = $extra === [] ? null : $extra;
                $student->save();
                $restored++;
            }
        }

        foreach ($snapshot['removed'] ?? [] as $id => $entry) {
            $student = Student::query()->where('school_id', $schoolId)->find($id);

            if ($student === null) {
                $notes[] = "{$entry['name']} has been deleted since, so they could not be put back in {$entry['class_name']}.";
            } elseif ($student->class_name !== null) {
                $notes[] = "{$entry['name']} has been put in {$student->class_name} since, so they were left there.";
            } else {
                $student->update(['class_name' => $entry['class_name']]);
                $restored++;
            }
        }

        $deleted = 0;
        foreach ($snapshot['created'] ?? [] as $id) {
            $student = Student::query()->where('school_id', $schoolId)->find($id);

            if ($student === null) {
                continue;
            }

            if ($student->photo_path) {
                $photos[] = $student->photo_path;
            }

            // Deleted as a model, so the admission number is released.
            $student->delete();
            $deleted++;
        }

        if ($deleted > 0) {
            $notes[] = "{$deleted} ".($deleted === 1 ? 'student/pupil' : 'students/pupils').' added by this update '.($deleted === 1 ? 'was' : 'were').' deleted.';
        }

        $class = $batch->school_class_id ? SchoolClass::query()->find($batch->school_class_id) : null;

        if ($class !== null) {
            $before = $snapshot['extra_columns_before'] ?? [];
            $after = $snapshot['extra_columns_after'] ?? [];
            $now = array_values($class->extra_columns ?? []);
            $columns = array_values(array_unique([...array_diff($now, array_diff($after, $before)), ...array_diff($before, $after)]));

            if ($columns !== $now) {
                $class->update(['extra_columns' => $columns === [] ? null : $columns]);
            }
        }

        $batch->update([
            'rolled_back_at' => now(),
            'rolled_back_by' => $user?->id,
            'rollback_notes' => $notes,
        ]);

        AuditLog::record(
            'class.bulk_update_rolled_back',
            "Rolled back the update to {$batch->class_name} made on {$batch->created_at?->format('d/m/Y H:i')} ({$batch->describe()}).",
            $class ?? $batch,
        );

        foreach ($photos as $photo) {
            Storage::disk('local')->delete($photo);
        }

        return ['restored' => $restored + $deleted, 'notes' => $notes, 'error' => null];
    }

    private function attribute(string $field, ?string $value): mixed
    {
        return match (true) {
            $value === null => $field === 'is_active' ? false : null,
            $field === 'gender' => Gender::from($value),
            default => RosterFields::toAttribute($field, $value),
        };
    }

    private function admissionNumberTaken(School $school, string $number, ?int $exceptId = null): bool
    {
        if (trim($number) === '') {
            return false;
        }

        return Student::query()
            ->where('school_id', $school->id)
            ->whereRaw('LOWER(admission_number) = ?', [mb_strtolower(trim($number))])
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }
}
