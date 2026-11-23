<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Combined subjects and the separate subjects they are made of, as
 * alternatives on one score sheet (config/subjects.php, `combined`).
 *
 * JSS 2 & 3 have, for example, both "BST 1 (PHE & ICT)" and "PHE" and "ICT"
 * on their sheet: some schools score the combined subject, some the separate
 * ones. For one student's result:
 *
 *  - the combined subject is scored: its separate subjects may stay blank;
 *  - any of its separate subjects is scored: the combined one may stay
 *    blank (the other separate subjects are still expected);
 *  - nothing of either is scored: the combined subject is what is missing,
 *    named with its separate subjects as the other way to complete it, and
 *    the separate subjects are not listed again.
 *
 * Names are matched however they are written (DefaultClassSubjects).
 */
class CombinedSubjects
{
    /**
     * Of a sheet's subjects with no score for this student, the ones that
     * are still expected, each with the name to show for it.
     *
     * @param  iterable<string>  $sheet  every subject name on the score sheet
     * @param  iterable<string>  $scored  the names this student has a score in
     * @return array<string, string> sheet name => how to name it as missing
     */
    public static function missing(iterable $sheet, iterable $scored): array
    {
        $sheet = collect($sheet)->values();
        $scoredKeys = collect($scored)->map(fn ($name) => self::key($name))->flip();
        $isScored = fn (string $name) => $scoredKeys->has(self::key($name));

        $waived = [];
        $labels = [];

        foreach (self::pairs() as $combined => $parts) {
            $combinedOnSheet = $sheet->first(fn ($name) => self::key($name) === self::key($combined));
            // In the order config lists them, as named on the sheet.
            $partsOnSheet = collect($parts)
                ->map(fn ($part) => $sheet->first(fn ($name) => self::key($name) === self::key($part)))
                ->filter()
                ->values();

            // Only both forms on one sheet are alternatives.
            if ($combinedOnSheet === null || $partsOnSheet->isEmpty()) {
                continue;
            }

            if ($isScored($combinedOnSheet)) {
                $partsOnSheet->each(function ($name) use (&$waived) {
                    $waived[self::key($name)] = true;
                });
            } elseif ($partsOnSheet->contains($isScored)) {
                $waived[self::key($combinedOnSheet)] = true;
            } else {
                $partsOnSheet->each(function ($name) use (&$waived) {
                    $waived[self::key($name)] = true;
                });
                $labels[self::key($combinedOnSheet)] = $combinedOnSheet.' (or '.self::listOf($partsOnSheet).')';
            }
        }

        return $sheet
            ->reject(fn ($name) => $isScored($name) || isset($waived[self::key($name)]))
            ->mapWithKeys(fn ($name) => [$name => $labels[self::key($name)] ?? $name])
            ->all();
    }

    /**
     * @return array<string, list<string>>
     */
    public static function pairs(): array
    {
        return (array) config('subjects.combined', []);
    }

    /**
     * One key per subject however it is written: a default subject's own
     * name for any of its spellings, otherwise the name normalised.
     */
    private static function key(?string $name): string
    {
        return DefaultClassSubjects::normalize(DefaultClassSubjects::defaultNameFor($name) ?? $name);
    }

    /**
     * @param  Collection<int, string>  $names
     */
    private static function listOf(Collection $names): string
    {
        $names = $names->values();

        return $names->count() === 1
            ? $names->first()
            : $names->slice(0, -1)->implode(', ').' and '.$names->last();
    }
}
