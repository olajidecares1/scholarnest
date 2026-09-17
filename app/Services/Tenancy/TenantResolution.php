<?php

namespace App\Services\Tenancy;

use App\Models\School;

/**
 * What a request's host turned out to be, as decided by {@see TenantResolver}.
 *
 * A value object rather than a nullable School, because "no school" has four
 * different meanings here and each is answered differently: the platform's own
 * host is served normally, www goes to the platform, an unknown address is a
 * 404, and a real school that may not be served right now gets a status page.
 */
final class TenantResolution
{
    /** The platform itself, akademicanest.com. Not a tenant. */
    public const CENTRAL = 'central';

    /** A platform alias such as www.akademicanest.com, sent to the platform. */
    public const PLATFORM_ALIAS = 'platform-alias';

    /** A school, resolved and allowed to be served. */
    public const RESOLVED = 'resolved';

    /** A real school whose website may not be served: suspended, expired, pending or on Basic. */
    public const UNAVAILABLE = 'unavailable';

    /** Nothing answers to this address. */
    public const NOT_FOUND = 'not-found';

    public function __construct(
        public readonly string $status,
        public readonly ?School $school = null,
        public readonly ?string $via = null,
        public readonly ?string $reason = null,
    ) {}

    public function resolved(): bool
    {
        return $this->status === self::RESOLVED && $this->school !== null;
    }

    /** Is this host a school's address at all, whether or not it may be served? */
    public function isTenantHost(): bool
    {
        return in_array($this->status, [self::RESOLVED, self::UNAVAILABLE], true);
    }
}
