<?php

namespace App\Support;

use App\Models\BrandingImage;
use App\Models\School;
use App\Models\Setting;
use App\Services\Uploads\UploadStorage;

/**
 * Which icon a browser tab should show, and at what address.
 *
 * There was no single answer to that before, which is most of why uploading a
 * new favicon looked like it did nothing. Twelve layouts each wrote their own
 * <link rel="icon"> tags; five of them never consulted the uploaded favicon at
 * all, and the seven that did emitted it AFTER the bundled default:
 *
 *     <link rel="icon" sizes="32x32" href="/favicon-32x32.png">
 *     <link rel="icon" sizes="16x16" href="/favicon-16x16.png">
 *     <link rel="icon" href="/storage/branding/favicon-a1b2c3d4.png">
 *
 * With several candidates a browser picks by its own rules - usually the best
 * size match, not the last tag - so the bundled 32x32 kept winning on exactly
 * the pages that had bothered to offer the uploaded one. The Super Admin
 * uploaded a favicon, the file was stored correctly, the setting was saved
 * correctly, and the tab did not change.
 *
 * So exactly ONE icon is declared, chosen here. Nothing for the browser to
 * arbitrate.
 */
final class Favicon
{
    /**
     * The bundled default, used when nothing has been uploaded.
     */
    private const FALLBACK = 'favicon-32x32.png';

    private function __construct(
        public readonly string $href,
        public readonly string $type,
    ) {}

    /**
     * The icon for a page, most specific source first.
     *
     * A school's own favicon wins on its own portals and website - that is
     * what a school branding its space means - and the platform's stands in
     * everywhere else. `$school` is always a School the caller already
     * resolved, never an id from a request, so one school cannot ask for
     * another's.
     *
     * $platformFallback is false on a school's PUBLIC WEBSITE, and only
     * there. That site is the school's own presence on the internet, so a
     * school that has uploaded no favicon shows none - it must never end up
     * flying AkademicNest's flag on its own front page. Everywhere else the
     * platform's icon is the honest answer, because those pages ARE
     * AkademicNest, wearing the school's colours.
     */
    public static function for(?School $school = null, bool $platformFallback = true): ?self
    {
        $path = $school?->favicon_path;

        // The platform's icon is served by the application, from the database,
        // because the disk it was uploaded to is not reachable in production -
        // see BrandingImage. A school's own icon still comes from the disk.
        $address = $path ? UploadStorage::publicUrl($path) : null;

        if (! $path && $platformFallback) {
            $path = Setting::current()->favicon_path;
            $address = $path ? BrandingImage::url($path) : null;
        }

        if (! $path) {
            return $platformFallback ? new self(asset(self::FALLBACK), 'image/png') : null;
        }

        return new self(
            // A cache-buster on top of the stored name. The name already
            // changes on every upload, which is enough for the platform
            // favicon; a school's does not, and browsers cache favicons hard
            // enough that without this a school re-uploading a corrected icon
            // would keep seeing the old one for days.
            $address.'?v='.substr(hash('sha256', $path), 0, 8),
            self::typeFor($path),
        );
    }

    /**
     * The MIME type to declare, from the stored extension.
     *
     * The extension is safe to read here: App\Support\StoredUpload decided it
     * from the file's content against an allowlist when it was written, so it
     * is the application's own string rather than the uploader's.
     */
    private static function typeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'ico' => 'image/x-icon',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };
    }
}
