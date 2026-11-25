<?php

use App\Services\GoldSignature;
use Illuminate\Support\Facades\Storage;

/**
 * Signatures on a report card print big and bold: strokes thickened, the
 * blank border of the upload trimmed off so the ink fills its space, the
 * Principal's in gold and the Class Teacher's in their own ink.
 */
function thinSignatureOnWideBorder(): string
{
    $image = imagecreatetruecolor(900, 360);
    imagesavealpha($image, true);
    imagealphablending($image, false);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    $ink = imagecolorallocatealpha($image, 20, 30, 120, 0);
    imageline($image, 300, 180, 600, 160, $ink);   // a 1px line, 300 x 20
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

test('the blank border is trimmed and the stroke thickened', function () {
    Storage::disk('local')->put('signatures/thin.png', thinSignatureOnWideBorder());

    $path = app(GoldSignature::class)->pathFor('signatures/thin.png');
    $image = imagecreatefromstring(Storage::disk('local')->get($path));

    // Trimmed from 900 x 360 to the line plus a few pixels of air.
    expect(imagesx($image))->toBeLessThan(320)
        ->and(imagesy($image))->toBeLessThan(40);

    // Thickened: a 1px line is now about 5px tall at any point along it.
    $solidInColumn = collect(range(0, imagesy($image) - 1))
        ->filter(fn ($y) => ((imagecolorat($image, (int) (imagesx($image) / 2), $y) >> 24) & 0x7F) < 64)
        ->count();

    expect($solidInColumn)->toBeGreaterThanOrEqual(5);
});

test('the class teacher\'s signature keeps its own ink, the principal\'s turns gold', function () {
    Storage::disk('local')->put('signatures/thin2.png', thinSignatureOnWideBorder());

    $ownInk = imagecreatefromstring(Storage::disk('local')->get(app(GoldSignature::class)->pathFor('signatures/thin2.png', ownInk: true)));
    $gold = imagecreatefromstring(Storage::disk('local')->get(app(GoldSignature::class)->pathFor('signatures/thin2.png')));

    $colourAtMiddle = function ($image) {
        foreach (range(0, imagesy($image) - 1) as $y) {
            $rgba = imagecolorat($image, (int) (imagesx($image) / 2), $y);
            if ((($rgba >> 24) & 0x7F) === 0) {
                return [($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF];
            }
        }
    };

    // Dark blue, darkened a touch; and the gold.
    expect($colourAtMiddle($ownInk)[2])->toBeGreaterThan($colourAtMiddle($ownInk)[0])
        ->and($colourAtMiddle($gold))->toBe([0xC8, 0xA3, 0x4A]);
});
