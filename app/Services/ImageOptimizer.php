<?php

namespace App\Services;

class ImageOptimizer
{
    private const MAX_DIMENSION = 2560;

    private const JPEG_QUALITY = 82;

    private const PNG_COMPRESSION = 6;

    /**
     * Re-encode and downscale an image in place, stripping EXIF metadata.
     *
     * @return array{width: int, height: int, size: int}
     */
    public function optimize(string $absolutePath, string $mimeType): array
    {
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : null,
            default => null,
        };

        if (! $image) {
            $size = filesize($absolutePath);

            return ['width' => 0, 'height' => 0, 'size' => $size !== false ? $size : 0];
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) > self::MAX_DIMENSION) {
            $ratio = self::MAX_DIMENSION / max($width, $height);
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);

            $image = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $absolutePath, self::JPEG_QUALITY),
            'image/png' => imagepng($image, $absolutePath, self::PNG_COMPRESSION),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $absolutePath, self::JPEG_QUALITY) : null,
            default => null,
        };

        imagedestroy($image);

        $size = filesize($absolutePath);

        return ['width' => $width, 'height' => $height, 'size' => $size !== false ? $size : 0];
    }
}
