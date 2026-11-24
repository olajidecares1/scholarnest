<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A safe file name for a download built from a school's own identifier.
 *
 * Admission numbers often carry the session, "TGA-2026/2027-JUR-005", and a
 * "/" or "\" in a download's file name is refused outright (Symfony throws),
 * so the report card PDF failed to download for every school numbering that
 * way. Slashes and anything else a file name cannot hold become "-".
 */
final class DownloadName
{
    public static function pdf(string $prefix, ?string $identifier): string
    {
        $safe = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', Str::ascii((string) $identifier)), '-.');

        return $prefix.($safe !== '' ? '-'.$safe : '').'.pdf';
    }
}
