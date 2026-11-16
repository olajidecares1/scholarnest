<?php

namespace App\Services\ClassRoster;

use App\Services\Import\ReadsImportValues;
use App\Services\StudentImport\StudentImportException;
use App\Services\StudentImport\StudentImportParser;

/**
 * Reads an uploaded class list (rows of cells from StudentImportReader) as
 * revised student records.
 *
 * Unlike the bulk student upload, nothing here is required except a way to
 * tell who each row is (an admission number or a name): a file holding only
 * "Admission No." and "House" updates houses and touches nothing else. The
 * columns understood are the bulk upload's, read the same forgiving way
 * ("Surname", "SEX", "D.O.B"), plus Blood Group and Status. Any other heading
 * is a column the school is adding, offered on the review page as a new
 * column for the class.
 */
class ClassRosterFileParser
{
    use ReadsImportValues;

    public const MAX_ROWS = 1000;

    /** Headings this update reads that the bulk student upload does not. */
    private const MORE_ALIASES = [
        'blood_group' => ['bloodgroup', 'bloodtype', 'bloodgrp', 'bg'],
        'is_active' => ['status', 'active', 'studentstatus', 'pupilstatus', 'activestatus', 'accountstatus'],
    ];

    /** Headings that are never a student detail or a new column. */
    private const SKIP = ['serial', 'sn', 'sno', 'serialnumber', 'serialno', 'no', 'number', 's', 'signature', 'sign', 'photo', 'photograph', 'passport', 'action', 'actions', 'age'];

    /**
     * @param  list<list<string>>  $rows
     * @return array{columns: list<string>, has_full_name: bool, rows: list<array{line: int, raw: array<string, string>, full_name: string|null}>}
     *
     * @throws StudentImportException
     */
    public function parse(array $rows): array
    {
        [$headerIndex, $map] = $this->findHeader($rows);

        $fields = array_values(array_unique($map));

        $hasName = array_intersect($fields, ['first_name', 'last_name', 'full_name', 'middle_name']) !== [];

        if (! $hasName && ! in_array('admission_number', $fields, true)) {
            throw new StudentImportException('We could not tell who each row is. Keep the "Admission Number" column, or the student/pupil names ("First Name" and "Last Name", or one "Name" column), in the file.');
        }

        $dataRows = array_slice($rows, $headerIndex + 1, null, true);

        if (count($dataRows) > self::MAX_ROWS) {
            throw new StudentImportException('A single upload can hold up to '.number_format(self::MAX_ROWS).' students/pupils. Please split the list into smaller files.');
        }

        $headerKey = mb_strtolower(implode('|', $rows[$headerIndex]));
        $parsed = [];

        foreach ($dataRows as $index => $cells) {
            if (mb_strtolower(implode('|', $cells)) === $headerKey) {
                continue;
            }

            $raw = [];

            foreach ($map as $column => $field) {
                $value = trim((string) ($cells[$column] ?? ''));

                // The first column of a kind wins; an empty cell is kept, so
                // "this column was in the file but blank here" can be told
                // apart from "this column was not in the file".
                if (! array_key_exists($field, $raw) || ($raw[$field] === '' && $value !== '')) {
                    $raw[$field] = $value;
                }
            }

            $identifying = array_intersect_key($raw, array_flip(['admission_number', 'first_name', 'last_name', 'full_name', 'middle_name']));

            // A sub-heading, a total or a page footer, not a student/pupil.
            if (implode('', $identifying) === '') {
                continue;
            }

            $parsed[] = ['line' => $index + 1, ...$this->names($raw)];
        }

        if ($parsed === []) {
            throw new StudentImportException('The file has column headings but no students/pupils listed under them.');
        }

        $columns = [];

        foreach ($fields as $field) {
            $column = match ($field) {
                'full_name', 'middle_name' => null,
                default => $field,
            };

            if ($column !== null) {
                $columns[] = $column;
            }
        }

        if (in_array('full_name', $fields, true) || in_array('middle_name', $fields, true)) {
            $columns = array_values(array_unique([...$columns, 'first_name', 'last_name']));
        }

        // Standard fields in the editor's order, then the new columns in the
        // order they appear in the file.
        $order = array_flip(array_keys(RosterFields::FIELDS));
        usort($columns, fn ($a, $b) => ($order[$a] ?? 1000) <=> ($order[$b] ?? 1000));

        return [
            'columns' => $columns,
            'has_full_name' => in_array('full_name', $fields, true) && ! (in_array('first_name', $fields, true) && in_array('last_name', $fields, true)),
            'rows' => $parsed,
        ];
    }

