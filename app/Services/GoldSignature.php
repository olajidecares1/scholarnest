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
 * "Bold" is a dilation of that alpha channel by two pixels before recolouring,
 * which thickens every stroke the way a heavier nib would. Done on the alpha
 * rather than the colour so the stroke grows without developing a halo.
 *
 * The empty margin around the strokes is trimmed off as well, so the ink
 * fills the space a document gives a signature instead of floating small in
 * the middle of an upload's blank border.
 *
 * The same treatment, bold and trimmed but in the signer's OWN ink, is what
 * the Class Teacher's signature gets on a report card: see ownInkPathFor().
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
    private const VERSION = 'v2';

    /**
     * How far each stroke grows, in pixels, on every side.
     */
    private const BOLD_RADIUS = 2;

    private const CACHE_DIRECTORY = 'signatures/gold';

    /**
     * Where the gold version of this signature lives, generating it if needed.
     *
     * Returns null when the source is missing or unreadable rather than
     * throwing: a document with an unsigned line is a document, and a report
     * card that 500s because a file went missing is not.
     */
    public function pathFor(string $sourcePath, bool $ownInk = false): ?string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($sourcePath)) {
            return null;
        }

        $cachePath = self::CACHE_DIRECTORY.'/'.self::VERSION.($ownInk ? '-ink' : '').'-'.hash('sha256', $sourcePath).'.png';

        if ($disk->exists($cachePath)) {
            return $cachePath;
        }

        $png = $this->render($disk->get($sourcePath), $ownInk);

        if ($png === null) {
            return null;
        }

        $disk->put($cachePath, $png);

        return $cachePath;
    }

    /**
     * Bold and trimmed, like the gold one, but drawn in the signer's own ink:
     * the average colour of their strokes. For the Class Teacher's signature,
     * which is theirs, not the Principal's, so it is not turned gold.
     */
    public function ownInkDataUriFor(string $sourcePath): ?string
    {
        $path = $this->pathFor($sourcePath, ownInk: true);

        return $path
            ? 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($path))
            : null;
    }

    public function ownInkAbsolutePathFor(string $sourcePath): ?string
    {
        $path = $this->pathFor($sourcePath, ownInk: true);

        return $path ? app(UploadStorage::class)->localPath('local', $path) : null;
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
     * Recolour every visible pixel, thickening the strokes and trimming the
     * empty margin on the way.
     */
    private function render(string $binary, bool $ownInk = false): ?string
    {
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return null;
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);

            $opacity = $this->opacityMap($source, $width, $height);
            [$red, $green, $blue] = $ownInk ? $this->inkOf($source, $opacity, $width, $height) : $this->gold();

            $bold = $this->dilate($opacity, $width, $height, self::BOLD_RADIUS);

            // The box the ink occupies, with a little air around it.
            [$left, $top, $right, $bottom] = $this->inkBounds($bold, $width, $height);
            $pad = self::BOLD_RADIUS + 2;
            $left = max(0, $left - $pad);
            $top = max(0, $top - $pad);
            $right = min($width - 1, $right + $pad);
            $bottom = min($height - 1, $bottom + $pad);

            $outWidth = $right - $left + 1;
            $outHeight = $bottom - $top + 1;

            $target = imagecreatetruecolor($outWidth, $outHeight);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));

            for ($y = $top; $y <= $bottom; $y++) {
                for ($x = $left; $x <= $right; $x++) {
                    $strongest = $bold[$y][$x];

                    if ($strongest <= 0) {
                        continue;
                    }

                    imagesetpixel(
                        $target,
                        $x - $left,
                        $y - $top,
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
     * GD alpha runs 0 (opaque) to 127 (transparent), the opposite way round
     * to everything else, so opacity is read as 127 minus it.
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
     * Each pixel takes the strongest opacity within $radius of it: a stroke
     * grows outwards on every side, so a thin pen line reads as a confident
     * one at the sizes a signature is printed.
     *
     * Rows then columns, which gives the same square neighbourhood as
     * checking every pixel around each one at a fraction of the work.
     *
     * @param  array<int, array<int, int>>  $opacity
     * @return array<int, array<int, int>>
     */
    private function dilate(array $opacity, int $width, int $height, int $radius): array
    {
        $rows = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $strongest = 0;

                for ($nx = max(0, $x - $radius), $end = min($width - 1, $x + $radius); $nx <= $end; $nx++) {
                    $strongest = max($strongest, $opacity[$y][$nx]);
                }

                $rows[$y][$x] = $strongest;
            }
        }

        $out = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $strongest = 0;

                for ($ny = max(0, $y - $radius), $end = min($height - 1, $y + $radius); $ny <= $end; $ny++) {
                    $strongest = max($strongest, $rows[$ny][$x]);
                }

                $out[$y][$x] = $strongest;
            }
        }

        return $out;
    }

    /**
     * The smallest box holding every visible pixel. The whole image when
     * nothing is visible, so an empty upload still renders as before.
     *
     * @param  array<int, array<int, int>>  $opacity
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function inkBounds(array $opacity, int $width, int $height): array
    {
        $left = $width;
        $top = $height;
        $right = -1;
        $bottom = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($opacity[$y][$x] > 8) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        return $right < 0 ? [0, 0, $width - 1, $height - 1] : [$left, $top, $right, $bottom];
    }

    /**
     * The signer's ink: the average colour of their solid strokes, darkened a
     * little so a pale scan still reads as a signature.
     *
     * @param  array<int, array<int, int>>  $opacity
     * @return array{0: int, 1: int, 2: int}
     */
    private function inkOf(\GdImage $source, array $opacity, int $width, int $height): array
    {
        $sum = [0, 0, 0];
        $count = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($opacity[$y][$x] < 100) {
                    continue;
                }

                $rgb = imagecolorat($source, $x, $y);
                $sum[0] += ($rgb >> 16) & 0xFF;
                $sum[1] += ($rgb >> 8) & 0xFF;
                $sum[2] += $rgb & 0xFF;
                $count++;
            }
        }

        if ($count === 0) {
            return [17, 24, 39];
        }

        return array_map(fn (int $channel) => (int) round(($channel / $count) * 0.8), $sum);
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
