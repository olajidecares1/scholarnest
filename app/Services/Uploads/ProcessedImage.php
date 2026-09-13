<?php

namespace App\Services\Uploads;

/**
 * An image ready to be stored: upright, within its size, metadata removed.
 */
final class ProcessedImage
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $mimeType,
        public readonly string $extension,
        public readonly int $width,
        public readonly int $height,
    ) {}
}
