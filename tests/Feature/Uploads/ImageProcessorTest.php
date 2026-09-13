<?php

use App\Services\Uploads\ImageProcessor;
use App\Services\Uploads\ImageRejected;
use App\Services\Uploads\ProcessedImage;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\UploadedFile;
use Tests\Support\UploadFixtures;

/**
 * What happens to an image between arriving and being stored.
 *
 * The processor this replaced turned transparent logos black and left phone
 * photographs sideways; both are pinned here with real files.
 */
function processed(UploadedFile $file, ImageProfile $profile = ImageProfile::Portrait): ProcessedImage
{
    return app(ImageProcessor::class)->process((string) $file->getRealPath(), $profile);
}

/** @return array{red: int, green: int, blue: int, alpha: int} */
function pixelAt(string $bytes, int $x, int $y): array
{
    $image = imagecreatefromstring($bytes);

    return imagecolorsforindex($image, imagecolorat($image, $x, $y));
}

describe('phone photographs come out upright', function () {
    /*
     * The fixture's stored pixels are 400x300 with a blue stripe down the LEFT
     * edge. For each EXIF orientation, this is where that stripe must be once
     * the tag has been applied - the same place every browser draws it.
     */
    test('every EXIF orientation is applied', function (int $orientation, int $width, int $height, string $stripe) {
        $image = processed(UploadFixtures::cameraJpeg($orientation));

        expect([$image->width, $image->height])->toBe([$width, $height])
            // The tag is gone: the pixels ARE upright now, and a viewer that
            // applied it a second time would turn the photo again.
            ->and(app(ImageProcessor::class)->jpegOrientation($image->bytes))->toBe(1);

        [$x, $y] = match ($stripe) {
            'left' => [5, (int) ($height / 2)],
            'right' => [$width - 6, (int) ($height / 2)],
            'top' => [(int) ($width / 2), 5],
            'bottom' => [(int) ($width / 2), $height - 6],
        };

        expect(pixelAt($image->bytes, $x, $y)['blue'])->toBeGreaterThan(200)
            ->and(pixelAt($image->bytes, $x, $y)['red'])->toBeLessThan(60);
    })->with([
        'normal' => [1, 400, 300, 'left'],
        'mirrored' => [2, 400, 300, 'right'],
        'upside down' => [3, 400, 300, 'right'],
        'flipped' => [4, 400, 300, 'left'],
        'transposed' => [5, 300, 400, 'top'],
        'portrait, rotated clockwise (the usual iPhone case)' => [6, 300, 400, 'top'],
        'transversed' => [7, 300, 400, 'bottom'],
        'portrait, rotated anticlockwise' => [8, 300, 400, 'bottom'],
    ]);

    test('a large modern phone photo is scaled to its use, never up', function () {
        $big = processed(UploadFixtures::cameraJpeg(6, 4032, 3024), ImageProfile::Portrait);
        $small = processed(UploadFixtures::cameraJpeg(1, 320, 240), ImageProfile::Website);

        expect([$big->width, $big->height])->toBe([1200, 1600])
            ->and([$small->width, $small->height])->toBe([320, 240]);
    });

    test('GPS and every other EXIF field is removed', function () {
        $image = processed(UploadFixtures::cameraJpeg(1));

        expect($image->bytes)->not->toContain('Exif')
            ->and($image->bytes)->not->toContain('GPS-6.5244N');
    });

    test('a camera-generated filename is irrelevant - only the content is read', function () {
        $image = processed(UploadFixtures::cameraJpeg(6, name: 'DSC_0042.JPG.upload'));

        expect($image->mimeType)->toBe('image/jpeg')->and($image->extension)->toBe('jpg');
    });
});

