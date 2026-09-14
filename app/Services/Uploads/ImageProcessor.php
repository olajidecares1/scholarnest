<?php

namespace App\Services\Uploads;

use App\Support\Uploads\ImageProfile;
use GdImage;
use Imagick;

/**
 * Turns an uploaded image into the image that is stored.
 *
 * Every image upload in the application passes through here, once, on
 * arrival. It replaces App\Services\ImageOptimizer, which re-encoded files in
 * place and got three things wrong that together made uploads look broken:
 *
 *  1. TRANSPARENCY WAS DESTROYED. A PNG was re-saved without its alpha channel,
 *     so a school logo on a transparent background came back on solid black.
 *  2. PHONE PHOTOGRAPHS CAME OUT SIDEWAYS. A camera stores a portrait photo as
 *     landscape pixels plus an EXIF "rotate me" tag. Re-encoding discarded the
 *     tag without rotating the pixels.
 *  3. IT NEEDED A LOCAL FILE, so it could not work at all once uploads live in
 *     object storage.
 *
 * What happens now, in order:
 *
 *  - The format is read from the BYTES, never from the name or the browser's
 *    claim. Only JPEG, PNG, WebP, GIF and BMP are decoded; HEIC/HEIF is
 *    converted when the server can, and refused with instructions when not.
 *  - The pixel count is checked against memory before anything is decoded, so
 *    a 200-megapixel file is a clear message rather than a crashed request.
 *  - EXIF orientation is applied, so the stored pixels are upright.
 *  - The image is scaled down to its profile's longest edge, never up.
 *  - Metadata (EXIF with GPS, XMP, IPTC, comments) is removed.
 *  - WHEN NOTHING NEEDS CHANGING, THE PIXELS ARE NOT RE-ENCODED. Metadata is cut
 *    out of the file structure losslessly, so an image that already fits is
 *    stored at exactly the quality it was uploaded at. Compressing it again
 *    would only make it worse.
 *  - When it does need re-encoding: JPEG at quality 90, PNG losslessly, WebP at
 *    90, each keeping its own format, so transparency survives.
 */
class ImageProcessor
{
    /** No upload is decoded beyond this, whatever memory is available. */
    public const MAX_PIXELS = 80_000_000;

    private const JPEG_QUALITY = 90;

    private const WEBP_QUALITY = 90;

    private const PNG_COMPRESSION = 6;

