<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use App\Services\SignatureExtractor;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UploadFixtures;

/**
 * A signature photographed on paper, alongside the one drawn on the pad.
 *
 * The photo goes to an extract endpoint that removes the paper and sends the
 * signature back as a preview, nothing stored. Keeping it is the same POST to
 * the same store action as a drawn signature, so a signature still has one
 * way in, owned by the session.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = activateSchool(School::factory()->create(), PlanKey::Basic);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    $this->staff = Staff::factory()->create(['school_id' => $this->school->id]);
});

/**
 * @return array{0: GdImage, 1: int, 2: int, 3: int} image, width, height, opaque pixel count
 */
function decodePreview(string $dataUrl): array
{
    expect($dataUrl)->toStartWith('data:image/png;base64,');

    $image = imagecreatefromstring(base64_decode(substr($dataUrl, strlen('data:image/png;base64,'))));
    $width = imagesx($image);
    $height = imagesy($image);
    $opaque = 0;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) < 64) {
                $opaque++;
            }
        }
    }

    return [$image, $width, $height, $opaque];
}

test('a School Admin uploads a photo of their signature and gets the signature back without the paper', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('signature.extract'), ['photo' => UploadFixtures::signaturePhoto()], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure(['message', 'preview']);

    [$image, $width, $height, $opaque] = decodePreview($response->json('preview'));

    // Cropped to the signature, not the whole 800x600 photo.
    expect($width)->toBeLessThan(800)
        ->and($height)->toBeLessThan(600)
        // Mostly transparent: the paper, the shadow and the desk are gone...
        ->and($opaque / ($width * $height))->toBeLessThan(0.25)
        // ...and the ink is there.
        ->and($opaque)->toBeGreaterThan(200);

    // The four corners are paper, so they are clear.
    foreach ([[0, 0], [$width - 1, 0], [0, $height - 1], [$width - 1, $height - 1]] as [$x, $y]) {
        expect((imagecolorat($image, $x, $y) >> 24) & 0x7F)->toBe(127);
    }

    // Previewing stores nothing.
    expect($this->admin->fresh()->signature)->toBeNull();
});

test('the extracted signature keeps the pen colour', function () {
    $response = $this->actingAs($this->staff, 'staff')
        ->post(route('staff.settings.signature.extract', $this->school), ['photo' => UploadFixtures::signaturePhoto()], ['Accept' => 'application/json'])
        ->assertOk();

    [$image, $width, $height] = decodePreview($response->json('preview'));

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $c = imagecolorat($image, $x, $y);

            if ((($c >> 24) & 0x7F) === 0) {
                // Blue ink stays blue.
                expect($c & 0xFF)->toBeGreaterThan(($c >> 16) & 0xFF);

                return;
            }
        }
    }

    $this->fail('No opaque ink pixel found.');
});

test('a teacher keeps the extracted signature through the same save as a drawn one', function () {
    $preview = $this->actingAs($this->staff, 'staff')
        ->post(route('staff.settings.signature.extract', $this->school), ['photo' => UploadFixtures::signaturePhoto()], ['Accept' => 'application/json'])
        ->assertOk()
        ->json('preview');

    $this->actingAs($this->staff, 'staff')
        ->postJson(route('staff.settings.signature.store', $this->school), ['signature' => $preview])
        ->assertOk();

    $signature = $this->staff->fresh()->signature;

    expect($signature)->not->toBeNull()
        ->and(Storage::disk('local')->exists($signature->path))->toBeTrue();
});

test('a photo with no signature on it is refused with advice', function () {
    $this->actingAs($this->admin)
        ->post(route('signature.extract'), ['photo' => UploadFixtures::blankPaperPhoto()], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJson(['message' => SignatureExtractor::NOT_FOUND_MESSAGE]);
});

test('something that is not an image is refused before it is processed', function () {
    $this->actingAs($this->admin)
        ->post(route('signature.extract'), [
            'photo' => UploadFixtures::file('<?php echo "hi";', 'signature.jpg', 'image/jpeg'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('photo');
});

test('a photo is required', function () {
    $this->actingAs($this->staff, 'staff')
        ->post(route('staff.settings.signature.extract', $this->school), [], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('photo');
});

test('guests cannot use either extract endpoint', function () {
    $this->post(route('staff.settings.signature.extract', $this->school), ['photo' => UploadFixtures::signaturePhoto()], ['Accept' => 'application/json'])
        ->assertUnauthorized();

    $this->post(route('signature.extract'), ['photo' => UploadFixtures::signaturePhoto()], ['Accept' => 'application/json'])
        ->assertUnauthorized();
});

test('a PNG that is already cut out is kept as it is, only cropped', function () {
    $png = SignatureExtractorFixture::transparentSignature();

    $out = app(SignatureExtractor::class)->extract($png);
    $image = imagecreatefromstring($out);

    expect(imagesx($image))->toBeLessThan(400)
        ->and((imagecolorat($image, 0, 0) >> 24) & 0x7F)->toBe(127);
});

test('the teacher upload form also removes the paper', function () {
    $this->actingAs($this->staff, 'staff')
        ->post(route('staff.settings.update-signature', $this->school), ['signature' => UploadFixtures::signaturePhoto()])
        ->assertSessionHasNoErrors();

    $stored = Storage::disk('local')->get($this->staff->fresh()->signature->path);
    $image = imagecreatefromstring($stored);

    expect((imagecolorat($image, 0, 0) >> 24) & 0x7F)->toBe(127);
});

test('both signature cards offer the photo upload beside the pad', function () {
    $this->actingAs($this->admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Upload a Photo')
        ->assertSee(basename((string) parse_url(route('signature.extract'), PHP_URL_PATH)), false);

    $this->actingAs($this->staff, 'staff')
        ->get(route('staff.settings.index', $this->school))
        ->assertOk()
        ->assertSee('Upload a Photo')
        ->assertSee('signature\\/extract', false);
});

final class SignatureExtractorFixture
{
    public static function transparentSignature(): string
    {
        $image = imagecreatetruecolor(400, 200);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagesetthickness($image, 3);
        imageline($image, 120, 120, 260, 80, imagecolorallocatealpha($image, 17, 24, 39, 0));

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