describe('an image that already fits is not recompressed', function () {
    test('the JPEG image data is byte-for-byte what was uploaded', function () {
        $upload = UploadFixtures::cameraJpeg(1, 800, 600);
        $original = (string) file_get_contents($upload->getRealPath());

        $image = processed($upload);

        // Everything from the first start-of-scan to the end of the image is
        // the compressed picture itself. It must be untouched.
        $scan = fn (string $bytes) => substr($bytes, strpos($bytes, "\xFF\xDA"), strrpos($bytes, "\xFF\xD9") - strpos($bytes, "\xFF\xDA"));

        expect($scan($image->bytes))->toBe($scan($original))
            ->and(strlen($image->bytes))->toBeLessThan(strlen($original));
    });

    test('a progressive JPEG, with several scans, is handled the same way', function () {
        $upload = UploadFixtures::cameraJpeg(1, 800, 600, progressive: true);

        $image = processed($upload);

        expect(substr_count($image->bytes, "\xFF\xDA"))->toBeGreaterThan(1)
            ->and($image->bytes)->not->toContain('Exif')
            ->and(imagecreatefromstring($image->bytes))->not->toBeFalse();
    });

    test('anything hidden after the end of the image is dropped', function () {
        $upload = UploadFixtures::cameraJpeg(1);
        file_put_contents($upload->getRealPath(), file_get_contents($upload->getRealPath()).'<?php echo "payload"; ?>');

        expect(processed($upload)->bytes)->not->toContain('<?php');
    });
});

describe('transparency survives', function () {
    test('a transparent PNG logo stays transparent when kept as it is', function () {
        $image = processed(UploadFixtures::transparentPng(), ImageProfile::Logo);

        expect($image->mimeType)->toBe('image/png')
            ->and(pixelAt($image->bytes, 2, 2)['alpha'])->toBe(127)
            ->and(pixelAt($image->bytes, 100, 100))->toMatchArray(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0]);
    });

    test('and when it has to be scaled down', function () {
        $image = processed(UploadFixtures::transparentPng(3200, 3200), ImageProfile::Logo);

        expect([$image->width, $image->height])->toBe([1600, 1600])
            ->and(pixelAt($image->bytes, 3, 3)['alpha'])->toBe(127)
            ->and(pixelAt($image->bytes, 800, 800)['alpha'])->toBe(0);
    });

    test('a palette PNG with a transparent colour keeps it', function () {
        $image = processed(UploadFixtures::palettePng(), ImageProfile::Logo);

        expect(pixelAt($image->bytes, 5, 5)['alpha'])->toBe(127)
            ->and(pixelAt($image->bytes, (int) ($image->width / 2), (int) ($image->height / 2))['alpha'])->toBe(0);
    });

    test('a transparent WebP stays WebP and stays transparent', function () {
        $image = processed(UploadFixtures::transparentWebp(), ImageProfile::Website);

        expect($image->mimeType)->toBe('image/webp')
            ->and($image->width)->toBe(2560)
            ->and(pixelAt($image->bytes, 4, 4)['alpha'])->toBeGreaterThan(120);
    });
});

describe('what is refused, and what it is told', function () {
    test('a script named like a photo is not an image', function () {
        processed(UploadFixtures::phpDisguisedAsJpeg());
    })->throws(ImageRejected::class, 'not an image this site accepts');

    test('a corrupt JPEG is refused as damaged', function () {
        processed(UploadFixtures::corruptJpeg());
    })->throws(ImageRejected::class);

    test('a truncated JPEG is refused or repaired, never stored broken', function () {
        try {
            $image = processed(UploadFixtures::truncatedJpeg());
        } catch (ImageRejected) {
            expect(true)->toBeTrue();

            return;
        }

        expect(imagecreatefromstring($image->bytes))->not->toBeFalse();
    });

    test('an image too large in pixels to decode is refused before decoding', function () {
        processed(UploadFixtures::pixelBombPng());
    })->throws(ImageRejected::class, '20000 × 20000 pixels');

    test('an iPhone HEIC photo gets instructions, not a generic error, when it cannot be converted', function () {
        if (class_exists(Imagick::class) && Imagick::queryFormats('HEI*') !== []) {
            $this->markTestSkipped('This server can convert HEIC.');
        }

        processed(UploadFixtures::heic());
    })->throws(ImageRejected::class, 'Most Compatible');

    test('an empty file is refused', function () {
        processed(UploadFixtures::file('', 'empty.jpg', 'image/jpeg'));
    })->throws(ImageRejected::class, 'arrived empty');
});
