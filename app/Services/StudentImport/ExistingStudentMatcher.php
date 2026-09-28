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
 * A row is the same student when any of these hold:
 *
 *   1. its admission number is one already assigned at the school (checked
 *      whether the school types admission numbers or has them generated, since
 *      a register exported from this system carries the generated ones);
 *   2. same first and last name, and the same date of birth;
 *   3. same first and last name, and the same guardian phone number;
 *   4. same first and last name, already in the class being imported into.
 *
 * Names compare without case, and on the first given name only, because a
 * register often adds a middle name the original entry did not have.
 *
 * Rows matched against each other within one file (the same child listed
 * twice) are caught too: each row is remembered as it is accepted.
 */
class ExistingStudentMatcher
{
    /** @var array<string, string> key => description of who holds it */
    private array $admissionNumbers = [];

    /** @var array<string, string> */
    private array $identities = [];

    public function __construct(private readonly string $className) {}

    public static function forSchool(School $school, string $className): self
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
                    ], trim($student->first_name.' '.$student->last_name).($student->admission_number ? " ({$student->admission_number})" : ''));
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
        foreach (array_unique(array_filter([$data['admission_number'] ?? null, $fileAdmissionNumber])) as $number) {
            $key = $this->admissionKey($number);

            if ($key !== '' && isset($this->admissionNumbers[$key])) {
                return "Already registered: admission number {$number} is assigned to {$this->admissionNumbers[$key]}.";
            }
        }

        foreach ($this->identityKeys($data + ['class_name' => $this->className]) as $key => $reason) {
            if (isset($this->identities[$key])) {
                return "Already registered: {$this->identities[$key]} has the same name and {$reason}.";
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
    public function remember(array $data, ?string $label = null): void
    {
        $data += ['class_name' => $this->className];
        $label ??= trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')).' (earlier in this file)';

        $number = $this->admissionKey((string) ($data['admission_number'] ?? ''));

        if ($number !== '') {
            $this->admissionNumbers[$number] ??= $label;
        }

        foreach (array_keys($this->identityKeys($data)) as $key) {
            $this->identities[$key] ??= $label;
        }
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string> key => what matched, in words
     */
    private function identityKeys(array $data): array
    {
        $first = $this->firstName((string) ($data['first_name'] ?? ''));
        $last = $this->normalise((string) ($data['last_name'] ?? ''));

        if ($first === '' || $last === '') {
            return [];
        }

        $name = $first.'|'.$last;
        $keys = [];

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
