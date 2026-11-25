<?php

namespace App\Support;

use App\Models\School;
use App\Models\Student;

/**
 * The result link a parent is sent, opening their child's result directly.
 *
 * The school's result page, with the admission number and token after a
 * "#": the browser never sends that part to the server, so neither ever
 * reaches a server log or a referrer header. The page reads it, fills in the
 * admission number and moves on, and the next step fills in the token, so
 * the parent only confirms it is their child and taps Check Result. Typing
 * them still works exactly as before.
 */
final class DirectResultLink
{
    public static function for(School $school, Student $student, string $token): string
    {
        return $school->resultLinkUrl().'#'.http_build_query(
            ['a' => (string) $student->admission_number, 't' => $token],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
    }
}
