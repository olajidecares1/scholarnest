<?php

namespace Tests\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Real files for upload tests.
 *
 * UploadedFile::fake()->image() makes a plain GD image with no EXIF, no
 * transparency and nothing wrong with it, which is exactly the kind of file
 * that never broke. These are the files that did: a phone photograph with an
 * orientation tag and GPS coordinates, a logo on a transparent background, a
 * truncated JPEG, a script wearing a .jpg name, an iPhone HEIC.
 */
final class UploadFixtures
{
    /**
     * A camera JPEG as a phone writes it: the pixels stored landscape, with an
     * EXIF Orientation tag saying how to turn them, and GPS coordinates.
     *
     * The stored pixels are white with a solid BLUE stripe down their LEFT
     * edge, so where that stripe ends up proves which way the image was turned.
     */
    public static function cameraJpeg(int $orientation = 6, int $width = 400, int $height = 300, string $name = 'IMG_20260913_101500.jpg', bool $progressive = false): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 0, 0, (int) ($width * 0.15), $height - 1, imagecolorallocate($image, 0, 0, 255));
        imageinterlace($image, $progressive);

        ob_start();
        imagejpeg($image, null, 92);
        $jpeg = (string) ob_get_clean();

        return self::file(self::withExif($jpeg, $orientation), $name, 'image/jpeg');
    }

    /**
     * EXIF with an Orientation tag and a GPS IFD pointer, inserted after SOI.
     */
    public static function withExif(string $jpeg, int $orientation): string
    {
        // IFD0: Orientation (0x0112) and GPSInfo pointer (0x8825) -> GPS IFD.
        $ifd0Offset = 8;
        $gpsOffset = $ifd0Offset + 2 + 2 * 12 + 4;

        $tiff = 'II'.pack('v', 42).pack('V', $ifd0Offset)
            .pack('v', 2)
            .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation).pack('v', 0)
            .pack('v', 0x8825).pack('v', 4).pack('V', 1).pack('V', $gpsOffset)
            .pack('V', 0)
            // GPS IFD: GPSLatitudeRef = "N".
            .pack('v', 1)
            .pack('v', 0x0001).pack('v', 2).pack('V', 2).'N'."\0\0\0"
            .pack('V', 0)
            .'GPS-6.5244N-3.3792E';

        $segment = "Exif\0\0".$tiff;

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($segment) + 2).$segment.substr($jpeg, 2);
    }

    /** A logo: opaque red square on a fully transparent background. */
    public static function transparentPng(int $width = 200, int $height = 200, string $name = 'logo.png'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, (int) ($width * 0.3), (int) ($height * 0.3), (int) ($width * 0.7), (int) ($height * 0.7), imagecolorallocatealpha($image, 255, 0, 0, 0));

        ob_start();
        imagepng($image);

        return self::file((string) ob_get_clean(), $name, 'image/png');
    }

    /** A palette (8-bit) PNG with a transparent index, how many logo tools export. */
    public static function palettePng(int $width = 3000, int $height = 1000): UploadedFile
    {
        $image = imagecreate($width, $height);
        $transparent = imagecolorallocate($image, 0, 255, 0);
        imagecolortransparent($image, $transparent);
        imagefilledrectangle($image, (int) ($width * 0.4), (int) ($height * 0.25), (int) ($width * 0.6), (int) ($height * 0.75), imagecolorallocate($image, 10, 20, 200));

        ob_start();
        imagepng($image);

        return self::file((string) ob_get_clean(), 'palette-logo.png', 'image/png');
    }

    /** A transparent WebP, large enough to be resized. */
    public static function transparentWebp(int $width = 3000, int $height = 2000): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, (int) ($width * 0.3), (int) ($height * 0.3), (int) ($width * 0.7), (int) ($height * 0.7), imagecolorallocatealpha($image, 0, 150, 0, 0));

        ob_start();
        imagewebp($image, null, 90);

        return self::file((string) ob_get_clean(), 'banner.webp', 'image/webp');
    }

    /** A JPEG cut off part-way through its image data. */
    public static function truncatedJpeg(): UploadedFile
    {
        $bytes = (string) file_get_contents(self::cameraJpeg(1, 1200, 900)->getRealPath());

        return self::file(substr($bytes, 0, (int) (strlen($bytes) * 0.2)), 'broken.jpg', 'image/jpeg');
    }

    /** Pure garbage behind a JPEG start-of-image marker. */
    public static function corruptJpeg(): UploadedFile
    {
        return self::file("\xFF\xD8\xFF\xE0".random_bytes(4096), 'corrupt.jpg', 'image/jpeg');
    }

    /** A PHP script wearing an image's name and an image's claimed type. */
    public static function phpDisguisedAsJpeg(): UploadedFile
    {
        return self::file("<?php system(\$_GET['c']); ?>\n", 'innocent-photo.jpg', 'image/jpeg');
    }

    /** The start of an iPhone HEIC file: an ISO-BMFF ftyp box with the heic brand. */
    public static function heic(): UploadedFile
    {
        $ftyp = pack('N', 24).'ftyp'.'heic'.pack('N', 0).'mif1heic';

        return self::file($ftyp.random_bytes(2048), 'IMG_4821.HEIC', 'image/heic');
    }

    /**
     * A PNG whose header claims 20000 x 20000 pixels, 400 megapixels, in a
     * few hundred bytes. Decoding it would try to allocate gigabytes.
     */
    public static function pixelBombPng(): UploadedFile
    {
        $ihdr = pack('N', 20000).pack('N', 20000)."\x08\x02\x00\x00\x00";
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', $ihdr).$chunk('IDAT', gzcompress(str_repeat("\0", 1024))).$chunk('IEND', '');

        return self::file($png, 'bomb.png', 'image/png');
    }

    /**
     * A screenshot of a bank transfer confirmation: dark text on white.
     */
    public static function receiptPng(string $name = 'receipt.png'): UploadedFile
    {
        $image = imagecreatetruecolor(480, 640);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $ink = imagecolorallocate($image, 20, 20, 20);

        foreach (['Transfer Successful', 'Amount: NGN 50,000.00', 'Reference: TRF/2026/00912', 'Beneficiary: AkademicNest'] as $row => $line) {
            imagestring($image, 5, 30, 40 + $row * 40, $line, $ink);
        }

        ob_start();
        imagepng($image);

        return self::file((string) ob_get_clean(), $name, 'image/png');
    }

    /** A solid white image, the size of a phone screenshot. */
    public static function blankPng(): UploadedFile
    {
        $image = imagecreatetruecolor(480, 640);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

        ob_start();
        imagepng($image);

        return self::file((string) ob_get_clean(), 'blank.png', 'image/png');
    }

    /**
     * A real PDF with selectable text, one line per entry.
     *
     * @param  list<string>  $lines
     */
    public static function textPdf(array $lines, string $name = 'receipt.pdf'): UploadedFile
    {
        $html = '<html><body>'.collect($lines)->map(fn (string $line) => '<p>'.e($line).'</p>')->implode('').'</body></html>';

        return self::file(Pdf::loadHTML($html)->output(), $name, 'application/pdf');
    }

    /**
     * A phone photograph of a signature on paper, as it really arrives: the
     * paper off-white and lit unevenly, a shadow across one corner, grain,
     * a strip of brown desk down the left edge, and blue ink signed across
     * it, with a separate dot and an underline that runs into the shadow.
     */
    public static function signaturePhoto(int $width = 800, int $height = 600, string $name = 'signature-photo.jpg'): UploadedFile
    {
        mt_srand(11);
        $image = imagecreatetruecolor($width, $height);
        $desk = (int) ($width * 0.07);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($x < $desk) {
                    imagesetpixel($image, $x, $y, imagecolorallocate($image, 70, 50, 35));

                    continue;
                }

                $light = 235 - ($x / $width) * 45 - ($y / $height) * 25;

                if ($x > $width * 0.56 && $y > $height * 0.58) {
                    $light -= 40;
                }

                $grain = mt_rand(-6, 6);
                $tone = (int) max(0, min(255, $light + $grain));
                imagesetpixel($image, $x, $y, imagecolorallocate($image, $tone, max(0, $tone - 2), max(0, $tone - 8)));
            }
        }

        imagesetthickness($image, max(2, (int) round($width / 230)));
        $ink = imagecolorallocate($image, 25, 40, 120);
        $previous = null;

        for ($t = 0.0; $t <= 1.0; $t += 0.002) {
            $x = $width * 0.25 + $t * $width * 0.5;
            $y = $height * 0.54 + sin($t * 18) * $height * 0.075 * (1 - $t * 0.4) + cos($t * 7) * $height * 0.033;

            if ($previous !== null) {
                imageline($image, (int) $previous[0], (int) $previous[1], (int) $x, (int) $y, $ink);
            }

            $previous = [$x, $y];
        }

        imagefilledellipse($image, (int) ($width * 0.44), (int) ($height * 0.43), max(4, (int) ($width / 115)), max(4, (int) ($width / 115)), $ink);
        imageline($image, (int) ($width * 0.28), (int) ($height * 0.68), (int) ($width * 0.72), (int) ($height * 0.66), $ink);

        ob_start();
        imagejpeg($image, null, 85);
        imagedestroy($image);

        return self::file((string) ob_get_clean(), $name, 'image/jpeg');
    }

    /** A sheet of blank, unevenly lit paper: a photo with no signature on it. */
    public static function blankPaperPhoto(int $width = 600, int $height = 450): UploadedFile
    {
        mt_srand(5);
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $tone = (int) max(0, min(255, 232 - $x / 20 + mt_rand(-5, 5)));
                imagesetpixel($image, $x, $y, imagecolorallocate($image, $tone, $tone, max(0, $tone - 6)));
            }
        }

        ob_start();
        imagejpeg($image, null, 85);
        imagedestroy($image);

        return self::file((string) ob_get_clean(), 'blank.jpg', 'image/jpeg');
    }

    public static function file(string $bytes, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload_fixture_');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $mime, null, true);
    }

    /**
     * Point both upload disks at storage that behaves like object storage.
     *
     * Files are kept in a directory the TEST can inspect, but the disk itself
     * answers path() with the object KEY, the way an S3 disk does, so any
     * code that still treats ->path() as a real file fails here exactly as it
     * would in production. url() returns a bucket-style address.
     *
     * @return array{public: string, local: string} the directories standing in for the buckets
     */
    public static function useObjectStorageLikeDisks(): array
    {
        Storage::extend('bucket-like', function ($app, array $config) {
            $adapter = new LocalFilesystemAdapter($config['root']);

            return new class(new Filesystem($adapter), $adapter, $config) extends FilesystemAdapter
            {
                public function path($path)
                {
                    return $path;
                }

                public function url($path)
                {
                    return rtrim($this->config['url'], '/').'/'.ltrim($path, '/');
                }
            };
        });

        $roots = [];

        foreach (['public', 'local'] as $disk) {
            $root = storage_path('framework/testing/buckets/'.$disk);
            File::deleteDirectory($root);
            File::ensureDirectoryExists($root);

            config(["filesystems.disks.{$disk}" => [
                'driver' => 'bucket-like',
                'root' => $root,
                'url' => 'https://bucket.example.test/'.$disk,
            ]]);

            Storage::forgetDisk($disk);
            $roots[$disk] = $root;
        }

        return $roots;
    }
}
