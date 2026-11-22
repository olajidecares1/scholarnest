<?php

namespace App\Services;

use GdImage;
use RuntimeException;

/**
 * Lifts a handwritten signature off a photograph or scan of paper.
 *
 * What a teacher or School Admin uploads is a picture of a page: off-white
 * paper, a shadow from the phone, maybe the edge of the desk, and somewhere
 * on it the signature. What a report card needs is the ink alone, on a
 * transparent background, cropped close, exactly what the signature pad
 * produces. This turns the first into the second with GD alone, so it runs on
 * any host the application runs on and nothing is sent anywhere else.
 *
 *   1. The photo is drawn onto white (so a transparent area counts as paper)
 *      at a working size, which bounds the cost of everything after it.
 *   2. THE PAPER IS ESTIMATED LOCALLY, not once for the whole photo. A phone
 *      photograph is lit unevenly, bright by the window, grey in the phone's
 *      shadow, and a single cut-off either loses the signature in the bright
 *      half or keeps the shadow in the dark half. The page is divided into
 *      blocks, the brightness of the paper in each block is measured (a high
 *      percentile, which ink cannot reach because it covers little of any
 *      block), and that is smoothed into a background for every pixel.
 *   3. Each pixel's INK STRENGTH is how much darker it is than the paper
 *      behind it. The cut-off between paper and ink is chosen from the photo
 *      itself (Otsu's method), within sensible bounds.
 *   4. The marks are grouped into connected shapes and the ones that are not
 *      handwriting are dropped: specks of noise, and large solid shapes
 *      touching the edge of the photo, which are the desk, a finger or the
 *      edge of the page rather than pen strokes.
 *   5. The ink keeps the pen's own colour, with soft edges taken from its
 *      strength, so it reads as written rather than stencilled, and it is
 *      cropped to the signature with a little room around it.
 *
 * An image that already has a transparent background, a PNG somebody cut out
 * themselves, is respected rather than reprocessed: it is only cropped.
 */
class SignatureExtractor
{
    /** The longest edge the photo is examined at. */
    private const WORK_EDGE = 900;

    /** Transparent space left around the signature, in pixels. */
    private const PADDING = 10;

    /** Bounds on the ink cut-off, as a fraction of the paper's brightness. */
    private const MIN_THRESHOLD = 0.16;

    private const MAX_THRESHOLD = 0.5;

    /** More separate marks than any signature is made of. */
    private const MAX_SHAPES = 150;

    public const NOT_FOUND_MESSAGE = 'We could not find a signature in that photo. Sign in dark ink on plain white paper, '
        .'then take the photo in good light with the signature filling most of the picture.';

    public const TOO_BUSY_MESSAGE = 'That photo has too much in it to pick out a signature. Sign on plain white paper and '
        .'photograph only the signature, close up, without a shadow across it.';

    /**
     * The signature as PNG bytes with a transparent background.
     *
     * @param  string  $bytes  An upright image, as ImageProcessor produces.
     *
     * @throws RuntimeException with a message fit to show the person.
     */
    public function extract(string $bytes): string
    {
        $source = @imagecreatefromstring($bytes);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('That photo could not be read. Please choose another one.');
        }

