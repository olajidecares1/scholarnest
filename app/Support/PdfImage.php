<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * A picture made the right size for a PDF before dompdf sees it.
 *
 * A passport photo taken on a phone is 4000 x 3000 pixels and 8 MB, and on a
 * report card it prints about 3 cm wide. dompdf embeds whatever it is given,
 * at full size: one pupil's PDF carried the whole 8 MB, and a class of them
 * ran the server out of memory (ten pupils: an 85 MB file and 334 MB of
 * memory), which is what a School Admin saw as a 500 when downloading a
 * class's results.
 *
 * So each picture is scaled down to what the card can use, once, and the
 * small copy is kept (keyed by the picture's own contents, so a replaced
 * photo is never served stale).
 *
 * Kept in a folder on THIS server, never on a Storage disk: on Laravel Cloud
 * the "local" disk is a bucket, and dompdf can only read real files. The same
 * place UploadStorage::localPath() keeps its working copies. PNGs stay PNG so a crest or stamp keeps its
 * transparency; everything else becomes a JPEG.
 */
final class PdfImage
{
    /**
     * @var array<string, string>
     */
    private static array $memo = [];

    /**
     * fit(), and laid onto a solid background so the PDF holds no
     * see-through picture at all.
     *
     * dompdf hands a PNG with an alpha channel to ImageMagick when the server
     * has it, and on Laravel Cloud ImageMagick refuses the file ("unable to
     * open image 'file:///...'"): every report card in a class failed on the
     * school's crest. A picture with nothing see-through never goes near
     * ImageMagick. The background is the colour the picture sits on, white
     * on a report card, so on paper it looks exactly the same.
     */
    public static function flat(?string $absolutePath, int $maxEdge, string $background = '#ffffff'): ?string
    {
        $path = self::fit($absolutePath, $maxEdge);

        if ($path === null || $path === '' || ! is_file($path)) {
            return $path;
        }

        $size = @getimagesize($path);

        if ($size === false || ($size[2] ?? null) !== IMAGETYPE_PNG) {
            return $path; // JPEG and the like are never see-through.
        }

        $hex = ltrim($background, '#');
        $directory = storage_path('framework/cache/pdf-images');
        $target = $directory.'/'.sha1_file($path).'-flat-'.$hex.'.png';

        if (isset(self::$memo[$target]) && is_file(self::$memo[$target])) {
            return self::$memo[$target];
        }

        if (! is_file($target)) {
            $source = @imagecreatefromstring((string) file_get_contents($path));

            if ($source === false) {
                return $path;
            }

            $width = imagesx($source);
            $height = imagesy($source);
            $canvas = imagecreatetruecolor($width, $height);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))));
            imagealphablending($canvas, true);
            imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
            imagesavealpha($canvas, false);
            imagedestroy($source);

            File::ensureDirectoryExists($directory);
            $written = @imagepng($canvas, $target, 9);
            imagedestroy($canvas);

            if (! $written) {
                return $path;
            }
        }

        return self::$memo[$target] = $target;
    }

    /**
     * A local path to a copy no larger than $maxEdge on its longer side, or
     * the original when it is already small enough or cannot be read as an
     * image (dompdf then does what it always did).
     */
    public static function fit(?string $absolutePath, int $maxEdge): ?string
    {
        if ($absolutePath === null || $absolutePath === '' || ! is_file($absolutePath)) {
            return $absolutePath;
        }

        $size = @getimagesize($absolutePath);

        if ($size === false || max($size[0], $size[1]) <= $maxEdge) {
            return $absolutePath;
        }

        $hash = sha1_file($absolutePath);
        $isPng = ($size[2] ?? null) === IMAGETYPE_PNG;
        $directory = storage_path('framework/cache/pdf-images');
        $target = $directory.'/'.$hash.'-'.$maxEdge.($isPng ? '.png' : '.jpg');

        // Only ever a small copy this class made itself is remembered. The
        // path it was given is a temporary copy (UploadStorage::localPath())
        // that may be gone by the next request, so it is never kept.
        if (isset(self::$memo[$target]) && is_file(self::$memo[$target])) {
            return self::$memo[$target];
        }

        if (! is_file($target)) {
            $scaled = self::scaled($absolutePath, $size[0], $size[1], $maxEdge, $isPng);

            if ($scaled === null) {
                return $absolutePath;
            }

            File::ensureDirectoryExists($directory);

            if (@file_put_contents($target, $scaled) === false) {
                return $absolutePath;
            }
        }

        return self::$memo[$target] = $target;
    }

    private static function scaled(string $path, int $width, int $height, int $maxEdge, bool $png): ?string
    {
        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            return null;
        }

        $ratio = $maxEdge / max($width, $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($newWidth, $newHeight);

        if ($png) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        } else {
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);

        ob_start();
        $png ? imagepng($target, null, 9) : imagejpeg($target, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($target);

        return $bytes === '' ? null : $bytes;
    }
}
