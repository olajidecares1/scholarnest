<?php

namespace App\Services\StudentImport;

use App\Models\School;
use Carbon\CarbonInterface;

/**
 * Finds the students/pupils ALREADY registered at a school who may be the
 * child being registered now, so the School Admin can choose to update that
 * record (moving them to the right class, say) instead of creating a second
 * one.
 *
 * A name alone is never treated as proof. Two different children at one
 * school can have the same name, so every candidate is only offered: the
 * School Admin confirms which record it is, or says it is a different child,
 * before anything is changed. What else matched (date of birth, guardian
 * phone, admission number) and what does not (a different date of birth) is
 * reported with each candidate, so the choice is an informed one.
 *
 * A registered student is a candidate when:
 *
 *   - their name is the same words in any order ("Ayinde Taiwo" and "Taiwo
 *     Ayinde"), ignoring capital letters and spacing; or
 *   - their first given name and surname are the same and so is the date of
 *     birth, the guardian's phone number or the class (a register that adds or
 *     drops a middle name); or
 *   - the admission number given is theirs AND the name agrees as above.
 *
 * An admission number that belongs to a student with a DIFFERENT name is not
 * a candidate: the caller reports it as taken, so a typo in an admission
 * number can never overwrite another child's record.
 */
class StudentMatchCandidates
{
    /** @var array<int, array<string, mixed>> student id => normalised record */
    private array $students = [];

    /** @var array<string, list<int>> */
    private array $byTokens = [];

    /** @var array<string, list<int>> */
    private array $byFirstAndLast = [];

    /** @var array<string, int> */
    private array $byAdmissionNumber = [];

    public static function forSchool(School $school): self
    {
        $finder = new self;

        $school->students()
            ->select(['id', 'admission_number', 'first_name', 'last_name', 'date_of_birth', 'guardian_phone', 'class_name'])
            ->orderBy('id')
            ->chunk(1000, function ($students) use ($finder) {
                foreach ($students as $student) {
                    $finder->add((int) $student->id, [
                        'admission_number' => $student->admission_number,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'date_of_birth' => $student->date_of_birth instanceof CarbonInterface ? $student->date_of_birth->toDateString() : $student->date_of_birth,
                        'guardian_phone' => $student->guardian_phone,
                        'class_name' => $student->class_name,
                    ]);
                }
            });

        return $finder;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function add(int $studentId, array $data): void
    {
        $record = self::normaliseRecord($data);
        $this->students[$studentId] = $record;

        if ($record['tokens'] !== '') {
            $this->byTokens[$record['tokens']][] = $studentId;
        }

        if ($record['first_last'] !== '') {
            $this->byFirstAndLast[$record['first_last']][] = $studentId;
        }

        if ($record['admission'] !== '') {
            $this->byAdmissionNumber[$record['admission']] ??= $studentId;
        }
    }

    /**
     * The registered students who may be this child, strongest first, each
     * with what matched and what did not.
     *
     * @param  array<string, mixed>  $data  first_name, last_name, and optionally date_of_birth, guardian_phone, admission_number, class_name
     * @return list<array{student_id: int, signals: list<string>, warnings: list<string>, strong: bool}>
     */
    public function find(array $data, ?string $fileAdmissionNumber = null): array
    {
        $row = self::normaliseRecord($data);

        if ($row['tokens'] === '') {
            return [];
        }

        $ids = [];

        foreach ($this->byTokens[$row['tokens']] ?? [] as $id) {
            $ids[$id] = true;
        }

        foreach ($this->byFirstAndLast[$row['first_last']] ?? [] as $id) {
            $student = $this->students[$id];

            if (($row['dob'] !== '' && $row['dob'] === $student['dob'])
                || ($row['phone'] !== '' && $row['phone'] === $student['phone'])
                || ($row['class'] !== '' && $row['class'] === $student['class'])) {
                $ids[$id] = true;
            }
        }

        $admissionNumbers = array_unique(array_filter([
            self::admissionKey((string) ($data['admission_number'] ?? '')),
            self::admissionKey((string) $fileAdmissionNumber),
        ]));

        $candidates = [];

        foreach (array_keys($ids) as $id) {
            $student = $this->students[$id];
            $signals = [$row['tokens'] === $student['tokens'] ? 'same name' : 'same first name and surname'];
            $warnings = [];

            if ($row['dob'] !== '' && $student['dob'] !== '') {
                if ($row['dob'] === $student['dob']) {
                    $signals[] = 'same date of birth';
                } else {
                    $warnings[] = 'different date of birth';
                }
            }

            if ($row['phone'] !== '' && $row['phone'] === $student['phone']) {
                $signals[] = 'same guardian phone number';
            }

            if ($row['class'] !== '' && $row['class'] === $student['class']) {
                $signals[] = 'already in this class';
            }

            if ($student['admission'] !== '' && in_array($student['admission'], $admissionNumbers, true)) {
                $signals[] = 'same admission number';
            }

            $candidates[] = [
                'student_id' => $id,
                'signals' => $signals,
                'warnings' => $warnings,
                // More than the name agrees, and nothing contradicts it.
                'strong' => count($signals) > 1 && $warnings === [],
            ];
        }

        usort($candidates, fn ($a, $b) => [$b['strong'], count($b['signals']), -count($b['warnings']), $a['student_id']]
            <=> [$a['strong'], count($a['signals']), -count($a['warnings']), $b['student_id']]);

        return $candidates;
    }

    /**
     * The student holding an admission number, if any.
     */
    public function holderOfAdmissionNumber(?string $number): ?int
    {
        $key = self::admissionKey((string) $number);

        return $key === '' ? null : ($this->byAdmissionNumber[$key] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{tokens: string, first_last: string, dob: string, phone: string, admission: string, class: string}
     */
    private static function normaliseRecord(array $data): array
    {
        $first = self::normalise((string) ($data['first_name'] ?? ''));
        $last = self::normalise((string) ($data['last_name'] ?? ''));

        $words = array_values(array_filter(explode(' ', trim($first.' '.$last))));
        sort($words);

        $phone = preg_replace('/\D+/', '', (string) ($data['guardian_phone'] ?? ''));

        return [
            'tokens' => $first === '' || $last === '' ? '' : implode(' ', $words),
            'first_last' => $first === '' || $last === '' ? '' : explode(' ', $first)[0].'|'.$last,
            'dob' => substr((string) ($data['date_of_birth'] ?? ''), 0, 10),
            // Last ten digits, so 0801... and +234801... are the same number.
            'phone' => strlen($phone) >= 7 ? substr($phone, -10) : '',
            'admission' => self::admissionKey((string) ($data['admission_number'] ?? '')),
            'class' => self::normalise((string) ($data['class_name'] ?? '')),
        ];
    }

    private static function admissionKey(string $number): string
    {
        return mb_strtolower(trim($number));
    }

    private static function normalise(string $value): string
    {
        // Hyphens and dots inside names ("Ade-Ola", "O.") count as spaces.
        $value = str_replace(['-', '.', ','], ' ', mb_strtolower($value));

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
