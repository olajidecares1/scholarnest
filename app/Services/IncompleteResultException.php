<?php

namespace App\Services;

use RuntimeException;

/**
 * A result that is not finished enough to publish.
 *
 * Carries the list of what is missing rather than a single sentence, because
 * the person who sees it is the person who can fix it, and "incomplete" is not
 * an instruction.
 */
class IncompleteResultException extends RuntimeException
{
    /**
     * @param  list<string>  $blockers
     */
    public function __construct(public readonly array $blockers)
    {
        parent::__construct('This result cannot be published yet: '.implode(' ', $blockers));
    }
}
