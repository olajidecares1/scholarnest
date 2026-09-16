<?php

namespace App\Support;

use App\Models\School;

/**
 * The school the current request's host resolved to, for controllers, views
 * and services that need it without being handed a route parameter.
 *
 * Registered as a SCOPED binding, so it is emptied between requests even under
 * a long-lived worker (Octane, queue): one school's tenant can never be left
 * behind for the next request to pick up.
 */
final class CurrentTenant
{
    private ?School $school = null;

    public function set(?School $school): void
    {
        $this->school = $school;
    }

    public function get(): ?School
    {
        return $this->school;
    }

    public function has(): bool
    {
        return $this->school !== null;
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }
}
