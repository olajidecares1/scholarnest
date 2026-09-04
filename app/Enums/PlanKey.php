<?php

namespace App\Enums;

enum PlanKey: string
{
    case Basic = 'basic';
    case Standard = 'standard';
    case Exclusive = 'exclusive';

    /**
     * The plan's name as a school sees it, so a message can say "the Standard
     * Plan" without the caller having to know how to spell it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Basic Plan',
            self::Standard => 'Standard Plan',
            self::Exclusive => 'Exclusive Plan',
        };
    }

    /**
     * Is this plan sold by the student rather than as a flat fee?
     *
     * Standard joined Basic here when its ₦200,000-a-term fee was replaced by
     * ₦1,000 a student. That is ALL that changed about Standard - it keeps
     * every feature it had; only the way it is priced moved.
     *
     * One predicate rather than a `=== Basic || === Standard` at each call
     * site, because there are five of them - the wizard, the capacity step,
     * the top-up, the slot limit and the pricing screen - and a plan added to
     * four of the five would be capped everywhere except the one place that
     * lets a school buy more.
     */
    public function isSoldPerStudent(): bool
    {
        return match ($this) {
            self::Basic, self::Standard => true,
            self::Exclusive => false,
        };
    }

    /**
     * Can a school subscribe to this plan today?
     *
     * Exclusive is built and stays built - its features, its columns and its
     * code are all untouched - but it cannot be bought yet. Everything that
     * offers or accepts a plan asks this, so the answer is in one place when
     * it is time to turn it on.
     */
    public function isAvailableToSubscribe(): bool
    {
        return $this !== self::Exclusive;
    }
}
