<?php

namespace App\Support;

/**
 * "YYYY/YYYY" academic-session helpers. Nigerian school years typically
 * start around September, so before that month the "current" session is
 * still last calendar year's.
 */
class AcademicSession
{
    public static function currentStartYear(): int
    {
        return now()->month >= 9 ? now()->year : now()->year - 1;
    }

    public static function current(): string
    {
        return self::label(self::currentStartYear());
    }

    public static function label(int $startYear): string
    {
        return "{$startYear}/".($startYear + 1);
    }

    /**
     * A couple of years either side of today, so schools recording past
     * exams or planning ahead both find their session, not just the
     * current one.
     *
     * @return list<string>
     */
    public static function options(): array
    {
        $currentStartYear = self::currentStartYear();

        return collect(range($currentStartYear - 2, $currentStartYear + 2))
            ->map(fn (int $year) => self::label($year))
            ->all();
    }
}
