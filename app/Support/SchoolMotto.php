<?php

namespace App\Support;

use App\Models\School;

/**
 * The two lines of words a school puts on its own documents.
 *
 * They are NOT the same line, and reading both from one field collapsed them
 * into the same sentence printed twice on every report card:
 *
 *   - the tagline sits under the school's name on the letterhead;
 *   - the values run along the foot of the page.
 *
 * Resolved the same way as the address, see App\Support\SchoolContact. The
 * school's own value wins; its website is consulted only where the school has
 * left the field empty, so a Standard school that filled in its public site
 * and never opened Settings still gets a motto on its cards, and a Basic
 * school, which has no website at all, can now set one for the first time.
 */
final class SchoolMotto
{
    public function __construct(
        public readonly ?string $tagline,
        public readonly ?string $values,
    ) {}

    public static function for(School $school): self
    {
        $site = $school->website;

        return new self(
            tagline: self::firstFilled($school->motto, $site?->slogan_tagline, $site?->slogan),

            // Deliberately no fallback to the slogan: the slogan is already
            // the tagline's last resort, and letting both fall back to it is
            // how the two lines came to print as the same sentence twice. A
            // school that has set no values gets no values line, and the
            // footer falls back to its name.
            values: self::firstFilled($school->core_values, $site?->footer_text),
        );
    }

    private static function firstFilled(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