    /**
     * Formats accepted, as sniffed from content, and what they are stored as.
     *
     * @var array<string, string>
     */
    private const FORMATS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];

    public const HEIC_MESSAGE = 'This photo is in Apple\'s HEIC format, which this server cannot convert yet. '
        .'On an iPhone, choose the photo with the Upload Photo button (Safari converts it automatically), '
        .'or set Settings > Camera > Formats to "Most Compatible" and take it again.';

    /**
     * The cheap checks, without decoding: what the file really is, and whether
     * it is small enough to decode. Used by validation, so a hopeless file is
     * refused before anything is stored.
     *
     * @return array{mime: string, width: int, height: int}
     *
     * @throws ImageRejected
     */
    public function inspect(string $path): array
    {
        $bytes = @file_get_contents($path, false, null, 0, 64);

        if ($bytes === false || $bytes === '') {
            throw new ImageRejected('The file arrived empty. Please choose it again.');
        }

        $mime = $this->sniff($path, $bytes);

        if ($mime === null) {
            throw new ImageRejected('That file is not an image this site accepts. Choose a JPG, PNG or WebP photo.');
        }

        if ($mime === 'image/heic' || $mime === 'image/heif') {
            if (! $this->canConvertHeic()) {
                throw new ImageRejected(self::HEIC_MESSAGE);
            }

            return ['mime' => $mime, 'width' => 0, 'height' => 0];
        }

        $size = @getimagesize($path);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw new ImageRejected('That image appears to be damaged and could not be read. Please choose another copy of it.');
        }

        $this->assertDecodable($size[0], $size[1]);

        return ['mime' => $mime, 'width' => (int) $size[0], 'height' => (int) $size[1]];
    }

    /**
     * @throws ImageRejected
     */
    public function process(string $path, ImageProfile $profile): ProcessedImage
    {
        $info = $this->inspect($path);
        $bytes = (string) file_get_contents($path);
        $mime = $info['mime'];

        if ($mime === 'image/heic' || $mime === 'image/heif') {
            $bytes = $this->convertHeicToJpeg($bytes);
            $mime = 'image/jpeg';

            $size = @getimagesizefromstring($bytes);

            if ($size === false) {
                throw new ImageRejected(self::HEIC_MESSAGE);
            }

            $this->assertDecodable((int) $size[0], (int) $size[1]);
        }

        $orientation = $mime === 'image/jpeg' ? $this->jpegOrientation($bytes) : 1;

        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw new ImageRejected('That image appears to be damaged and could not be read. Please choose another copy of it.');
        }

        try {
            return $this->prepare($image, $bytes, $mime, $orientation, $profile);
        } finally {
            imagedestroy($image);
        }
    }

    private function prepare(GdImage $image, string $bytes, string $mime, int $orientation, ImageProfile $profile): ProcessedImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        // Dimensions as the image will be SEEN, after orientation.
        [$shownWidth, $shownHeight] = $orientation >= 5 ? [$height, $width] : [$width, $height];

        $needsResize = max($shownWidth, $shownHeight) > $profile->maxEdge();
        $needsRotate = $orientation > 1;

        // An animated GIF cannot be resized frame by frame with GD. It is kept
        // exactly as uploaded, GIF carries no EXIF to strip.
        if ($mime === 'image/gif' && $this->isAnimatedGif($bytes)) {
            return new ProcessedImage($bytes, 'image/gif', 'gif', $width, $height);
        }

        // Nothing to change but the metadata: cut it out without touching the
        // pixels, so the stored image is exactly as sharp as the upload.
        if (! $needsResize && ! $needsRotate) {
            $lossless = match ($mime) {
                'image/jpeg' => $this->stripJpegMetadata($bytes),
                'image/png' => $this->stripPngMetadata($bytes),
                'image/webp' => $this->webpHasMetadata($bytes) ? null : $bytes,
                default => null,
            };

            if ($lossless !== null) {
                return new ProcessedImage($lossless, $mime, self::FORMATS[$mime], $width, $height);
            }
        }

        $image = $this->toTrueColor($image);
        $image = $this->applyOrientation($image, $orientation);

        if ($needsResize) {
            $image = $this->scaleDown($image, $profile->maxEdge());
        }

        // GIF and BMP are stored as PNG: lossless, and it keeps transparency.
        $outputMime = in_array($mime, ['image/gif', 'image/bmp'], true) ? 'image/png' : $mime;

        $encoded = $this->encode($image, $outputMime);

        return new ProcessedImage($encoded, $outputMime, self::FORMATS[$outputMime], imagesx($image), imagesy($image));
    }

    // -----------------------------------------------------------------------
    // What the file is
    // -----------------------------------------------------------------------

    private function sniff(string $path, string $head): ?string
    {
        // HEIC/HEIF first: many servers' magic databases do not know it and
        // call it application/octet-stream. The ISO-BMFF brand says what it is.
        if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
            $brand = substr($head, 8, 4);

            if (in_array($brand, ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis'], true)) {
                return 'image/heic';
            }

            if (in_array($brand, ['mif1', 'msf1'], true) && ! str_contains($head, 'avif')) {
                return 'image/heif';
            }
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';

        $mime = match ($mime) {
            'image/jpg', 'image/pjpeg' => 'image/jpeg',
            'image/x-ms-bmp', 'image/x-bmp' => 'image/bmp',
            'image/x-png' => 'image/png',
            default => $mime,
        };

        return array_key_exists($mime, self::FORMATS) ? $mime : null;
    }

    private function assertDecodable(int $width, int $height): void
    {
        $pixels = $width * $height;

        if ($pixels > self::MAX_PIXELS) {
            throw new ImageRejected("That image is {$width} × {$height} pixels, which is too large to process. Please choose a smaller photo.");
        }

        $limit = $this->memoryLimit();

        // Roughly five bytes a pixel for GD's truecolor buffer, plus room for
        // the scaled copy and what the request is already holding.
        $needed = (int) ($pixels * 5 * 1.5);

        if ($limit > 0 && memory_get_usage(true) + $needed > $limit) {
            throw new ImageRejected("That image is {$width} × {$height} pixels, which is too large for this server to process. Please choose a smaller photo.");
        }
    }

    private function memoryLimit(): int
    {
        $value = trim((string) ini_get('memory_limit'));

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function canConvertHeic(): bool
    {
        if (! class_exists(Imagick::class)) {
            return false;
        }

        try {
            return Imagick::queryFormats('HEI*') !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    private function convertHeicToJpeg(string $bytes): string
    {
        try {
            $imagick = new Imagick;
            $imagick->readImageBlob($bytes);

            if (method_exists($imagick, 'autoOrient')) {
                $imagick->autoOrient();
            }

            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(self::JPEG_QUALITY);
            $imagick->stripImage();

            return $imagick->getImageBlob();
        } catch (\Throwable) {
            throw new ImageRejected(self::HEIC_MESSAGE);
        }
    }

    // -----------------------------------------------------------------------
    // Orientation
    // -----------------------------------------------------------------------

    /**
     * The EXIF Orientation tag (1-8) of a JPEG, read from the file structure.
     *
     * Parsed here rather than with exif_read_data(), which is an optional
     * extension and warns on the malformed EXIF some phones write.
     */
    public function jpegOrientation(string $bytes): int
    {
        $length = strlen($bytes);

        if ($length < 4 || substr($bytes, 0, 2) !== "\xFF\xD8") {
            return 1;
        }

        $pos = 2;

        while ($pos + 4 <= $length) {
            if (ord($bytes[$pos]) !== 0xFF) {
                return 1;
            }

            $marker = ord($bytes[$pos + 1]);

            if ($marker === 0xFF) {
                $pos++;

                continue;
            }

            if ($marker === 0xDA || $marker === 0xD9) {
                return 1;
            }

            $segmentLength = unpack('n', substr($bytes, $pos + 2, 2))[1];

            if ($marker === 0xE1 && substr($bytes, $pos + 4, 6) === "Exif\0\0") {
                return $this->tiffOrientation(substr($bytes, $pos + 10, max(0, $segmentLength - 8)));
            }

            $pos += 2 + $segmentLength;
        }

        return 1;
    }

    private function tiffOrientation(string $tiff): int
    {
        $order = substr($tiff, 0, 2);

        if (strlen($tiff) < 8 || ($order !== 'II' && $order !== 'MM')) {
            return 1;
        }

        $little = $order === 'II';
        $u16 = fn (int $at): ?int => $at + 2 <= strlen($tiff) ? unpack($little ? 'v' : 'n', substr($tiff, $at, 2))[1] : null;
        $u32 = fn (int $at): ?int => $at + 4 <= strlen($tiff) ? unpack($little ? 'V' : 'N', substr($tiff, $at, 4))[1] : null;

        $ifd = $u32(4);
        $count = $ifd === null ? null : $u16($ifd);

        if ($count === null) {
            return 1;
        }

        for ($i = 0; $i < min($count, 512); $i++) {
            $entry = $ifd + 2 + $i * 12;

            if ($u16($entry) === 0x0112) {
                $value = $u16($entry + 8);

                return $value !== null && $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }

    private function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        // imagerotate() turns COUNTER-clockwise; 270 is a quarter turn clockwise.
        $rotate = function (GdImage $source, int $degrees): GdImage {
            $rotated = imagerotate($source, $degrees, 0);
            imagedestroy($source);
            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);

            return $rotated;
        };

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $image = $rotate($image, 180);
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $image = $rotate($image, 270);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $image = $rotate($image, 270);
                break;
            case 7:
                $image = $rotate($image, 90);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $image = $rotate($image, 90);
                break;
        }

        return $image;
    }

    // -----------------------------------------------------------------------
    // Pixels
    // -----------------------------------------------------------------------

    private function toTrueColor(GdImage $image): GdImage
    {
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    private function scaleDown(GdImage $source, int $maxEdge): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = $maxEdge / max($width, $height);

        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $scaled = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));

        imagecopyresampled($scaled, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);

        return $scaled;
    }

    private function encode(GdImage $image, string $mime): string
    {
        ob_start();

        try {
            $ok = match ($mime) {
                'image/jpeg' => (function () use ($image) {
                    // Progressive: a slow connection shows the whole photo
                    // early and sharpens it, rather than drawing it top-down.
                    imageinterlace($image, true);

                    return imagejpeg($image, null, self::JPEG_QUALITY);
                })(),
                'image/png' => imagepng($image, null, self::PNG_COMPRESSION),
                'image/webp' => imagewebp($image, null, self::WEBP_QUALITY),
            };

            $bytes = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if (! $ok || $bytes === '') {
            throw new ImageRejected('That image could not be processed. Please try a JPG or PNG copy of it.');
        }

        return $bytes;
    }

    // -----------------------------------------------------------------------
    // Lossless metadata removal
    // -----------------------------------------------------------------------

    /**
     * A JPEG with its metadata segments removed and its pixels untouched.
     *
     * Kept: JFIF (APP0), the ICC colour profile (APP2 "ICC_PROFILE"), Adobe
     * (APP14, needed to decode CMYK correctly), and every segment that carries
     * the image itself. Dropped: EXIF and XMP (APP1), IPTC (APP13), other
     * vendor APPn blocks, comments, and anything after the image ends, which
     * is where phones append depth maps and where a hidden payload would sit.
     *
     * Returns null when the structure cannot be followed; the caller then
     * re-encodes instead, which strips everything anyway.
     */
    public function stripJpegMetadata(string $bytes): ?string
    {
        $length = strlen($bytes);

        if ($length < 4 || substr($bytes, 0, 2) !== "\xFF\xD8") {
            return null;
        }

        $out = "\xFF\xD8";
        $pos = 2;

        while ($pos + 2 <= $length) {
            if (ord($bytes[$pos]) !== 0xFF) {
                return null;
            }

            $marker = ord($bytes[$pos + 1]);

            if ($marker === 0xFF) {
                $pos++;

                continue;
            }

            if ($marker === 0xD9) {
                return $out."\xFF\xD9";
            }

            if ($pos + 4 > $length) {
                return null;
            }

            $segmentLength = unpack('n', substr($bytes, $pos + 2, 2))[1];

            if ($segmentLength < 2 || $pos + 2 + $segmentLength > $length) {
                return null;
            }

            $keep = match (true) {
                $marker === 0xE1, $marker === 0xED, $marker === 0xFE => false,
                $marker === 0xE2 => substr($bytes, $pos + 4, 12) === "ICC_PROFILE\0",
                $marker >= 0xE3 && $marker <= 0xEF && $marker !== 0xEE => false,
                default => true,
            };

            if ($keep) {
                $out .= substr($bytes, $pos, 2 + $segmentLength);
            }

            $pos += 2 + $segmentLength;

            if ($marker !== 0xDA) {
                continue;
            }

            // Entropy-coded data follows a start-of-scan until the next real
            // marker. 0xFF00 is an escaped byte and 0xFFD0-D7 are restart
            // markers, both part of the data. Progressive JPEGs have several
            // scans, so parsing resumes at whatever marker ends this one.
            $start = $pos;

            while (true) {
                $next = strpos($bytes, "\xFF", $pos);

                if ($next === false || $next + 1 >= $length) {
                    return null;
                }

                $following = ord($bytes[$next + 1]);

                if ($following === 0x00 || ($following >= 0xD0 && $following <= 0xD7) || $following === 0xFF) {
                    $pos = $next + ($following === 0xFF ? 1 : 2);

                    continue;
                }

                $pos = $next;
                break;
            }

            $out .= substr($bytes, $start, $pos - $start);
        }

        return null;
    }

    /**
     * A PNG with its text, time and EXIF chunks removed, pixels untouched.
     */
    public function stripPngMetadata(string $bytes): ?string
    {
        $signature = "\x89PNG\r\n\x1a\n";

        if (! str_starts_with($bytes, $signature)) {
            return null;
        }

        $out = $signature;
        $pos = 8;
        $length = strlen($bytes);

        while ($pos + 12 <= $length) {
            $chunkLength = unpack('N', substr($bytes, $pos, 4))[1];
            $type = substr($bytes, $pos + 4, 4);
            $total = 12 + $chunkLength;

            if ($pos + $total > $length) {
                return null;
            }

            if (! in_array($type, ['eXIf', 'tEXt', 'zTXt', 'iTXt', 'tIME'], true)) {
                $out .= substr($bytes, $pos, $total);
            }

            $pos += $total;

            if ($type === 'IEND') {
                return $out;
            }
        }

        return null;
    }

    private function webpHasMetadata(string $bytes): bool
    {
        return str_contains($bytes, 'EXIF') || str_contains($bytes, 'XMP ');
    }

    private function isAnimatedGif(string $bytes): bool
    {
        return substr_count($bytes, "\x00\x21\xF9\x04") > 1;
    }
}
