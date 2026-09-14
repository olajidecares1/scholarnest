<?php

namespace App\Enums;

/**
 * Where a vacancy is in its life.
 *
 * Whether a published vacancy is still ACCEPTING applications also depends on
 * its deadline, see JobPosting::acceptsApplications(). A deadline passing does
 * not change the status; it is read at the moment an application arrives, so a
 * vacancy can never quietly keep accepting after its closing date.
 */
enum JobPostingStatus: string
{
    /** Being written. Visible only in the dashboard. */
    case Draft = 'draft';

    /** On the school's Job Portal. */
    case Published = 'published';

    /** Still viewable at its link, no longer accepting applications. */
    case Closed = 'closed';

    /** Put away. Gone from the portal and the default dashboard list. */
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Closed => 'Closed',
            self::Archived => 'Archived',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'fa-pen-to-square',
            self::Published => 'fa-circle-check',
            self::Closed => 'fa-lock',
            self::Archived => 'fa-box-archive',
        };
    }

    /** Tailwind classes for the status badge. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            self::Published => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            self::Closed => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::Archived => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
        };
    }

    /** Whether the vacancy's public link shows it at all. */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Published || $this === self::Closed;
    }
}
