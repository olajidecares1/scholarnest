<?php

namespace App\Services;

use RuntimeException;

/**
 * Lifting a school's stamp off the paper it was photographed on.
 *
 * A school does not have a stamp as a file. It has a rubber stamp, and what it
 * can give us is a photograph or a scan of that stamp pressed onto white
 * paper, complete with the paper, its shadows, and whatever else was on the
 * desk. Dropped straight onto a report card, that arrives as a white rectangle
 * with a stamp somewhere inside it, sitting over the design like a sticker.
 *
 * So the picture is processed rather than stored:
 *
 *   1. THE PAPER IS MADE TRANSPARENT. Every pixel close enough to the page's
 *      own background colour becomes transparent, judged against the corners
 *      rather than against pure white, a scan is never pure white, and a
 *      photograph of one is often grey or faintly blue.
 *
 *   2. THE MARGINS ARE TRIMMED. What is left is cropped to the ink, so the
 *      stamp fills the image it is stored in. Two schools' stamps then print
 *      at the same size on a card whatever proportion of their photograph the
 *      stamp happened to occupy.
 *
 *   3. IT IS SAVED AS PNG. Transparency has to survive, so the format is not
 *      the uploaded one, a stamp uploaded as JPEG comes out PNG.
 *
 * The edge is deliberately soft: pixels near the threshold are made partly
 * transparent rather than fully, so the stamp does not print with a hard
 * jagged outline where the ink fades.
 */
class StampImage
{
    /**
     * How close to the background colour a pixel must be to disappear.
     *
     * Measured as a distance across the three channels. Generous enough to
     * take the grey of a scan and the faint blue of a phone photograph, tight
     * enough to leave a pale blue or red stamp alone, which is why this is
     * judged against the SAMPLED background rather than against white.
     */
    private const TRANSPARENT_WITHIN = 60;

    /**
     * Where the fade to fully transparent begins. Pixels between this and
     * TRANSPARENT_WITHIN come out partly transparent, which is what keeps the
     * edge of the ink smooth instead of stepped.
     */
    private const FADE_FROM = 30;

    /**
     * The longest side of the stored stamp, in pixels.
     *
     * A stamp prints about 20mm across. Storing a 4000px phone photograph of
     * one wastes space in every PDF it is embedded in and looks no better.
     */
    private const MAX_DIMENSION = 600;

    /**
     * Turn an uploaded photograph of a stamp into the stamp on transparency.
     *
     * @param  string  $binary  The uploaded file's bytes.
     * @return string PNG bytes.
     *
     * @throws RuntimeException when the upload is not an image this can read.
     */
    public function extract(string $binary): string
    {
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw new RuntimeException('That file could not be read as an image.');
        }

        try {
            $source = $this->withinMaxDimension($source);

            $width = imagesx($source);
            $height = imagesy($source);

            $background = $this->sampleBackground($source, $width, $height);

            $target = imagecreatetruecolor($width, $height);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));

            $bounds = ['left' => $width, 'top' => $height, 'right' => -1, 'bottom' => -1];

            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $rgb = imagecolorat($source, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    $alpha = $this->alphaFor($r, $g, $b, $background);

                    if ($alpha >= 127) {
                        continue;
                    }

                    imagesetpixel($target, $x, $y, imagecolorallocatealpha($target, $r, $g, $b, $alpha));

                    $bounds['left'] = min($bounds['left'], $x);
                    $bounds['top'] = min($bounds['top'], $y);
                    $bounds['right'] = max($bounds['right'], $x);
                    $bounds['bottom'] = max($bounds['bottom'], $y);
                }
            }

            if ($bounds['right'] < 0) {
                throw new RuntimeException(
                    'No stamp could be found in that image. It looks blank. Try a clearer photograph on plain paper.'
                );
            }

            $cropped = $this->crop($target, $bounds);

            imagedestroy($target);

            ob_start();
            imagepng($cropped, null, 9);
            $png = (string) ob_get_clean();

            imagedestroy($cropped);

            if ($png === '') {
                throw new RuntimeException('The stamp could not be saved.');
            }

            return $png;
        } finally {
            if (is_resource($source) || $source instanceof \GdImage) {
                imagedestroy($source);
            }
        }
    }

    /**
     * How transparent this pixel should be: 0 solid, 127 invisible.
     *
     * @param  array{0: int, 1: int, 2: int}  $background
     */
    private function alphaFor(int $r, int $g, int $b, array $background): int
    {
        $distance = sqrt(
            (($r - $background[0]) ** 2)
            + (($g - $background[1]) ** 2)
            + (($b - $background[2]) ** 2)
        );

        if ($distance >= self::TRANSPARENT_WITHIN) {
            return 0;
        }

        if ($distance <= self::FADE_FROM) {
            return 127;
        }

        // Between the two, fade proportionally. This is the soft edge.
        $ratio = ($distance - self::FADE_FROM) / (self::TRANSPARENT_WITHIN - self::FADE_FROM);

        return (int) round(127 * (1 - $ratio));
    }

    /**
     * The paper's colour, taken from the four corners.
     *
     * The corners are where a stamp is not. Taking the median rather than the
     * mean means one dark corner, a thumb over the lens, the edge of a desk,
     * does not drag the estimate away from the paper.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function sampleBackground(\GdImage $image, int $width, int $height): array
    {
        $corners = [
            [1, 1],
            [$width - 2, 1],
            [1, $height - 2],
            [$width - 2, $height - 2],
        ];

        $channels = [[], [], []];

        foreach ($corners as [$x, $y]) {
            $rgb = imagecolorat($image, max(0, $x), max(0, $y));
            $channels[0][] = ($rgb >> 16) & 0xFF;
            $channels[1][] = ($rgb >> 8) & 0xFF;
            $channels[2][] = $rgb & 0xFF;
        }

        return array_map(function (array $values): int {
            sort($values);

            return (int) round(($values[1] + $values[2]) / 2);
        }, $channels);
    }

    /**
     * @param  array{left: int, top: int, right: int, bottom: int}  $bounds
     */
    private function crop(\GdImage $image, array $bounds): \GdImage
    {
        // A couple of pixels of breathing room, so the soft edge is not itself
        // clipped by the crop.
        $padding = 2;

        $left = max(0, $bounds['left'] - $padding);
        $top = max(0, $bounds['top'] - $padding);
        $right = min(imagesx($image) - 1, $bounds['right'] + $padding);
        $bottom = min(imagesy($image) - 1, $bounds['bottom'] + $padding);

        $cropped = imagecrop($image, [
            'x' => $left,
            'y' => $top,
            'width' => $right - $left + 1,
            'height' => $bottom - $top + 1,
        ]);

        if ($cropped === false) {
            throw new RuntimeException('The stamp could not be trimmed.');
        }

        imagesavealpha($cropped, true);

        return $cropped;
    }

    /**
     * Shrink an oversized photograph before working pixel by pixel over it.
     *
     * Both a size decision and a speed one: this walks every pixel twice, and
     * a 12-megapixel phone photograph is 24 million iterations for an image
     * that prints 20mm wide.
     */
    private function withinMaxDimension(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= self::MAX_DIMENSION) {
            return $image;
        }

        $scale = self::MAX_DIMENSION / $longest;

        $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

        if ($resized === false) {
            return $image;
        }

        imagedestroy($image);

        return $resized;
    }
}