    /**
     * Middle names join the first name, as when importing; a single "Name"
     * column is kept whole and worked out against the record it matches.
     *
     * @param  array<string, string>  $raw
     * @return array{raw: array<string, string>, full_name: string|null}
     */
    private function names(array $raw): array
    {
        $full = $raw['full_name'] ?? null;
        $middle = $raw['middle_name'] ?? null;
        unset($raw['full_name'], $raw['middle_name']);

        if ($middle !== null) {
            if (($raw['first_name'] ?? '') === '' && ($raw['last_name'] ?? '') !== '') {
                $raw['first_name'] = $middle;
            } elseif ($middle !== '' && ($raw['first_name'] ?? '') !== '') {
                $raw['first_name'] .= ' '.$middle;
            }

            $raw['first_name'] ??= '';
        }

        return ['raw' => $raw, 'full_name' => $full !== null && $full !== '' ? $full : null];
    }

    /**
     * The row, among the first fifteen, with the most recognised headings.
     *
     * @param  list<list<string>>  $rows
     * @return array{0: int, 1: array<int, string>}
     */
    private function findHeader(array $rows): array
    {
        $best = null;

        foreach (array_slice($rows, 0, 15, true) as $index => $cells) {
            $map = [];
            $known = 0;

            foreach ($cells as $column => $heading) {
                $field = $this->fieldFor((string) $heading);

                if ($field === null || in_array($field, $map, true)) {
                    continue;
                }

                $map[$column] = $field;
                $known += RosterFields::isExtra($field) ? 0 : 1;
            }

            $identifies = array_intersect($map, ['admission_number', 'first_name', 'last_name', 'full_name']) !== [];

            if ($identifies && $known >= 2 && ($best === null || $known > $best[2])) {
                $best = [$index, $map, $known];
            }
        }

        if ($best === null) {
            throw new StudentImportException('We could not find the column headings (for example "Admission Number", "First Name", "Last Name"). Make sure the first row of your list has headings, or download the class list and edit that.');
        }

        return [$best[0], $best[1]];
    }

    /**
     * The field a heading names, "x:<heading>" for a column the school is
     * adding, or null for a heading that is not a student detail.
     */
    public function fieldFor(string $heading): ?string
    {
        $key = $this->headingKey($heading);

        if ($key === '' || in_array($key, self::SKIP, true)) {
            return null;
        }

        foreach ([...StudentImportParser::ALIASES, ...self::MORE_ALIASES] as $field => $aliases) {
            if (in_array($key, $aliases, true)) {
                return match ($field) {
                    'serial' => null,
                    'class' => 'class_name',
                    default => $field,
                };
            }
        }

        $prefixed = match (true) {
            str_starts_with($key, 'dateofbirth') => 'date_of_birth',
            str_starts_with($key, 'admissiondate'), str_starts_with($key, 'dateofadmission') => 'admission_date',
            str_starts_with($key, 'surname') => 'last_name',
            str_starts_with($key, 'firstname') => 'first_name',
            str_starts_with($key, 'admissionn') => 'admission_number',
            default => null,
        };

        if ($prefixed !== null) {
            return $prefixed;
        }

        $clean = RosterFields::cleanHeading($heading);

        return $clean === '' ? null : RosterFields::extraKey($clean);
    }

    /**
     * Whether a heading the school typed for a new column would be read as
     * one of the standard details instead.
     */
    public function isStandardHeading(string $heading): bool
    {
        $field = $this->fieldFor($heading);

        return $field === null || ! RosterFields::isExtra($field);
    }
}
