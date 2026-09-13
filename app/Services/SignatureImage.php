<?php

namespace App\Services;

use App\Services\Uploads\UploadStorage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns what a signature pad posts into a file we are willing to keep.
 *
 * The canvas sends a base64 data URL, which means this is the one image path
 * in the application that never goes near Laravel's `image` validation rule -
 * there is no UploadedFile to validate. Everything that rule would have done
 * has to be done here instead, and then some:
 *
 *   1. The envelope must be a PNG data URL, and the base64 must decode
 *      strictly - no whitespace-tolerant "close enough".
 *   2. The decoded bytes are size-capped BEFORE anything tries to parse them,
 *      so a malicious payload cannot be large enough to matter.
 *   3. getimagesizefromstring() must agree it is a PNG of sane dimensions.
 *   4. It is re-encoded through GD.
 *
 * Step 4 is the one that matters most and is worth being explicit about. A
 * file can be a valid PNG *and* carry something else - a polyglot that is also
 * a script, or metadata that a later viewer parses. Re-encoding throws away
 * everything that is not pixels: what lands on disk is bytes GD wrote, not
 * bytes a browser sent. It also guarantees the stored file really is a PNG,
 * rather than something that merely claimed to be one in its first few bytes.
 */
class SignatureImage
{
    /**
     * The largest data URL we will even look at.
     *
     * A drawn signature is a few tens of kilobytes. This is generous enough
     * for a large canvas on a high-density screen and small enough that a
     * bad payload is never big.
     */
    private const MAX_DECODED_BYTES = 1_048_576;

    /** Sane bounds for something drawn on a signature pad. */
    private const MAX_DIMENSION = 4000;

    private const MIN_DIMENSION = 8;

    /**
     * Store a signature drawn on a canvas, and return its path on the private
     * disk.
     *
     * @param  string  $directory  Where it belongs, e.g. 'staff-signatures'.
     *
     * @throws RuntimeException when the payload is not a signature we can keep.
     */
    public function store(string $dataUrl, string $directory): string
    {
        $png = $this->decode($dataUrl);

        $path = trim($directory, '/').'/'.Str::uuid().'.png';

        app(UploadStorage::class)->putContents('local', $path, $png);

        return $path;
    }

    /**
     * The pixels, re-encoded, or an exception explaining why not.
     */
    public function decode(string $dataUrl): string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#', trim($dataUrl), $matches)) {
            throw new RuntimeException('That is not a signature image.');
        }

        // Capped on the encoded string first: base64 is 4 bytes per 3, so this
        // refuses an oversized payload without decoding it.
        if (strlen($matches[1]) > (int) ceil(self::MAX_DECODED_BYTES / 3) * 4) {
            throw new RuntimeException('That signature is too large.');
        }

        $binary = base64_decode($matches[1], true);

        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_DECODED_BYTES) {
            throw new RuntimeException('That signature could not be read.');
        }

        $size = @getimagesizefromstring($binary);

        if ($size === false || $size[2] !== IMAGETYPE_PNG) {
            throw new RuntimeException('That signature is not a PNG image.');
        }

        [$width, $height] = $size;

        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION
            || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new RuntimeException('That signature is the wrong size.');
        }

        return $this->reEncode($binary);
    }

    /**
     * Re-draw the image from its pixels, so nothing but pixels survives.
     *
     * Transparency is preserved: a signature is drawn on a transparent canvas
     * so it can sit on a letterhead, a ruled line or a coloured panel without
     * carrying a white box around with it.
     */
    private function reEncode(string $binary): string
    {
        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            throw new RuntimeException('That signature could not be read.');
        }

        try {
            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            $written = imagepng($image, null, 9);
            $png = (string) ob_get_clean();

            if (! $written || $png === '') {
                throw new RuntimeException('That signature could not be saved.');
            }

            return $png;
        } finally {
            imagedestroy($image);
        }
    }
}
