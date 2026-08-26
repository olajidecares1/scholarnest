<?php

namespace App\Enums;

use App\Models\School;

/**
 * Who a memorandum is addressed to.
 *
 * "All" is a value of its own rather than a shorthand the interface expands
 * before saving, because a school that addressed everybody should still read
 * as having addressed everybody a term later - not as three separate memos
 * that happen to share a timestamp.
 */
enum MemorandumAudience: string
{
    case Staff = 'staff';
    case Students = 'students';
    case Guardians = 'guardians';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Teachers & Staff',
            self::Students => 'Students / Pupils',
            self::Guardians => 'Parents / Guardians',
            self::All => 'Everyone',
        };
    }

    /**
     * The concrete recipient groups this audience resolves to.
     *
     * @return list<self>
     */
    public function groups(): array
    {
        return $this === self::All
            ? [self::Staff, self::Students, self::Guardians]
            : [$this];
    }

    /**
     * Which audiences a school can actually reach on its plan.
     *
     * Teachers sign in on every plan; students and parents have portals only
     * on Standard and Exclusive. Offering a Basic school "Parents/Guardians"
     * would be offering to send a memorandum to accounts that do not exist -
     * so the option is not shown rather than shown and quietly ignored.
     *
     * "Everyone" stays available on every plan and means everyone that school
     * actually has.
     *
     * @return list<self>
     */
    public static function availableTo(School $school): array
    {
        if ($school->hasPortalAccounts()) {
            return [self::All, self::Staff, self::Students, self::Guardians];
        }

        return [self::All, self::Staff];
    }

    /**
     * The groups this audience reaches at this school, once its plan is taken
     * into account.
     *
     * @return list<self>
     */
    public function groupsFor(School $school): array
    {
        $groups = $this->groups();

        if ($school->hasPortalAccounts()) {
            return $groups;
        }

        // A Basic school has no student or parent portal, so a memorandum to
        // "everyone" reaches the people it has: its staff.
        return array_values(array_filter($groups, fn (self $group) => $group === self::Staff));
    }
}
