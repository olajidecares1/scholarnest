<?php

namespace App\Services;

use RuntimeException;

/**
 * Some pupils' report cards could not be made at all, each named with the
 * reason, so the School Admin can see whose card to look at rather than
 * being told only that "something went wrong".
 */
class ClassReportCardsFailed extends RuntimeException
{
    /**
     * @param  list<array{name: string, reason: string}>  $failures
     */
    public function __construct(public readonly array $failures)
    {
        parent::__construct('Report cards could not be made for: '.collect($failures)->map(fn ($f) => "{$f['name']} ({$f['reason']})")->join('; '));
    }
}
