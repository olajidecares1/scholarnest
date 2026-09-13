<?php

namespace App\Services\Uploads;

/**
 * Where a processed image was stored, and what it turned out to be.
 */
final class StoredImage
{
    public function __construct(
        public readonly string $path,
        public readonly int $width,
        public readonly int $height,
        public readonly int $size,
        public readonly string $mimeType,
    ) {}
}
