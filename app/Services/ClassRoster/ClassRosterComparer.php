<?php

namespace App\Services\ClassRoster;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Import\ReadsImportValues;
use App\Services\StudentImport\ExistingStudentMatcher;
use Illuminate\Support\Collection;

/**
 * Works out exactly what a revised class list changes.
 *
 * Given the rows of an uploaded file or of the class editor, it finds the
 * student each row is, compares every column with that student's record and
 * reports, student by student and field by field, what is different. Nothing
 * is saved here: the result is what the review page shows and what
 * ClassRosterWriter applies, and only ever to the students and fields that
 * actually changed. A student whose row matches their record is left alone.
 *
 * WHO A FILE ROW IS. Its admission number, when the number belongs to a
 * student in the class; otherwise its name (the same words in any order, so
 * "OKAFOR Chinedu" is Chinedu Okafor), when exactly one student in the class
 * has it. A row that is neither is a new student/pupil, offered for adding.
 * Rows of the class editor carry the student's id, so need no matching.
 *
 * EMPTY CELLS in a file either keep the value on record (the default: a list
 * that simply lacks a phone number does not wipe it) or clear it, as the
 * school chooses on the upload form. In the editor an emptied cell is always
 * a cleared value, because the school can see exactly what it emptied.
 */
class ClassRosterComparer
{
    use ReadsImportValues;

    public const BLANKS_KEEP = 'keep';

    public const BLANKS_CLEAR = 'clear';

    /** Details every student must keep. */
    private const REQUIRED = ['first_name', 'last_name', 'gender'];

    private RosterFields $fields;

    /** @var Collection<int, Student> */
    private Collection $students;

    /** @var array<int, array<string, string|null>> student id => canonical values */
    private array $current = [];

    /** @var array<string, array{id: int, name: string, class_name: string|null}> lower-cased admission number => holder, school-wide */
    private array $admissionNumbers = [];

    /** @var list<string> */
    private array $extraHeadings;

    private ?ExistingStudentMatcher $registered = null;

    public function __construct(private readonly School $school, private readonly SchoolClass $class)
    {
        $classNames = SchoolClass::query()->where('school_id', $school->id)->orderBy('sort_order')->orderBy('name')->pluck('name')->unique()->values()->all();
        $this->fields = RosterFields::forClasses($classNames);
        $this->extraHeadings = array_values($class->extra_columns ?? []);

        $this->students = Student::query()
            ->where('school_id', $school->id)
            ->where('class_name', $class->name)
            ->alphabetical()
            ->get();

        foreach ($this->students as $student) {
            $this->current[$student->id] = RosterFields::current($student, $this->extraHeadings);
        }

        Student::query()
            ->where('school_id', $school->id)
            ->whereNotNull('admission_number')
            ->select(['id', 'admission_number', 'first_name', 'last_name', 'class_name'])
            ->orderBy('id')
            ->chunk(1000, function ($students) {
                foreach ($students as $student) {
                    $this->admissionNumbers[mb_strtolower(trim($student->admission_number))] ??= [
                        'id' => $student->id,
                        'name' => $student->fullName(),
                        'class_name' => $student->class_name,
                    ];
                }
            });
    }

    /**
     * @return Collection<int, Student>
     */
    public function students(): Collection
    {
        return $this->students;
    }

    /**
     * @return list<string>
     */
    public function extraHeadings(): array
    {
        return $this->extraHeadings;
    }

