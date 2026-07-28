<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::Excused => 'Excused',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Present => 'bg-green-100 text-green-700',
            self::Absent => 'bg-red-100 text-red-700',
            self::Late => 'bg-amber-100 text-amber-700',
            self::Excused => 'bg-blue-100 text-blue-700',
        };
    }

    /**
     * Whether this status counts towards the "attended" tally.
     */
    public function isPresentForStats(): bool
    {
        return $this === self::Present || $this === self::Late;
    }
}
