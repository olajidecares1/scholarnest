<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Services\StampImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The school's official stamp.
 *
 * A school does not have a stamp as a file, it has a rubber stamp, and what
 * it can give us is a photograph of that stamp pressed onto paper. So the
 * upload is PROCESSED rather than kept: the paper is made transparent, the
 * margins are trimmed, and what is stored is the mark itself.
 *
 * Kept on the private disk with no URL, like a signature. A stamp is what
 * makes a document look official, so an address that hands out a clean copy
 * is an address for forging the school's paperwork.
 *
 * On EVERY plan. A stamp is how a school's own paperwork is recognised, not a
 * premium extra.
 */
/**
 * The BYTES of a photographed stamp: a dark ring on light paper.
 *
 * Returned as a string rather than an UploadedFile because the extraction
 * tests read it directly, and a fake upload's temporary file is released
 * before it can be read back.
 */
function stampPng(int $size = 240, string $paper = '#ffffff'): string
{
    $image = imagecreatetruecolor($size, $size);

    $pr = hexdec(substr($paper, 1, 2));
    $pg = hexdec(substr($paper, 3, 2));
    $pb = hexdec(substr($paper, 5, 2));

    imagefill($image, 0, 0, imagecolorallocate($image, (int) $pr, (int) $pg, (int) $pb));

    $ink = imagecolorallocate($image, 20, 30, 120);
    imagesetthickness($image, 6);
    imageellipse($image, (int) ($size / 2), (int) ($size / 2), (int) ($size / 2), (int) ($size / 2), $ink);

    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    return $png;
}

function stampPhotograph(int $size = 240, string $paper = '#ffffff'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('stamp.png', stampPng($size, $paper));
}

/**
 * A photograph of nothing at all.
 */
function blankSheetPng(): string
{
    $blank = imagecreatetruecolor(200, 200);
    imagefill($blank, 0, 0, imagecolorallocate($blank, 255, 255, 255));

    ob_start();
    imagepng($blank);
    $png = (string) ob_get_clean();
    imagedestroy($blank);

    return $png;
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->school = activateSchool(School::factory()->create(), PlanKey::Basic);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

describe('extracting the stamp from a photograph', function () {
    test('the paper becomes transparent and the mark survives', function () {
        $png = app(StampImage::class)->extract(stampPng());

        $image = imagecreatefromstring($png);

        expect($image)->not->toBeFalse();

        // A corner is paper, so it must be gone. GD alpha runs 0 (opaque) to
        // 127 (invisible).
        expect((imagecolorat($image, 0, 0) >> 24) & 0x7F)->toBe(127);

        // And something is still there, the ring.
        $visible = 0;

        for ($y = 0; $y < imagesy($image); $y += 2) {
            for ($x = 0; $x < imagesx($image); $x += 2) {
                if ((((imagecolorat($image, $x, $y) >> 24) & 0x7F)) < 60) {
                    $visible++;
                }
            }
        }

        expect($visible)->toBeGreaterThan(20);
    });

    test('it works on a grey scan, not only on pure white', function () {
        // A scan is never pure white and a phone photograph of one is often
        // grey. The background is sampled from the corners rather than
        // assumed, which is what makes this work.
        $png = app(StampImage::class)->extract(stampPng(paper: '#ebe9e4'));

        $image = imagecreatefromstring($png);

        expect((imagecolorat($image, 0, 0) >> 24) & 0x7F)->toBe(127);
    });

    test('the result is trimmed to the ink', function () {
        // The stamp fills the stored image, so two schools' stamps print at
        // the same size whatever proportion of their photograph they occupied.
        $png = app(StampImage::class)->extract(stampPng(400));

        expect(imagesx(imagecreatefromstring($png)))->toBeLessThan(400);
    });

    test('a blank sheet is refused with something a school can act on', function () {
        expect(fn () => app(StampImage::class)->extract(blankSheetPng()))
            ->toThrow(RuntimeException::class, 'No stamp could be found');
    });

    test('something that is not an image at all is refused', function () {
        expect(fn () => app(StampImage::class)->extract('not an image'))
            ->toThrow(RuntimeException::class);
    });
});

describe('uploading it from School Settings', function () {
    test('a Basic school can upload one - there is no plan restriction', function () {
        $this->actingAs($this->admin)
            ->put(route('settings.update'), [
                'name' => $this->school->name,
                'timezone' => 'Africa/Lagos',
                'stamp' => stampPhotograph(),
            ])
            ->assertSessionHasNoErrors();

        $school = $this->school->fresh();

        expect($school->hasStamp())->toBeTrue()
            ->and($school->stamp_path)->toStartWith('school-stamps/');
    });

    test('it lands on the private disk, never the public one', function () {
        $this->actingAs($this->admin)
            ->put(route('settings.update'), [
                'name' => $this->school->name,
                'timezone' => 'Africa/Lagos',
                'stamp' => stampPhotograph(),
            ]);

        $path = $this->school->fresh()->stamp_path;

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    });

    test('it is embedded, never linked', function () {
        $this->actingAs($this->admin)
            ->put(route('settings.update'), [
                'name' => $this->school->name,
                'timezone' => 'Africa/Lagos',
                'stamp' => stampPhotograph(),
            ]);

        expect($this->school->fresh()->stampDataUri())->toStartWith('data:image/png;base64,')
            ->and(method_exists(School::class, 'stampUrl'))->toBeFalse();
    });

    test('a blank photograph is refused against the field, not with a 500', function () {
        $this->actingAs($this->admin)
            ->put(route('settings.update'), [
                'name' => $this->school->name,
                'timezone' => 'Africa/Lagos',
                'stamp' => UploadedFile::fake()->createWithContent('blank.png', blankSheetPng()),
            ])
            ->assertSessionHasErrors('stamp');

        expect($this->school->fresh()->hasStamp())->toBeFalse();
    });

    test('replacing it removes the file it replaced', function () {
        $this->actingAs($this->admin)->put(route('settings.update'), [
            'name' => $this->school->name,
            'timezone' => 'Africa/Lagos',
            'stamp' => stampPhotograph(),
        ]);

        $first = $this->school->fresh()->stamp_path;

        $this->actingAs($this->admin)->put(route('settings.update'), [
            'name' => $this->school->name,
            'timezone' => 'Africa/Lagos',
            'stamp' => stampPhotograph(260),
        ]);

        expect($this->school->fresh()->stamp_path)->not->toBe($first);

        Storage::disk('local')->assertMissing($first);
    });

    test('a school can remove its stamp', function () {
        $this->actingAs($this->admin)->put(route('settings.update'), [
            'name' => $this->school->name,
            'timezone' => 'Africa/Lagos',
            'stamp' => stampPhotograph(),
        ]);

        $this->actingAs($this->admin)->put(route('settings.update'), [
            'name' => $this->school->name,
            'timezone' => 'Africa/Lagos',
            'remove_stamp' => '1',
        ]);

        expect($this->school->fresh()->hasStamp())->toBeFalse();
    });

    test('the settings page offers it, on a Basic plan', function () {
        $this->actingAs($this->admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Official School Stamp')
            ->assertSee('name="stamp"', false);
    });
});

test('deleting a school takes its stamp with it', function () {
    $this->actingAs($this->admin)->put(route('settings.update'), [
        'name' => $this->school->name,
        'timezone' => 'Africa/Lagos',
        'stamp' => stampPhotograph(),
    ]);

    $path = $this->school->fresh()->stamp_path;

    Storage::disk('local')->assertExists($path);

    $this->school->fresh()->delete();

    Storage::disk('local')->assertMissing($path);
});