    /**
     * @param  list<array{key: string, line: int|null, student_uuid?: string|null, raw: array<string, string|null>, full_name?: string|null, remove?: bool}>  $rows
     * @param  list<string>  $columns  the fields present (standard keys and "x:<heading>")
     * @param  array{source: string, blanks?: string, name_order?: string, extra_columns?: list<string>, file_name?: string|null}  $options
     *                                                                                                                                       extra_columns: the class's added columns once this update is applied
     * @return array<string, mixed>
     */
    public function compare(array $rows, array $columns, array $options): array
    {
        $source = $options['source'];
        $blanks = $source === 'editor' ? self::BLANKS_CLEAR : ($options['blanks'] ?? self::BLANKS_KEEP);
        $nameOrder = $options['name_order'] ?? 'surname_first';
        $autoGenerate = (bool) $this->school->auto_generate_admission_numbers;

        $extraAfter = array_values(array_unique($options['extra_columns'] ?? $this->extraHeadings));
        $newColumns = array_values(array_diff($extraAfter, $this->extraHeadings));
        $droppedColumns = array_values(array_diff($this->extraHeadings, $extraAfter));

        // A column dropped in the editor clears its values for everyone.
        $columns = array_values(array_unique([
            ...array_filter($columns, fn ($c) => ! RosterFields::isExtra($c) || in_array(RosterFields::extraHeading($c), $extraAfter, true)),
        ]));

        $updates = [];
        $additions = [];
        $removals = [];
        $errors = [];
        $warnings = [];
        $matched = [];
        $unchanged = 0;

        $byNumber = [];
        foreach ($this->students as $student) {
            if (filled($student->admission_number)) {
                $byNumber[mb_strtolower(trim($student->admission_number))] = $student;
            }
        }

        foreach ($rows as $row) {
            $raw = $row['raw'];
            $line = $row['line'] ?? null;
            $label = $this->rowLabel($row);

            // Who is this?
            $student = null;

            if ($source === 'editor') {
                if (filled($row['student_uuid'] ?? null)) {
                    $student = $this->students->firstWhere('uuid', $row['student_uuid']);

                    if ($student === null) {
                        $errors[] = $this->error($row, $label, null, "{$label} is no longer in {$this->class->name}. Reload the editor to see the class as it is now.");

                        continue;
                    }
                }
            } else {
                $identified = $this->identify($row, $byNumber, $matched);

                if (isset($identified['error'])) {
                    $errors[] = $this->error($row, $label, null, $identified['error']);

                    continue;
                }

                $student = $identified['student'];
            }

            if ($student !== null && isset($matched[$student->id])) {
                $errors[] = $this->error($row, $label, null, "{$student->fullName()} is listed more than once (also ".$matched[$student->id].'). Only one row per student/pupil can be applied.');

                continue;
            }

            if ($student !== null) {
                $matched[$student->id] = $line !== null ? ($source === 'editor' ? "row {$line}" : "row {$line} of the file") : 'another row';

                if (! empty($row['remove'])) {
                    $removals[$student->uuid] = $this->who($student);

                    continue;
                }

                $result = $this->changesFor($student, $row, $columns, $blanks, $nameOrder, $autoGenerate);

                foreach ($result['warnings'] as $message) {
                    $warnings[] = ['key' => $row['key'], 'line' => $line, 'name' => $student->fullName(), 'message' => $message];
                }

                if ($result['errors'] !== []) {
                    foreach ($result['errors'] as [$field, $message]) {
                        $errors[] = $this->error($row, $student->fullName(), $field, $message);
                    }

                    continue;
                }

                if ($result['changes'] === []) {
                    $unchanged++;

                    continue;
                }

                $updates[$student->uuid] = [
                    ...$this->who($student),
                    'key' => $row['key'],
                    'line' => $line,
                    'changes' => $result['changes'],
                ];

                continue;
            }

            // Not on the class list: a new student/pupil.
            if (! empty($row['remove'])) {
                continue;
            }

            $result = $this->newStudent($row, $columns, $nameOrder, $autoGenerate);

            if ($result === null) {
                continue;
            }

            foreach ($result['warnings'] as $message) {
                $warnings[] = ['key' => $row['key'], 'line' => $line, 'name' => $result['name'], 'message' => $message];
            }

            if ($result['errors'] !== []) {
                foreach ($result['errors'] as [$field, $message]) {
                    $errors[] = $this->error($row, $result['name'], $field, $message);
                }

                continue;
            }

            $additions[$row['key']] = [
                'key' => $row['key'],
                'line' => $line,
                'name' => $result['name'],
                'values' => $result['values'],
            ];
        }

        // Dropped columns: cleared for students no row mentioned too.
        if ($droppedColumns !== []) {
            foreach ($this->students as $student) {
                if (isset($matched[$student->id]) || isset($removals[$student->uuid])) {
                    continue;
                }

                $changes = [];
                foreach ($droppedColumns as $heading) {
                    $old = $this->current[$student->id][RosterFields::extraKey($heading)] ?? null;
                    if ($old !== null) {
                        $changes[RosterFields::extraKey($heading)] = ['old' => $old, 'new' => null, 'kind' => 'cleared'];
                    }
                }

                if ($changes !== []) {
                    $updates[$student->uuid] = [...$this->who($student), 'key' => $student->uuid, 'line' => null, 'changes' => $changes];
                }
            }
        }

        [$updates, $additions, $numberErrors] = $this->checkAdmissionNumbers($updates, $additions);
        $errors = [...$errors, ...$numberErrors];

        $notInFile = [];
        if ($source === 'file') {
            foreach ($this->students as $student) {
                if (! isset($matched[$student->id])) {
                    $notInFile[] = $this->who($student);
                }
            }
        }

        return [
            'source' => $source,
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'class_uuid' => $this->class->uuid,
            'class_name' => $this->class->name,
            'class_size' => $this->students->count(),
            'file_name' => $options['file_name'] ?? null,
            'blanks' => $blanks,
            'columns' => $columns,
            'extra_columns_before' => $this->extraHeadings,
            'extra_columns_after' => $extraAfter,
            'new_columns' => $newColumns,
            'dropped_columns' => $droppedColumns,
            'updates' => $updates,
            'additions' => $additions,
            'removals' => $removals,
            'unchanged' => $unchanged,
            'not_in_file' => $notInFile,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * How much of the class an update reaches: 'none', 'one', 'some' or
     * 'all' (every student/pupil in the class has a change).
     *
     * @param  array<string, mixed>  $diff
     */
    public static function scope(array $diff): string
    {
        $affected = count($diff['updates']) + count($diff['removals']);
        $size = (int) $diff['class_size'];

        return match (true) {
            $affected === 0 => count($diff['additions']) > 0 || $diff['new_columns'] !== [] ? 'some' : 'none',
            $size > 0 && $affected >= $size => 'all',
            $affected === 1 => 'one',
            default => 'some',
        };
    }

    /**
     * @param  array<string, Student>  $byNumber
     * @param  array<int, string>  $matched
     * @return array{student: Student|null}|array{error: string}
     */
    private function identify(array $row, array $byNumber, array $matched): array
    {
        $number = trim((string) ($row['raw']['admission_number'] ?? ''));

        if ($number !== '') {
            $key = mb_strtolower($number);

            if (isset($byNumber[$key])) {
                return ['student' => $byNumber[$key]];
            }

            $holder = $this->admissionNumbers[$key] ?? null;

            if ($holder !== null) {
                return ['error' => "Admission number {$number} belongs to {$holder['name']}, who is in ".($holder['class_name'] ?: 'no class').", not {$this->class->name}. Update them from their own class."];
            }
        }

        $first = $row['raw']['first_name'] ?? null;
        $last = $row['raw']['last_name'] ?? null;
        $tokens = filled($row['full_name'] ?? null)
            ? RosterFields::nameTokens($row['full_name'])
            : (filled($first) || filled($last) ? RosterFields::nameTokens($first, $last) : '');

        if ($tokens === '') {
            return ['student' => null];
        }

        $exactKey = filled($first) && filled($last) ? $this->nameKey($first, $last) : null;

        $candidates = $this->students->filter(function (Student $s) use ($tokens, $exactKey, $matched) {
            if (isset($matched[$s->id])) {
                return false;
            }

            return ($exactKey !== null && $this->nameKey($s->first_name, $s->last_name) === $exactKey)
                || RosterFields::nameTokens($s->first_name, $s->last_name) === $tokens;
        });

        if ($candidates->count() > 1) {
            return ['error' => 'More than one student/pupil in '.$this->class->name.' is called '.$candidates->first()->fullName().'. Add their admission numbers to the file so each row can be matched.'];
        }

        return ['student' => $candidates->first()];
    }

    /**
     * @return array{changes: array<string, array{old: string|null, new: string|null, kind: string}>, errors: list<array{0: string|null, 1: string}>, warnings: list<string>}
     */
    private function changesFor(Student $student, array $row, array $columns, string $blanks, string $nameOrder, bool $autoGenerate): array
    {
        $current = $this->current[$student->id];
        $raw = $row['raw'];
        $changes = [];
        $errors = [];
        $warnings = [];
        $new = [];

        foreach ($columns as $field) {
            if (! array_key_exists($field, $raw)) {
                continue;
            }

            // A single "Name" column is compared as a whole below.
            if (in_array($field, RosterFields::NAME_FIELDS, true) && filled($row['full_name'] ?? null) && blank($raw[$field])) {
                continue;
            }

            $read = $this->fields->read($field, $raw[$field]);

            if ($read['error'] !== null) {
                $errors[] = [$field, $read['error']];

                continue;
            }

            $new[$field] = $read['value'];
        }

        if (filled($row['full_name'] ?? null) && ! array_key_exists('first_name', $new) && ! array_key_exists('last_name', $new)
            && RosterFields::nameTokens($row['full_name']) !== RosterFields::nameTokens($student->first_name, $student->last_name)) {
            [$first, $last] = $this->splitFullName($row['full_name'], $nameOrder);

            if ($first !== null && $last !== null) {
                $new['first_name'] = $this->nameCase($first);
                $new['last_name'] = $this->nameCase($last);
            }
        }

        foreach ($new as $field => $value) {
            $old = $current[$field] ?? null;

            if ($value === null) {
                // Blank cell. Kept, unless the school asked for blanks to clear.
                if ($old === null || $blanks === self::BLANKS_KEEP) {
                    continue;
                }

                if (in_array($field, self::REQUIRED, true) || ($field === 'admission_number' && ! $autoGenerate)) {
                    $errors[] = [$field, RosterFields::label($field).' cannot be left empty.'];

                    continue;
                }

                if ($field === 'class_name') {
                    $errors[] = [$field, 'Class cannot be left empty. To take a student/pupil out of the class, remove their row instead.'];

                    continue;
                }

                if ($field === 'is_active' || ($field === 'admission_number' && $autoGenerate)) {
                    continue;
                }
            }

            if ($field === 'admission_number' && $autoGenerate) {
                if ($value !== $old) {
                    $warnings[] = 'Admission numbers are generated automatically at your school and cannot be changed here, so '.($old ?? 'the existing number').' was kept.';
                }

                continue;
            }

            if ($value === $old) {
                continue;
            }

            $changes[$field] = [
                'old' => $old,
                'new' => $value,
                'kind' => $old === null ? 'added' : ($value === null ? 'cleared' : 'changed'),
            ];
        }

        return ['changes' => $changes, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * A row for someone not in the class yet.
     *
     * @return array{name: string, values: array<string, string|null>, errors: list<array{0: string|null, 1: string}>, warnings: list<string>}|null null when the row is empty
     */
    private function newStudent(array $row, array $columns, string $nameOrder, bool $autoGenerate): ?array
    {
        $raw = $row['raw'];

        if (implode('', array_map('strval', $raw)).((string) ($row['full_name'] ?? '')) === '') {
            return null;
        }

        $values = [];
        $errors = [];
        $warnings = [];

        foreach ($columns as $field) {
            if (! array_key_exists($field, $raw)) {
                continue;
            }

            $read = $this->fields->read($field, $raw[$field]);

            if ($read['error'] !== null) {
                $errors[] = [$field, $read['error']];

                continue;
            }

            $values[$field] = $read['value'];
        }

        if (filled($row['full_name'] ?? null) && blank($values['first_name'] ?? null) && blank($values['last_name'] ?? null)) {
            [$first, $last] = $this->splitFullName($row['full_name'], $nameOrder);
            $values['first_name'] = $first !== null ? $this->nameCase($first) : null;
            $values['last_name'] = $last !== null ? $this->nameCase($last) : null;
        }

        $name = trim(($values['first_name'] ?? '').' '.($values['last_name'] ?? '')) ?: (string) ($row['full_name'] ?? 'New student/pupil');

        foreach (self::REQUIRED as $field) {
            if (blank($values[$field] ?? null) && ! collect($errors)->contains(fn ($e) => $e[0] === $field)) {
                $errors[] = [$field, RosterFields::label($field).' is needed to add a new student/pupil.'];
            }
        }

        if ($autoGenerate) {
            if (filled($values['admission_number'] ?? null)) {
                $warnings[] = 'A new admission number will be generated automatically; the one given was not used.';
            }

            unset($values['admission_number']);
        } elseif (blank($values['admission_number'] ?? null)) {
            $errors[] = ['admission_number', 'Admission number is needed to add a new student/pupil.'];
        }

        $values['class_name'] = filled($values['class_name'] ?? null) ? $values['class_name'] : $this->class->name;
        $values['is_active'] ??= '1';

        if ($errors === []) {
            // The school's rule for every way of registering: someone already
            // registered is never registered a second time.
            $this->registered ??= ExistingStudentMatcher::forSchool($this->school, $this->class->name);
            $found = $this->registered->find(array_filter($values, fn ($v, $k) => ! RosterFields::isExtra($k), ARRAY_FILTER_USE_BOTH));

            if ($found !== null) {
                $errors[] = [null, $found['reason'].' They were not added again.'];
            } else {
                $this->registered->remember($values);
            }
        }

        return ['name' => $name, 'values' => $values, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Admission numbers must stay unique across the school once everything
     * is applied. A clash takes the row out, with the reason.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: list<array<string, mixed>>}
     */
    private function checkAdmissionNumbers(array $updates, array $additions): array
    {
        $errors = [];
        $claimed = [];

        // Numbers released by this update (changed away from) may be reused
        // by another row of it.
        $released = [];
        foreach ($updates as $update) {
            if (isset($update['changes']['admission_number']) && $update['changes']['admission_number']['old'] !== null) {
                $released[mb_strtolower($update['changes']['admission_number']['old'])] = true;
            }
        }

        $check = function (string $number, ?int $ownerId, string $who) use (&$claimed, $released): ?string {
            $key = mb_strtolower($number);

            if (isset($claimed[$key])) {
                return "Admission number {$number} is given to both {$claimed[$key]} and {$who}.";
            }

            $holder = $this->admissionNumbers[$key] ?? null;

            if ($holder !== null && $holder['id'] !== $ownerId && ! isset($released[$key])) {
                return "Admission number {$number} is already used by {$holder['name']}".($holder['class_name'] ? " ({$holder['class_name']})" : '').'.';
            }

            $claimed[$key] = $who;

            return null;
        };

        foreach ($updates as $uuid => $update) {
            $number = $update['changes']['admission_number']['new'] ?? null;

            if ($number !== null && ($message = $check($number, $update['student_id'], $update['name'])) !== null) {
                $errors[] = ['key' => $update['key'], 'line' => $update['line'], 'name' => $update['name'], 'field' => 'admission_number', 'message' => $message];
                unset($updates[$uuid]);
            }
        }

        foreach ($additions as $key => $addition) {
            $number = $addition['values']['admission_number'] ?? null;

            if ($number !== null && ($message = $check($number, null, $addition['name'])) !== null) {
                $errors[] = ['key' => $addition['key'], 'line' => $addition['line'], 'name' => $addition['name'], 'field' => 'admission_number', 'message' => $message];
                unset($additions[$key]);
            }
        }

        return [$updates, $additions, $errors];
    }

    private function nameKey(?string $first, ?string $last): string
    {
        return $this->normalise((string) $first).'|'.$this->normalise((string) $last);
    }

    private function normalise(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($value)));
    }

    /**
     * @return array{uuid: string, student_id: int, name: string, admission_number: string|null}
     */
    private function who(Student $student): array
    {
        return [
            'uuid' => $student->uuid,
            'student_id' => $student->id,
            'name' => $student->fullName(),
            'admission_number' => $student->admission_number,
        ];
    }

    private function rowLabel(array $row): string
    {
        $name = trim(((string) ($row['raw']['first_name'] ?? '')).' '.((string) ($row['raw']['last_name'] ?? '')));

        return $name !== '' ? $name : (string) ($row['full_name'] ?? ($row['raw']['admission_number'] ?? 'This row'));
    }

    /**
     * @return array{key: string, line: int|null, name: string, field: string|null, message: string}
     */
    private function error(array $row, string $name, ?string $field, string $message): array
    {
        return ['key' => $row['key'], 'line' => $row['line'] ?? null, 'name' => $name, 'field' => $field, 'message' => $message];
    }
}
