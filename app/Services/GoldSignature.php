<?php

namespace App\Services;

use App\Services\Uploads\UploadStorage;
use Illuminate\Support\Facades\Storage;

/**
 * A signature, re-drawn in gold and thickened.
 *
 * The rule is that a Principal's signature appears in gold, bold, everywhere,
 * report cards, ID cards, certificates, previews, on screen and in print. That
 * cannot be done with CSS. A signature is a PNG of near-black strokes on
 * transparency, and:
 *
 *   - `filter: sepia() hue-rotate()` approximates a colour rather than setting
 *     one, and lands somewhere different for every source image;
 *   - dompdf ignores CSS filters entirely, so the printed card would come out
 *     black while the screen preview showed gold, exactly the preview/output
 *     drift this project keeps eliminating;
 *   - `font-weight` means nothing to an image.
 *
 * So the pixels are rewritten instead. The alpha channel IS the signature,
 * where the pen went, and everything else is thrown away: every visible pixel
 * becomes the same gold, whatever colour it was drawn in. That is also what
 * makes the rule enforceable rather than aspirational: a signature drawn in
 * blue, or scanned from blue ink, comes out gold like every other.
 *
 * "Bold" is a dilation of that alpha channel by one pixel before recolouring,
 * which thickens every stroke the way a heavier nib would. Done on the alpha
 * rather than the colour so the stroke grows without developing a halo.
 *
 * Results are cached on the public disk. The cache key includes the source
 * path, and registering a signature always writes a new random filename, so a
 * re-registered signature can never serve a stale gold copy.
 */
class GoldSignature
{
    /**
     * The one gold. Shared with the report card's accent so a card's
     * signature and its rules are the same colour.
     */
    public const GOLD = '#c8a34a';

    /**
     * Bumped when the rendering changes, so existing cached files are
     * regenerated rather than served forever.
     */
    private const VERSION = 'v1';

    private const CACHE_DIRECTORY = 'signatures/gold';

    /**
     * Where the gold version of this signature lives, generating it if needed.
     *
     * Returns null when the source is missing or unreadable rather than
     * throwing: a document with an unsigned line is a document, and a report
     * card that 500s because a file went missing is not.
     */
    public function pathFor(string $sourcePath): ?string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($sourcePath)) {
            return null;
        }

        $cachePath = self::CACHE_DIRECTORY.'/'.self::VERSION.'-'.hash('sha256', $sourcePath).'.png';

        if ($disk->exists($cachePath)) {
            return $cachePath;
        }

        $png = $this->render($disk->get($sourcePath));

        if ($png === null) {
            return null;
        }

        $disk->put($cachePath, $png);

        return $cachePath;
    }

    /**
     * Inline, not a URL. The gold rendering is a second copy of a signature and
     * was kept on the public disk beside the original, so moving the original
     * to private storage without moving this would have left the exposure
     * exactly where it was, one directory across.
     */
    public function dataUriFor(string $sourcePath): ?string
    {
        $path = $this->pathFor($sourcePath);

        return $path
            ? 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($path))
            : null;
    }

    /**
     * Absolute local filesystem path, for dompdf views only.
     *
     * dompdf's `enable_remote` option is off, so it cannot fetch an address,
     * and a data URI would work but writes a base64 copy of the image into
     * every generated PDF. A local path costs nothing.
     */
    public function absolutePathFor(string $sourcePath): ?string
    {
        $path = $this->pathFor($sourcePath);

        // A real file from whichever disk holds it, see UploadStorage::localPath().
        return $path ? app(UploadStorage::class)->localPath('local', $path) : null;
    }

    /**
     * Recolour every visible pixel gold, thickening the strokes on the way.
     */
    private function render(string $binary): ?string
    {
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return null;
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);

            $target = imagecreatetruecolor($width, $height);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));

            [$red, $green, $blue] = $this->gold();

            // GD alpha runs 0 (opaque) to 127 (transparent), which is the
            // opposite way round to everything else, so opacity is read as
            // 127 minus it and the strongest neighbour wins.
            $opacity = $this->opacityMap($source, $width, $height);

            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $strongest = $this->dilatedOpacityAt($opacity, $x, $y, $width, $height);

                    if ($strongest <= 0) {
                        continue;
                    }

                    imagesetpixel(
                        $target,
                        $x,
                        $y,
                        imagecolorallocatealpha($target, $red, $green, $blue, 127 - $strongest),
                    );
                }
            }

            ob_start();
            imagepng($target, null, 9);
            $png = (string) ob_get_clean();

            imagedestroy($target);

            return $png === '' ? null : $png;
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * How opaque each pixel is, 0 (nothing) to 127 (solid).
     *
     * @return array<int, array<int, int>>
     */
    private function opacityMap(\GdImage $source, int $width, int $height): array
    {
        $map = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $map[$y][$x] = 127 - ((imagecolorat($source, $x, $y) >> 24) & 0x7F);
            }
        }

        return $map;
    }

    /**
     * The strongest opacity in this pixel's immediate neighbourhood.
     *
     * This is the "bold": a stroke grows outwards by a pixel on every side,
     * so a thin pen line reads as a confident one at the sizes a signature is
     * printed, about 28px tall on a report card, 15px on an ID card.
     *
     * @param  array<int, array<int, int>>  $opacity
     */
    private function dilatedOpacityAt(array $opacity, int $x, int $y, int $width, int $height): int
    {
        $strongest = 0;

        for ($dy = -1; $dy <= 1; $dy++) {
            for ($dx = -1; $dx <= 1; $dx++) {
                $nx = $x + $dx;
                $ny = $y + $dy;

                if ($nx < 0 || $ny < 0 || $nx >= $width || $ny >= $height) {
                    continue;
                }

                $strongest = max($strongest, $opacity[$ny][$nx]);
            }
        }

        return $strongest;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function gold(): array
    {
        $hex = ltrim(self::GOLD, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
