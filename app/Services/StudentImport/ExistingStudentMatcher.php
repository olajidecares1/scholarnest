<?php

namespace App\Services\StudentImport;

use App\Models\School;
use Carbon\CarbonInterface;

/**
 * Recognises a row in a bulk upload as a student/pupil the school has
 * ALREADY registered.
 *
 * THE RULE THIS EXISTS FOR: a bulk upload never overrides, replaces or
 * modifies an existing student record or its admission number. A student who
 * is already registered is skipped entirely and the upload carries on with the
 * next row. The importer only ever creates records, so skipping is what keeps
 * a re-uploaded register from producing a second copy of the same child under
 * a new admission number.
 *
 * One matcher for EVERY way a student is registered: the bulk upload and the
 * single Add Student form both ask it, before anything is saved, so the two
 * can never disagree about what counts as a duplicate.
 *
 * A row is the same student when any of these hold:
 *
 *   1. its admission number is one already assigned at the school (checked
 *      whether the school types admission numbers or has them generated, since
 *      a register exported from this system carries the generated ones);
 *   2. the same name: first name and surname, ignoring case and spacing.
 *      The school's rule is that a name already registered is refused;
 *   3. same first given name and surname, and the same date of birth;
 *   4. same first given name and surname, and the same guardian phone number;
 *   5. same first given name and surname, already in the class.
 *
 * Rules 3 to 5 compare the first given name only, because a register often
 * adds a middle name the original entry did not have ("Chinedu Emeka" and
 * "Chinedu"), and a second signal makes that safe to treat as the same child.
 *
 * Rows matched against each other within one file (the same child listed
 * twice) are caught too: each row is remembered as it is accepted.
 */
class ExistingStudentMatcher
{
    /** @var array<string, array{label: string, student_id: int|null}> key => who holds it */
    private array $admissionNumbers = [];

    /** @var array<string, array{label: string, student_id: int|null}> */
    private array $identities = [];

    public function __construct(private readonly ?string $className = null) {}

    public static function forSchool(School $school, ?string $className = null): self
    {
        $matcher = new self($className);

        $school->students()
            ->select(['id', 'admission_number', 'first_name', 'last_name', 'date_of_birth', 'guardian_phone', 'class_name'])
            ->orderBy('id')
            ->chunk(1000, function ($students) use ($matcher) {
                foreach ($students as $student) {
                    $matcher->remember([
                        'admission_number' => $student->admission_number,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'date_of_birth' => $student->date_of_birth instanceof CarbonInterface ? $student->date_of_birth->toDateString() : $student->date_of_birth,
                        'guardian_phone' => $student->guardian_phone,
                        'class_name' => $student->class_name,
                    ], trim($student->first_name.' '.$student->last_name).($student->admission_number ? " ({$student->admission_number})" : ''), $student->id);
                }
            });

        return $matcher;
    }

    /**
     * Why this row is an existing student, or null when it is new.
     *
     * @param  array<string, string|null>  $data  a parsed row
     * @param  string|null  $fileAdmissionNumber  the number written in the file, even when the school generates its own
     */
    public function match(array $data, ?string $fileAdmissionNumber = null): ?string
    {
        return $this->find($data, $fileAdmissionNumber)['reason'] ?? null;
    }

    /**
     * match(), with the id of the existing student when the match is a record
     * already in the database (null when it is an earlier row of the same
     * file), so the School Admin can be shown that student's details.
     *
     * @param  array<string, string|null>  $data
     * @return array{reason: string, student_id: int|null}|null
     */
    public function find(array $data, ?string $fileAdmissionNumber = null): ?array
    {
        foreach (array_unique(array_filter([$data['admission_number'] ?? null, $fileAdmissionNumber])) as $number) {
            $key = $this->admissionKey($number);

            if ($key !== '' && isset($this->admissionNumbers[$key])) {
                $holder = $this->admissionNumbers[$key];

                return [
                    'reason' => "Already registered: admission number {$number} is assigned to {$holder['label']}.",
                    'student_id' => $holder['student_id'],
                ];
            }
        }

        foreach ($this->identityKeys($data + ['class_name' => $this->className]) as $key => $reason) {
            if (isset($this->identities[$key])) {
                $holder = $this->identities[$key];

                return [
                    'reason' => "Already registered: {$holder['label']} has the same name".($reason === '' ? '.' : " and {$reason}."),
                    'student_id' => $holder['student_id'],
                ];
            }
        }

        return null;
    }

    public function admissionNumberTaken(string $number): bool
    {
        return isset($this->admissionNumbers[$this->admissionKey($number)]);
    }

    /**
     * Treat a row as registered from now on, so a later row for the same
     * child in the same file is skipped as well.
     *
     * @param  array<string, string|null>  $data
     */
    public function remember(array $data, ?string $label = null, ?int $studentId = null): void
    {
        $data += ['class_name' => $this->className];
        $label ??= trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')).' (earlier in this file)';
        $holder = ['label' => $label, 'student_id' => $studentId];

        $number = $this->admissionKey((string) ($data['admission_number'] ?? ''));

        if ($number !== '') {
            $this->admissionNumbers[$number] ??= $holder;
        }

        foreach (array_keys($this->identityKeys($data)) as $key) {
            $this->identities[$key] ??= $holder;
        }
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string> key => what matched, in words
     */
    private function identityKeys(array $data): array
    {
        $fullFirst = $this->normalise((string) ($data['first_name'] ?? ''));
        $first = $this->firstName((string) ($data['first_name'] ?? ''));
        $last = $this->normalise((string) ($data['last_name'] ?? ''));

        if ($first === '' || $last === '') {
            return [];
        }

        // The same name on its own is enough: the school's rule.
        $keys = ['name:'.$fullFirst.'|'.$last => ''];

        $name = $first.'|'.$last;

        if (filled($data['date_of_birth'] ?? null)) {
            $keys['dob:'.$name.'|'.substr((string) $data['date_of_birth'], 0, 10)] = 'date of birth';
        }

        $phone = preg_replace('/\D+/', '', (string) ($data['guardian_phone'] ?? ''));

        if (strlen($phone) >= 7) {
            // Last ten digits, so 0801... and +234801... are the same number.
            $keys['phone:'.$name.'|'.substr($phone, -10)] = 'guardian phone number';
        }

        if (filled($data['class_name'] ?? null)) {
            $keys['class:'.$name.'|'.$this->normalise((string) $data['class_name'])] = 'is already in '.$data['class_name'];
        }

        return $keys;
    }

    private function admissionKey(string $number): string
    {
        return mb_strtolower(trim($number));
    }

    private function firstName(string $value): string
    {
        return explode(' ', $this->normalise($value))[0];
    }

    private function normalise(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($value)));
    }
}
