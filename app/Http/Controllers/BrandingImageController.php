<?php

namespace App\Http\Controllers;

use App\Models\BrandingImage;
use Illuminate\Http\Response;

/**
 * Serves the platform logo and favicon.
 *
 * Public, like /pwa/icon-*.png: a browser loads a favicon before anybody signs
 * in, and the logo is on every sign-in page.
 */
class BrandingImageController extends Controller
{
    /**
     * A year. Every upload is stored under a new random name, so a given
     * address always means the same bytes and can be cached for good.
     */
    private const MAX_AGE = 31536000;

    public function show(string $file): Response
    {
        // The route already restricts the name; this is the part that matters.
        // Only an image type is ever sent, never the stored mime_type, so a row
        // cannot be made to serve HTML from this origin.
        $type = BrandingImage::typeFor($file);
        abort_if($type === 'application/octet-stream', 404);

        $bytes = BrandingImage::contents(BrandingImage::DIRECTORY.'/'.$file);
        abort_if($bytes === null, 404);

        return response($bytes, 200, [
            'Content-Type' => $type,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'public, max-age='.self::MAX_AGE.', immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
