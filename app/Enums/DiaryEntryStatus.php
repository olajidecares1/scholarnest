<?php

namespace App\Enums;

/**
 * Whether the school has read a diary entry yet.
 *
 * Two states, because there are only two things a teacher needs to know: it
 * has been sent, and somebody has looked at it. Anything finer would be a
 * status nobody could act on.
 */
enum DiaryEntryStatus: string
{
    case Submitted = 'submitted';
    case Seen = 'seen';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Seen => 'Seen / Approved',
        };
    }

    /**
     * Tailwind classes for the badge, kept beside the label so the two cannot
     * describe different states.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Submitted => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::Seen => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Submitted => 'fa-paper-plane',
            self::Seen => 'fa-circle-check',
        };
    }
}
