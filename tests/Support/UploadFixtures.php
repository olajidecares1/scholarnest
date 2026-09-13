<?php

namespace Tests\Support;

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
 * transparency and nothing wrong with it - which is exactly the kind of file
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

    /** A palette (8-bit) PNG with a transparent index - how many logo tools export. */
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
     * A PNG whose header claims 20000 x 20000 pixels - 400 megapixels - in a
     * few hundred bytes. Decoding it would try to allocate gigabytes.
     */
    public static function pixelBombPng(): UploadedFile
    {
        $ihdr = pack('N', 20000).pack('N', 20000)."\x08\x02\x00\x00\x00";
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', $ihdr).$chunk('IDAT', gzcompress(str_repeat("\0", 1024))).$chunk('IEND', '');

        return self::file($png, 'bomb.png', 'image/png');
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
     * answers path() with the object KEY, the way an S3 disk does - so any
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
