<?php

namespace App\Enums;

/**
 * Why an attempt to redeem a result token succeeded or failed.
 *
 * These values are recorded, never shown. Whatever the real reason, the person
 * at the keyboard always sees the same generic refusal - telling them that a
 * token exists but is revoked, or that it belongs to a different term, hands
 * an attacker a way to map what is out there.
 */
enum ResultTokenAccessOutcome: string
{
    case Succeeded = 'succeeded';

    /** No token with that value exists at this school. */
    case NotFound = 'not_found';

    /** The school revoked it. */
    case Revoked = 'revoked';

    /** Past its expiry date. */
    case Expired = 'expired';

    /** Temporarily suspended, usually while something is investigated. */
    case Suspended = 'suspended';

    /** Every permitted view has been used. */
    case Exhausted = 'exhausted';

    /** Created but never bound to a student and an examination. */
    case NotIssued = 'not_issued';

    /** The result it points at is not available to view yet. */
    case ResultUnavailable = 'result_unavailable';

    /** Refused before it was even checked, because of rate limiting. */
    case RateLimited = 'rate_limited';

    /**
     * A real, usable token - for somebody else's result.
     *
     * Only reachable from the portals, where the result being opened is already
     * known, so the token can be checked against it. Recorded separately from
     * NotFound because they mean different things to a school: one is somebody
     * guessing, the other is a token that has been passed around.
     */
    case BoundElsewhere = 'bound_elsewhere';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => 'Succeeded',
            self::NotFound => 'Token not found',
            self::Revoked => 'Token revoked',
            self::Expired => 'Token expired',
            self::Suspended => 'Token suspended',
            self::Exhausted => 'Token exhausted',
            self::NotIssued => 'Token not issued',
            self::ResultUnavailable => 'Result unavailable',
            self::RateLimited => 'Rate limited',
            self::BoundElsewhere => 'Token belongs to another result',
        };
    }

    public function succeeded(): bool
    {
        return $this === self::Succeeded;
    }

    /**
     * Should this stand out in the school's and Super Admin's security views?
     *
     * A token that does not exist, or repeated rate limiting, is what guessing
     * looks like. A parent using an expired token is just a parent.
     */
    public function isSuspicious(): bool
    {
        return match ($this) {
            self::NotFound, self::RateLimited, self::Revoked, self::BoundElsewhere => true,
            default => false,
        };
    }
}
