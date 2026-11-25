<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

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
 * photo is never served stale). PNGs stay PNG so a crest or stamp keeps its
 * transparency; everything else becomes a JPEG.
 */
final class PdfImage
{
    private const DIRECTORY = 'pdf-images';

    /**
     * @var array<string, string|null>
     */
    private static array $memo = [];

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
        $relative = self::DIRECTORY.'/'.$hash.'-'.$maxEdge.($isPng ? '.png' : '.jpg');
        $disk = Storage::disk('local');

        if (array_key_exists($relative, self::$memo)) {
            return self::$memo[$relative] ?? $absolutePath;
        }

        if (! $disk->exists($relative)) {
            $scaled = self::scaled($absolutePath, $size[0], $size[1], $maxEdge, $isPng);

            if ($scaled === null) {
                return self::$memo[$relative] = $absolutePath;
            }

            $disk->put($relative, $scaled);
        }

        return self::$memo[$relative] = $disk->path($relative);
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