        try {
            if (! imageistruecolor($source)) {
                imagepalettetotruecolor($source);
            }

            return $this->hasTransparentBackground($source)
                ? $this->cropCutOut($source)
                : $this->cutOut($source);
        } finally {
            imagedestroy($source);
        }
    }

    // -----------------------------------------------------------------------
    // An image that is already cut out
    // -----------------------------------------------------------------------

    private function hasTransparentBackground(GdImage $image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, (int) floor(sqrt(($width * $height) / 40000)));
        $samples = 0;
        $clear = 0;

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $samples++;

                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) >= 100) {
                    $clear++;
                }
            }
        }

        return $samples > 0 && $clear / $samples >= 0.2;
    }

    private function cropCutOut(GdImage $source): string
    {
        $image = $this->scaled($source, self::WORK_EDGE, onWhite: false);

        try {
            $width = imagesx($image);
            $height = imagesy($image);
            [$left, $top, $right, $bottom] = [$width, $height, -1, -1];

            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) < 120) {
                        $left = min($left, $x);
                        $right = max($right, $x);
                        $top = min($top, $y);
                        $bottom = max($bottom, $y);
                    }
                }
            }

            if ($right < 0) {
                throw new RuntimeException(self::NOT_FOUND_MESSAGE);
            }

            [$left, $top, $right, $bottom] = $this->padded($left, $top, $right, $bottom, $width, $height);

            $out = $this->transparentCanvas($right - $left + 1, $bottom - $top + 1);
            imagecopy($out, $image, 0, 0, $left, $top, $right - $left + 1, $bottom - $top + 1);

            return $this->png($out);
        } finally {
            imagedestroy($image);
        }
    }

    // -----------------------------------------------------------------------
    // A photograph or scan of paper
    // -----------------------------------------------------------------------

    private function cutOut(GdImage $source): string
    {
        $image = $this->scaled($source, self::WORK_EDGE, onWhite: true);
        $width = imagesx($image);
        $height = imagesy($image);
        $count = $width * $height;

        // Packed RGB and luminance, row by row.
        $rgb = [];
        $lum = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $c = imagecolorat($image, $x, $y) & 0xFFFFFF;
                $rgb[] = $c;
                $lum[] = (int) ((299 * (($c >> 16) & 0xFF) + 587 * (($c >> 8) & 0xFF) + 114 * ($c & 0xFF)) / 1000);
            }
        }

        imagedestroy($image);

        $background = $this->paperBrightness($lum, $width, $height);

        // Ink strength, 0 (paper) to 255 (as dark as it gets against it).
        $strength = [];
        $histogram = array_fill(0, 256, 0);

        for ($i = 0; $i < $count; $i++) {
            $paper = $background[$i];
            $value = $paper > 40 ? (int) round(max(0, $paper - $lum[$i]) * 255 / $paper) : 0;
            $value = min(255, $value);
            $strength[] = $value;
            $histogram[$value]++;
        }

        unset($lum, $background);

        $threshold = (int) round(255 * min(self::MAX_THRESHOLD, max(self::MIN_THRESHOLD, $this->otsu($histogram, $count) / 255)));

        [$labels, $keep] = $this->signatureShapes($strength, $threshold, $width, $height);

        $inked = 0;
        $coreR = $coreG = $coreB = $coreCount = 0;
        $core = $threshold + (int) ((255 - $threshold) * 0.35);
        [$left, $top, $right, $bottom] = [$width, $height, -1, -1];

        for ($i = 0; $i < $count; $i++) {
            $label = $labels[$i];

            if ($label === 0 || ! isset($keep[$label])) {
                continue;
            }

            $inked++;
            $x = $i % $width;
            $y = intdiv($i, $width);
            $left = min($left, $x);
            $right = max($right, $x);
            $top = min($top, $y);
            $bottom = max($bottom, $y);

            if ($strength[$i] >= $core) {
                $c = $rgb[$i];
                $coreR += ($c >> 16) & 0xFF;
                $coreG += ($c >> 8) & 0xFF;
                $coreB += $c & 0xFF;
                $coreCount++;
            }
        }

        if ($inked < max(40, (int) ($count * 0.0003))) {
            throw new RuntimeException(self::NOT_FOUND_MESSAGE);
        }

        // A signature is a handful of strokes and dots. Hundreds of separate
        // fragments is texture: a patterned surface, a busy photo, grain.
        if ($inked > $count * 0.3 || count($keep) > self::MAX_SHAPES) {
            throw new RuntimeException(self::TOO_BUSY_MESSAGE);
        }

        [$inkR, $inkG, $inkB] = $this->inkColour($coreCount, $coreR, $coreG, $coreB);

        // Soft edges: fully opaque well inside a stroke, fading out through
        // the paler pixels at its edge, which are kept only where they touch
        // a stroke that was kept.
        $low = (int) ($threshold * 0.6);
        $high = $threshold + (int) ((255 - $threshold) * 0.25);

        [$left, $top, $right, $bottom] = $this->padded($left, $top, $right, $bottom, $width, $height);
        $out = $this->transparentCanvas($right - $left + 1, $bottom - $top + 1);

        for ($y = $top; $y <= $bottom; $y++) {
            for ($x = $left; $x <= $right; $x++) {
                $i = $y * $width + $x;
                $value = $strength[$i];

                if ($value < $low) {
                    continue;
                }

                $label = $labels[$i];

                if ($label !== 0) {
                    if (! isset($keep[$label])) {
                        continue;
                    }
                } elseif (! $this->touchesKept($labels, $keep, $x, $y, $width, $height)) {
                    continue;
                }

                $opacity = $value >= $high ? 1.0 : ($value - $low) / max(1, $high - $low);
                $alpha = 127 - (int) round($opacity * 127);

                imagesetpixel($out, $x - $left, $y - $top, ($alpha << 24) | ($inkR << 16) | ($inkG << 8) | $inkB);
            }
        }

        return $this->png($out);
    }

    /**
     * The paper's brightness behind every pixel.
     *
     * @param  list<int>  $lum
     * @return list<int>
     */
    private function paperBrightness(array $lum, int $width, int $height): array
    {
        $block = max(8, intdiv(max($width, $height), 32));
        $cols = (int) ceil($width / $block);
        $rows = (int) ceil($height / $block);
        $grid = [];

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $values = [];
                $yEnd = min($height, ($row + 1) * $block);
                $xEnd = min($width, ($col + 1) * $block);

                for ($y = $row * $block; $y < $yEnd; $y++) {
                    $offset = $y * $width;

                    for ($x = $col * $block; $x < $xEnd; $x++) {
                        $values[] = $lum[$offset + $x];
                    }
                }

                sort($values);
                $grid[$row][$col] = $values[(int) floor((count($values) - 1) * 0.9)];
            }
        }

        // A block that happens to be mostly ink (a heavy loop of the pen)
        // reads darker than its paper; lift it to the median of its
        // neighbours. Then soften the grid so the background has no steps.
        $lifted = [];

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $around = [];

                for ($dy = -1; $dy <= 1; $dy++) {
                    for ($dx = -1; $dx <= 1; $dx++) {
                        if (isset($grid[$row + $dy][$col + $dx])) {
                            $around[] = $grid[$row + $dy][$col + $dx];
                        }
                    }
                }

                sort($around);
                $lifted[$row][$col] = max($grid[$row][$col], $around[intdiv(count($around), 2)]);
            }
        }

        $smooth = [];

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $sum = 0;
                $n = 0;

                for ($dy = -1; $dy <= 1; $dy++) {
                    for ($dx = -1; $dx <= 1; $dx++) {
                        if (isset($lifted[$row + $dy][$col + $dx])) {
                            $sum += $lifted[$row + $dy][$col + $dx];
                            $n++;
                        }
                    }
                }

                $smooth[$row][$col] = $sum / $n;
            }
        }

        // Bilinear between block centres.
        $background = [];

        for ($y = 0; $y < $height; $y++) {
            $gy = max(0.0, min($rows - 1.0, ($y + 0.5) / $block - 0.5));
            $r0 = (int) floor($gy);
            $r1 = min($rows - 1, $r0 + 1);
            $fy = $gy - $r0;

            for ($x = 0; $x < $width; $x++) {
                $gx = max(0.0, min($cols - 1.0, ($x + 0.5) / $block - 0.5));
                $c0 = (int) floor($gx);
                $c1 = min($cols - 1, $c0 + 1);
                $fx = $gx - $c0;

                $top = $smooth[$r0][$c0] * (1 - $fx) + $smooth[$r0][$c1] * $fx;
                $bottom = $smooth[$r1][$c0] * (1 - $fx) + $smooth[$r1][$c1] * $fx;

                $background[] = (int) round($top * (1 - $fy) + $bottom * $fy);
            }
        }

        return $background;
    }

    /**
     * Otsu's threshold over the ink-strength histogram.
     *
     * @param  array<int, int>  $histogram
     */
    private function otsu(array $histogram, int $count): int
    {
        $sum = 0;

        foreach ($histogram as $value => $n) {
            $sum += $value * $n;
        }

        $sumBelow = 0;
        $weightBelow = 0;
        $best = 0;
        $threshold = 0;

        for ($t = 0; $t < 256; $t++) {
            $weightBelow += $histogram[$t];

            if ($weightBelow === 0) {
                continue;
            }

            $weightAbove = $count - $weightBelow;

            if ($weightAbove === 0) {
                break;
            }

            $sumBelow += $t * $histogram[$t];
            $meanBelow = $sumBelow / $weightBelow;
            $meanAbove = ($sum - $sumBelow) / $weightAbove;
            $between = $weightBelow * $weightAbove * ($meanBelow - $meanAbove) ** 2;

            if ($between > $best) {
                $best = $between;
                $threshold = $t + 1;
            }
        }

        return $threshold;
    }

    /**
     * Label the marks at or above the threshold, and decide which are
     * handwriting.
     *
     * @param  list<int>  $strength
     * @return array{0: list<int>, 1: array<int, true>}
     */
    private function signatureShapes(array $strength, int $threshold, int $width, int $height): array
    {
        $count = $width * $height;
        $labels = array_fill(0, $count, 0);
        $keep = [];
        $next = 0;
        $minArea = max(5, (int) ($count * 0.000012));

        for ($start = 0; $start < $count; $start++) {
            if ($labels[$start] !== 0 || $strength[$start] < $threshold) {
                continue;
            }

            $label = ++$next;
            $labels[$start] = $label;
            $stack = [$start];
            $area = 0;
            [$left, $top, $right, $bottom] = [$width, $height, -1, -1];

            while ($stack !== []) {
                $i = array_pop($stack);
                $x = $i % $width;
                $y = intdiv($i, $width);
                $area++;
                $left = min($left, $x);
                $right = max($right, $x);
                $top = min($top, $y);
                $bottom = max($bottom, $y);

                for ($dy = -1; $dy <= 1; $dy++) {
                    $ny = $y + $dy;

                    if ($ny < 0 || $ny >= $height) {
                        continue;
                    }

                    for ($dx = -1; $dx <= 1; $dx++) {
                        $nx = $x + $dx;

                        if ($nx < 0 || $nx >= $width) {
                            continue;
                        }

                        $n = $ny * $width + $nx;

                        if ($labels[$n] === 0 && $strength[$n] >= $threshold) {
                            $labels[$n] = $label;
                            $stack[] = $n;
                        }
                    }
                }
            }

            if ($area < $minArea) {
                continue;
            }

            $boxArea = ($right - $left + 1) * ($bottom - $top + 1);
            $fill = $area / $boxArea;
            $touchesEdge = $left === 0 || $top === 0 || $right === $width - 1 || $bottom === $height - 1;

            // Pen strokes are thin lines: even a dense signature covers a
            // small part of the box around it. A solid shape is something
            // else in the photo.
            if ($touchesEdge && $fill > 0.3 && $area > $count * 0.002) {
                continue;
            }

            if ($fill > 0.5 && $area > $count * 0.01) {
                continue;
            }

            $keep[$label] = true;
        }

        return [$labels, $keep];
    }

    /**
     * @param  list<int>  $labels
     * @param  array<int, true>  $keep
     */
    private function touchesKept(array $labels, array $keep, int $x, int $y, int $width, int $height): bool
    {
        for ($dy = -1; $dy <= 1; $dy++) {
            $ny = $y + $dy;

            if ($ny < 0 || $ny >= $height) {
                continue;
            }

            for ($dx = -1; $dx <= 1; $dx++) {
                $nx = $x + $dx;

                if ($nx < 0 || $nx >= $width) {
                    continue;
                }

                $label = $labels[$ny * $width + $nx];

                if ($label !== 0 && isset($keep[$label])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The pen's colour, deepened a little, since a photo always makes ink look
     * paler than it is. Very pale "ink" (pencil, a faded pen) is darkened
     * enough to print.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function inkColour(int $count, int $r, int $g, int $b): array
    {
        if ($count === 0) {
            return [17, 24, 39];
        }

        [$r, $g, $b] = [$r / $count * 0.8, $g / $count * 0.8, $b / $count * 0.8];
        $lum = (299 * $r + 587 * $g + 114 * $b) / 1000;

        if ($lum > 90) {
            $scale = 90 / $lum;
            [$r, $g, $b] = [$r * $scale, $g * $scale, $b * $scale];
        }

        return [(int) round($r), (int) round($g), (int) round($b)];
    }

    // -----------------------------------------------------------------------
    // GD helpers
    // -----------------------------------------------------------------------

    private function scaled(GdImage $source, int $maxEdge, bool $onWhite): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxEdge / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($newWidth, $newHeight);

        if ($onWhite) {
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
            imagealphablending($target, true);
        } else {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $target;
    }

    private function transparentCanvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function padded(int $left, int $top, int $right, int $bottom, int $width, int $height): array
    {
        return [
            max(0, $left - self::PADDING),
            max(0, $top - self::PADDING),
            min($width - 1, $right + self::PADDING),
            min($height - 1, $bottom + self::PADDING),
        ];
    }

    private function png(GdImage $image): string
    {
        try {
            ob_start();
            $written = imagepng($image, null, 9);
            $png = (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        if (! $written || $png === '') {
            throw new RuntimeException('Your signature could not be prepared. Please try again.');
        }

        return $png;
    }
}
