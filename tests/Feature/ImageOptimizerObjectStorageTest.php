<?php

use App\Services\ImageOptimizer;

/**
 * The optimizer re-encodes an image in place, so it needs a file on this
 * machine. With a disk on object storage - as production must be, on Laravel
 * Cloud - Storage::disk(...)->path() returns the object's key instead, and
 * there is no file at that path.
 *
 * filesize() on a missing file raises a warning, and Laravel turns warnings
 * into exceptions: the upload, which had already been stored, answered 500.
 * Every school logo, news image, CBT question image and media library upload
 * would have failed that way the moment a bucket was attached.
 */
test('a path that is not a local file is skipped, not fatal', function () {
    $result = app(ImageOptimizer::class)->optimize('branding/logo-in-a-bucket.png', 'image/png');

    expect($result)->toBe(['width' => 0, 'height' => 0, 'size' => 0]);
});

test('a real local image is still optimized', function () {
    $path = tempnam(sys_get_temp_dir(), 'opt').'.png';
    $image = imagecreatetruecolor(20, 10);
    imagepng($image, $path);

    try {
        $result = app(ImageOptimizer::class)->optimize($path, 'image/png');

        expect($result['width'])->toBe(20)
            ->and($result['height'])->toBe(10)
            ->and($result['size'])->toBeGreaterThan(0);
    } finally {
        @unlink($path);
    }
});
