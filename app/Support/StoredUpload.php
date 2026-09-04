<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * What an uploaded file is called once it is ours.
 *
 * Every upload site in the application discarded the original filename - which
 * is right - and then pasted the original EXTENSION back on, which is not. The
 * extension is part of the same untrusted string: the person uploading chooses
 * it, and it decides what a web server will later do with the file.
 *
 * That was never exploitable here, and it is worth being precise about why:
 * Laravel's `image` and `mimes` rules block php, phtml and phar by the client
 * extension before any of this code runs. The problem is that the safety lived
 * in a framework blocklist the calling code knew nothing about. An upload site
 * added tomorrow without one of those rules would silently lose it, and the
 * blocklist does not cover every extension some server configurations execute
 * (.pht, .phps, .cgi).
 *
 * So the extension is decided here, twice over:
 *
 *   1. From the file's CONTENT, via finfo, rather than from its name.
 *   2. Against an allowlist. Anything not on it becomes .bin, whichever way it
 *      was derived - so a site that forgets its validation rules is still
 *      incapable of writing an executable name to disk.
 *
 * The client extension is consulted only as a fallback, and only through the
 * same allowlist. finfo cannot always identify a file - an empty one, or a
 * format it has no magic for - and a correctly uploaded .docx should not lose
 * its name because of that. What the fallback cannot do is smuggle anything
 * through: `.php` is not on the list from either direction.
 */
class StoredUpload
{
    /**
     * Extensions this application is willing to write to disk.
     *
     * Everything the upload rules accept, and nothing else. Add a format here
     * when you add it to a validation rule - not the other way round.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        // SVG is deliberately absent. It is the one image format that is also
        // a script host - an <svg> may contain <script>, and these files are
        // written to the public disk and served from the school's own origin,
        // so one uploaded by a School Admin would run as that school.
        //
        // Laravel's `image` rule already refuses SVG (it is admitted only by
        // the explicit `allow_svg` parameter, which nothing here passes), so
        // no upload reaches this list with one today. It was still on the list
        // though, which meant the day somebody validated with `mimes:svg`
        // alone, this would happily write it. An SVG now becomes .bin and is
        // never served as an image.
        'jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp', 'ico',
        'mp4', 'mov', 'webm',
        'pdf', 'docx',
    ];

    /**
     * What a file that cannot be identified is stored as.
     *
     * Chosen because no server configuration does anything with it.
     */
    private const UNKNOWN = 'bin';

    /**
     * A safe filename for a stored upload: a random stem and a safe extension.
     *
     * @param  string|null  $stem  Defaults to a UUID. Pass one only where the
     *                             name itself carries meaning, as branding
     *                             assets do.
     */
    public static function name(UploadedFile $file, ?string $stem = null): string
    {
        return ($stem ?? (string) Str::uuid()).'.'.self::extension($file);
    }

    /**
     * The extension to store this file under.
     */
    public static function extension(UploadedFile $file): string
    {
        $fromContent = self::normalise($file->extension());

        if (self::isAllowed($fromContent)) {
            return $fromContent;
        }

        $fromName = self::normalise($file->getClientOriginalExtension());

        return self::isAllowed($fromName) ? $fromName : self::UNKNOWN;
    }

    private static function normalise(?string $extension): string
    {
        return strtolower(trim((string) $extension));
    }

    private static function isAllowed(string $extension): bool
    {
        return in_array($extension, self::ALLOWED, true);
    }
}
