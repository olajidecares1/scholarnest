<?php

namespace App\Enums;

/**
 * The state of a result token.
 *
 * Only Active can be redeemed. Everything else is a refusal, and the person
 * redeeming is never told which, see App\Enums\ResultTokenAccessOutcome.
 */
enum ResultCheckingPinStatus: string
{
    /** Issued, bound to a student and a result, and usable. */
    case Active = 'active';

    /** Every permitted view has been used. Reached automatically. */
    case Exhausted = 'exhausted';

    /** Cancelled by the school or the Super Admin. Permanent. */
    case Revoked = 'revoked';

    /**
     * Temporarily withdrawn, typically while an access pattern is looked
     * into. Unlike Revoked this can be lifted, which is the point of having
     * both: revoking a token a parent legitimately holds means reissuing it.
     */
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Exhausted => 'Exhausted',
            self::Revoked => 'Revoked',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * A Tailwind colour name for the status badge.
     */
    public function badgeColour(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Exhausted => 'gray',
            self::Suspended => 'amber',
            self::Revoked => 'red',
        };
    }

    /**
     * May a token in this state still be redeemed?
     *
     * Expiry is deliberately not a status: it is a date, checked separately, so
     * a token does not need a background job to sweep it into another state.
     */
    public function allowsAccess(): bool
    {
        return $this === self::Active;
    }

    /**
     * Can this state be lifted, returning the token to use?
     */
    public function isReversible(): bool
    {
        return $this === self::Suspended;
    }
}
