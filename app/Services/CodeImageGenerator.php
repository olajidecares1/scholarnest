<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

class CodeImageGenerator
{
    /**
     * A QR code data URI, generated locally (no remote HTTP call) so it
     * renders identically in the browser and in dompdf, which cannot fetch
     * remote images (`enable_remote` is off).
     */
    public function qrCodeDataUri(string $data, int $size = 200, int $margin = 8): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: $data,
            size: $size,
            margin: $margin,
        ))->build();

        return $result->getDataUri();
    }

    /**
     * A Code 128 barcode data URI, generated locally for the same reason as
     * qrCodeDataUri() above.
     */
    public function barcodeDataUri(string $data, float $width = 200, float $height = 40): string
    {
        $barcode = (new TypeCode128)->getBarcode($data);
        $svg = (new SvgRenderer)->render($barcode, $width, $height);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Re-samples a raster photo/logo down to the exact pixel size it will be
     * displayed at (cropping to fill, like CSS `object-fit: cover`) and
     * returns it as a data URI. dompdf does not reliably honor explicit
     * width/height (as HTML attributes or CSS) on an `<img>` referencing an
     * arbitrary uploaded photo, some files render at a much larger,
     * unrelated size regardless of any sizing given, spilling into
     * surrounding content on the tiny ID card canvas. Baking the real pixel
     * dimensions into the image itself sidesteps dompdf's sizing entirely,
     * the same fix already relied on for the barcode/QR code above.
     */
    public function croppedImageDataUri(string $absolutePath, int $width, int $height): ?string
    {
        $info = @getimagesize($absolutePath);

        if (! $info) {
            return null;
        }

        $source = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : null,
            default => null,
        };

        if (! $source) {
            return null;
        }

        [$sourceWidth, $sourceHeight] = $info;
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $width / $height;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagecopyresampled($canvas, $source, 0, 0, $cropX, $cropY, $width, $height, $cropWidth, $cropHeight);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        return 'data:image/jpeg;base64,'.base64_encode($bytes);
    }

    /**
     * Same purpose as croppedImageDataUri(), but scales to fit within the
     * given bounds while preserving aspect ratio (no cropping), used for
     * images like a signature scan where cropping would cut off content.
     * Returns the data URI plus the dimensions it was rendered at, since the
     * caller needs the actual width/height to size the <img> correctly.
     *
     * @return array{uri: string, width: int, height: int}|null
     */
    /**
     * A school's logo, faded, for use as a watermark behind a printed page.
     *
     * The fade is baked into the image rather than applied with CSS, because
     * dompdf does not honour opacity on an <img> and ignores it on a
     * background, a watermark drawn the CSS way looks right on screen and
     * comes out of the printer at full strength, obliterating the marks
     * underneath it.
     *
     * Composited onto white rather than left transparent: a PNG with an alpha
     * channel over a white page is the same thing to the eye, but a printer
     * driver that flattens transparency badly is not, and this is the one
     * image on the page nobody would notice had gone wrong until the cards
     * were already handed out.
     *
     * @param  float  $opacity  0 (invisible) to 1 (untouched).
     * @return array{uri: string, width: int, height: int}|null
     */
    public function watermarkDataUri(string $absolutePath, int $size, float $opacity = 0.06): ?array
    {
        $logo = $this->containedImageDataUri($absolutePath, $size, $size);

        if (! $logo) {
            return null;
        }

        $source = @imagecreatefromstring(base64_decode(substr($logo['uri'], strlen('data:image/png;base64,')), true) ?: '');

        if (! $source) {
            return null;
        }

        $canvas = imagecreatetruecolor($logo['width'], $logo['height']);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

        // imagecopymerge cannot blend a source that carries its own alpha, so
        // the logo is laid on white first and the two are merged after.
        $flattened = imagecreatetruecolor($logo['width'], $logo['height']);
        imagefill($flattened, 0, 0, imagecolorallocate($flattened, 255, 255, 255));
        imagealphablending($flattened, true);
        imagecopy($flattened, $source, 0, 0, 0, 0, $logo['width'], $logo['height']);
        imagedestroy($source);

        imagecopymerge($canvas, $flattened, 0, 0, 0, 0, $logo['width'], $logo['height'], (int) round(max(0, min(1, $opacity)) * 100));
        imagedestroy($flattened);

        ob_start();
        imagepng($canvas);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        return [
            'uri' => 'data:image/png;base64,'.base64_encode($bytes),
            'width' => $logo['width'],
            'height' => $logo['height'],
        ];
    }

    public function containedImageDataUri(string $absolutePath, int $maxWidth, int $maxHeight): ?array
    {
        $info = @getimagesize($absolutePath);

        if (! $info) {
            return null;
        }

        $source = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : null,
            default => null,
        };

        if (! $source) {
            return null;
        }

        [$sourceWidth, $sourceHeight] = $info;
        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        imagedestroy($source);

        ob_start();
        imagepng($canvas);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        return [
            'uri' => 'data:image/png;base64,'.base64_encode($bytes),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @param  array{0: float, 1: float}  $p0
     * @param  array{0: float, 1: float}  $p1
     * @param  array{0: float, 1: float}  $p2
     * @param  array{0: float, 1: float}  $p3
     * @return array<int, array{0: float, 1: float}>
     */
    private function cubicBezierPoints(array $p0, array $p1, array $p2, array $p3, int $steps = 24): array
    {
        $points = [];

        for ($i = 1; $i <= $steps; $i++) {
            $t = $i / $steps;
            $x = (1 - $t) ** 3 * $p0[0] + 3 * (1 - $t) ** 2 * $t * $p1[0] + 3 * (1 - $t) * $t ** 2 * $p2[0] + $t ** 3 * $p3[0];
            $y = (1 - $t) ** 3 * $p0[1] + 3 * (1 - $t) ** 2 * $t * $p1[1] + 3 * (1 - $t) * $t ** 2 * $p2[1] + $t ** 3 * $p3[1];
            $points[] = [$x, $y];
        }

        return $points;
    }

    /**
     * @param  \GdImage  $canvas
     */
    private function drawPill($canvas, int $x, int $y, int $width, int $height, int $color): void
    {
        $radius = (int) round($height / 2);
        imagefilledrectangle($canvas, $x + $radius, $y, $x + $width - $radius, $y + $height, $color);
        imagefilledellipse($canvas, $x + $radius, $y + $radius, $height, $height, $color);
        imagefilledellipse($canvas, $x + $width - $radius, $y + $radius, $height, $height, $color);
    }
}
