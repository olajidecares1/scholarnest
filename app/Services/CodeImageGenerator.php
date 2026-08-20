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
     * arbitrary uploaded photo - some files render at a much larger,
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
     * given bounds while preserving aspect ratio (no cropping) - used for
     * images like a signature scan where cropping would cut off content.
     * Returns the data URI plus the dimensions it was rendered at, since the
     * caller needs the actual width/height to size the <img> correctly.
     *
     * @return array{uri: string, width: int, height: int}|null
     */
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
     * The ID card's top header graphic: two hand-drawn navy corner shapes
     * (a smooth bezier swoosh, not a CSS border-radius approximation) with
     * a wide white gap between them, and the pill-shaped card slot drawn
     * directly into that gap so it's guaranteed to sit exactly where the
     * curves meet with no separate layout/positioning needed.
     *
     * Rendered as a raster PNG via GD at the EXACT display pixel size (no
     * supersampling), not left as an SVG - confirmed by actual render that
     * dompdf does not scale a multi-path SVG with a non-square viewBox the
     * same way a browser does, collapsing these two corner shapes into a
     * single distorted blob. Supersampling for crispness then relying on
     * dompdf to downscale had the same problem: extracting the raw bytes
     * dompdf actually received proved the source image was correct
     * (~8% navy coverage) and dompdf itself was distorting it when scaling
     * down to display size - the same "does not reliably honor
     * width/height" behaviour already worked around for every other raster
     * image on this card. Generating at the exact display size removes any
     * scaling step for dompdf to get wrong.
     */
    public function idCardHeaderDataUri(string $color, int $width = 204, int $height = 38): string
    {
        $canvasWidth = $width;
        $canvasHeight = $height;

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagealphablending($canvas, true);

        [$r, $g, $b] = sscanf(ltrim($color, '#'), '%02x%02x%02x');
        $navy = imagecolorallocate($canvas, $r, $g, $b);

        // Shapes are designed against a 200x60 reference box: a wide top
        // edge tapering down a smooth cubic-bezier curve to a point partway
        // down the side edge, leaving most of the top-centre free for the
        // pill slot. Scaled up to the actual canvas size below. Tuned by
        // objectively measuring navy-pixel coverage (not just eyeballing a
        // tiny render, which proved unreliable) until the corners read as a
        // modest accent rather than a dominant band - about 8-9% of the
        // header area.
        $sx = $canvasWidth / 200;
        $sy = $canvasHeight / 60;
        $curve = $this->cubicBezierPoints([65, 0], [36, 2], [10, 11], [0, 25]);

        $leftPolygon = [];
        foreach (array_merge([[0, 0]], $curve) as [$x, $y]) {
            $leftPolygon[] = $x * $sx;
            $leftPolygon[] = $y * $sy;
        }
        imagefilledpolygon($canvas, $leftPolygon, $navy);

        $rightPolygon = [];
        foreach (array_merge([[200, 0]], array_map(fn ($p) => [200 - $p[0], $p[1]], $curve)) as [$x, $y]) {
            $rightPolygon[] = $x * $sx;
            $rightPolygon[] = $y * $sy;
        }
        imagefilledpolygon($canvas, $rightPolygon, $navy);

        $pillDark = imagecolorallocate($canvas, 203, 213, 225);
        $pillLight = imagecolorallocate($canvas, 226, 232, 240);
        $this->drawPill($canvas, (int) (86 * $sx), (int) (4 * $sy), (int) (28 * $sx), (int) (10 * $sy), $pillDark);
        $this->drawPill($canvas, (int) (86 * $sx), (int) (4 * $sy), (int) (28 * $sx), (int) (5 * $sy), $pillLight);

        ob_start();
        imagepng($canvas);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        return 'data:image/png;base64,'.base64_encode($bytes);
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

    /**
     * A solid-colour band with one smooth full-width elliptical curve on
     * either its bottom edge (dips down through the centre - used for a
     * card's top header) or its top edge (rises up through the centre -
     * used for a bottom footer), the same shape CSS `border-radius: 50% Npx`
     * on both corners produces in a browser.
     *
     * Rendered as GD raster rather than left as CSS: confirmed by an
     * isolated reproduction that dompdf has a real bug where a SECOND
     * `border-radius: 50% ...` curve anywhere later on the same page
     * silently fails to paint at all once a first one has already been
     * rendered - reliable alone, broken as soon as a card needs both a
     * curved header and a curved footer (i.e. every back-of-card design).
     * A pre-rendered image sidesteps dompdf's border-radius handling
     * entirely, so it can't collide with any other curve on the page.
     */
    public function curvedBandDataUri(string $color, int $width, int $height, string $edge, int $depth): string
    {
        // GD's imagefilledpolygon() draws hard, non-anti-aliased edges, which
        // makes a curve built from many short straight segments look like a
        // visible staircase at final size - worse the steeper the curve
        // (the footer's depth is a large fraction of its own height, so it
        // needs more supersampling than a shallow curve would). Drawing at
        // 8x scale and then downsampling with imagecopyresampled() (which
        // does interpolate) smooths that away - the same supersample-then-
        // shrink technique already used for the ID card header graphic,
        // done here in PHP rather than relying on dompdf to shrink an
        // oversized image (which it has already been confirmed to do
        // unreliably).
        $scale = 8;
        $bigWidth = $width * $scale;
        $bigHeight = $height * $scale;
        $bigDepth = $depth * $scale;

        $big = imagecreatetruecolor($bigWidth, $bigHeight);
        imagealphablending($big, false);
        imagesavealpha($big, true);
        $transparent = imagecolorallocatealpha($big, 0, 0, 0, 127);
        imagefill($big, 0, 0, $transparent);
        imagealphablending($big, true);

        [$r, $g, $b] = sscanf(ltrim($color, '#'), '%02x%02x%02x');
        $fill = imagecolorallocate($big, $r, $g, $b);

        // One continuous elliptical arc (centre-x, semi-axes width/2 and
        // depth) traced from theta=0 to pi traces exactly the curve two
        // mirrored quarter-ellipse corners would produce, without the
        // seam/gap risk of building it from two separate halves.
        $steps = 96;
        $curvePoints = [];
        for ($i = 0; $i <= $steps; $i++) {
            $theta = M_PI * $i / $steps;
            $x = $bigWidth / 2 + ($bigWidth / 2) * cos($theta);
            $y = $edge === 'bottom'
                ? ($bigHeight - $bigDepth) + $bigDepth * sin($theta)
                : $bigDepth * (1 - sin($theta));
            $curvePoints[] = [$x, $y];
        }

        $polygon = [];
        if ($edge === 'bottom') {
            // (0,0) -> (width,0) -> curve (right-to-left, dipping through
            // bottom-centre) -> implicit close back to (0,0).
            $polygon[] = 0;
            $polygon[] = 0;
            $polygon[] = $bigWidth;
            $polygon[] = 0;
        } else {
            // (0,height) -> (width,height) -> curve (right-to-left, rising
            // through top-centre) -> implicit close back to (0,height).
            $polygon[] = 0;
            $polygon[] = $bigHeight;
            $polygon[] = $bigWidth;
            $polygon[] = $bigHeight;
        }

        foreach ($curvePoints as [$x, $y]) {
            $polygon[] = $x;
            $polygon[] = $y;
        }

        imagefilledpolygon($big, $polygon, $fill);

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $big, 0, 0, 0, 0, $width, $height, $bigWidth, $bigHeight);
        imagedestroy($big);

        ob_start();
        imagepng($canvas);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        return 'data:image/png;base64,'.base64_encode($bytes);
    }
}
