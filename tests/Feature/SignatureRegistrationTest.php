<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Signature;
use App\Models\Staff;
use App\Models\User;
use App\Services\SignatureImage;
use App\Support\PrincipalSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Registering a handwritten signature drawn on a canvas.
 *
 * The rule this file exists to hold down: a signature belongs exclusively to
 * the person who drew it, and no request, form field, query string, route
 * parameter or JSON key, can make it belong to anybody else.
 *
 * That is enforced in three places, and each is tested here:
 *
 *   1. The controller reads the signer from the session and never from the
 *      request, so there is nothing to tamper with.
 *   2. The database is unique on (owner_type, owner_id), so one signer has
 *      one signature and a second cannot be written.
 *   3. The image is re-encoded from its pixels, so what lands on disk is a
 *      PNG we wrote rather than bytes a browser sent.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = activateSchool(School::factory()->create(['name' => 'Pad School']), PlanKey::Basic);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    $this->staff = Staff::factory()->create(['school_id' => $this->school->id]);
});

/**
 * A real, tiny PNG as a data URL, what the canvas posts.
 */
function drawnSignature(int $width = 240, int $height = 90): string
{
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imageline($image, 10, 60, $width - 10, 30, imagecolorallocate($image, 17, 24, 39));

    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    return 'data:image/png;base64,'.base64_encode($png);
}

test('a teacher registers a signature drawn on the pad', function () {
    $this->actingAs($this->staff, 'staff')
        ->postJson(route('staff.settings.signature.store', $this->school), ['signature' => drawnSignature()])
        ->assertOk()
        ->assertJsonStructure(['message', 'signature_url']);

    $signature = $this->staff->fresh()->signature;

    expect($signature)->not->toBeNull()
        ->and($signature->owner_type)->toBe(Staff::class)
        ->and($signature->owner_id)->toBe($this->staff->id)
        ->and($signature->school_id)->toBe($this->school->id)
        ->and(Storage::disk('local')->exists($signature->path))->toBeTrue();
});

test('a School Admin registers a signature drawn on the pad', function () {
    $this->actingAs($this->admin)
        ->postJson(route('signature.store'), ['signature' => drawnSignature()])
        ->assertOk();

    $signature = $this->admin->fresh()->signature;

    expect($signature)->not->toBeNull()
        ->and($signature->owner_type)->toBe(User::class)
        ->and($signature->owner_id)->toBe($this->admin->id);
});

test('registering again replaces the signature rather than adding one', function () {
    $this->actingAs($this->staff, 'staff');

    $this->postJson(route('staff.settings.signature.store', $this->school), ['signature' => drawnSignature()])->assertOk();
    $first = $this->staff->fresh()->signature->path;

    $this->postJson(route('staff.settings.signature.store', $this->school), ['signature' => drawnSignature(300, 120)])->assertOk();
    $second = $this->staff->fresh()->signature->path;

    expect($second)->not->toBe($first)
        ->and(Signature::where('owner_type', Staff::class)->where('owner_id', $this->staff->id)->count())->toBe(1)
        // The replaced file is deleted, not orphaned: a signature nobody can
        // reach is still a signature sitting on disk.
        ->and(Storage::disk('local')->exists($first))->toBeFalse()
        ->and(Storage::disk('local')->exists($second))->toBeTrue();
});

test('a signer id in the request is not read, so it cannot redirect the signature', function () {
    $colleague = Staff::factory()->create(['school_id' => $this->school->id]);
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    // Every name a tamperer might reach for, all at once. None is consulted.
    $this->actingAs($this->staff, 'staff')
        ->postJson(route('staff.settings.signature.store', $this->school), [
            'signature' => drawnSignature(),
            'staff_id' => $colleague->id,
            'owner_id' => $colleague->id,
            'user_id' => $otherAdmin->id,
            'id' => $colleague->id,
            'uuid' => $colleague->uuid,
            'owner_type' => User::class,
            'school_id' => 99999,
        ])
        ->assertOk();

    expect($this->staff->fresh()->signature)->not->toBeNull()
        ->and($colleague->fresh()->signature)->toBeNull()
        ->and($otherAdmin->fresh()->signature)->toBeNull()
        ->and($this->staff->fresh()->signature->owner_type)->toBe(Staff::class)
        ->and($this->staff->fresh()->signature->school_id)->toBe($this->school->id);
});

test('one teacher\'s signature never becomes another\'s', function () {
    $colleague = Staff::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->staff, 'staff')
        ->postJson(route('staff.settings.signature.store', $this->school), ['signature' => drawnSignature()])
        ->assertOk();

    auth('staff')->logout();

    $this->actingAs($colleague, 'staff')
        ->postJson(route('staff.settings.signature.store', $this->school), ['signature' => drawnSignature(280, 100)])
        ->assertOk();

    expect($this->staff->fresh()->signature->path)
        ->not->toBe($colleague->fresh()->signature->path);
});

test('a teacher from another school cannot reach this school\'s route', function () {
    $otherSchool = activateSchool(School::factory()->create(), PlanKey::Basic);
    $stranger = Staff::factory()->create(['school_id' => $otherSchool->id]);

    // 404 rather than 403: the tenant scope refuses to acknowledge a
    // school this teacher has no business knowing about.
    $this->actingAs($stranger, 'staff')
        ->postJson(route('staff.settings.signature.store', $this->school), ['signature' => drawnSignature()])
        ->assertNotFound();

    expect($stranger->fresh()->signature)->toBeNull();
});

test('signing out means signing nothing', function () {
    $this->postJson(route('staff.settings.signature.store', $this->school), ['signature' => drawnSignature()])
        ->assertUnauthorized();

    $this->postJson(route('signature.store'), ['signature' => drawnSignature()])
        ->assertUnauthorized();
});

test('a teacher cannot register through the School Admin route', function () {
    // Signed in on the staff guard, refused by the School Admin gate.
    $this->actingAs($this->staff, 'staff')
        ->postJson(route('signature.store'), ['signature' => drawnSignature()])
        ->assertForbidden();

    expect($this->staff->fresh()->signature)->toBeNull();
});

test('a signer withdraws their own signature', function () {
    $this->actingAs($this->staff, 'staff');
    $this->postJson(route('staff.settings.signature.store', $this->school), ['signature' => drawnSignature()])->assertOk();
    $path = $this->staff->fresh()->signature->path;

    $this->deleteJson(route('staff.settings.signature.destroy', $this->school))->assertOk();

    expect($this->staff->fresh()->signature)->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});

/**
 * These replace an earlier set that tested an "also use this as my school's
 * principal signature" tick. That tick is gone: School Admin IS the Principal,
 * so registering a signature makes it the school's, with nothing to opt into
 * and nothing that can drift out of step.
 */
test('a School Admin\'s registration IS the school\'s Principal signature', function () {
    $this->actingAs($this->admin)
        ->postJson(route('signature.store'), ['signature' => drawnSignature()])
        ->assertOk();

    $principal = PrincipalSignature::for($this->school->fresh());

    expect($principal)->not->toBeNull()
        ->and($principal->dataUri())->not->toBeNull();
});

test('redrawing changes what the school\'s documents show, with no further action', function () {
    $this->actingAs($this->admin);
    $this->postJson(route('signature.store'), ['signature' => drawnSignature()])->assertOk();

    $before = PrincipalSignature::for($this->school->fresh())->dataUri();

    $this->postJson(route('signature.store'), ['signature' => drawnSignature(300, 110)])->assertOk();

    expect(PrincipalSignature::for($this->school->fresh())->dataUri())->not->toBe($before);
});

test('withdrawing takes it off the school\'s documents', function () {
    $this->actingAs($this->admin);
    $this->postJson(route('signature.store'), ['signature' => drawnSignature()])->assertOk();

    $this->deleteJson(route('signature.destroy'))->assertOk();

    // Nothing is substituted: the Principal's line prints blank, which is the
    // honest placeholder.
    expect(PrincipalSignature::for($this->school->fresh()))->toBeNull();
});

test('a teacher registering a signature does not become their school\'s Principal', function () {
    $this->actingAs($this->staff, 'staff')
        ->postJson(route('staff.settings.signature.store', $this->school), ['signature' => drawnSignature()])
        ->assertOk();

    expect(PrincipalSignature::for($this->school->fresh()))->toBeNull();
});

test('the pad is offered on every plan, to teachers and admins alike', function () {
    foreach ([PlanKey::Basic, PlanKey::Standard, PlanKey::Exclusive] as $plan) {
        $school = activateSchool(School::factory()->create(), $plan);
        $staff = Staff::factory()->create(['school_id' => $school->id]);
        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

        $this->actingAs($staff, 'staff')
            ->get(route('staff.settings.index', $school))
            ->assertOk()
            ->assertSee('Register Your Signature')
            ->assertSee('signaturePad(', false);
        auth('staff')->logout();

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Register Your Signature')
            ->assertSee('signaturePad(', false);
        auth()->logout();
    }
});

describe('what the pad is allowed to post', function () {
    beforeEach(fn () => $this->actingAs($this->staff, 'staff'));

    test('a JPEG wearing a PNG label is refused', function () {
        $image = imagecreatetruecolor(40, 40);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        $this->postJson(route('staff.settings.signature.store', $this->school), [
            'signature' => 'data:image/png;base64,'.base64_encode($jpeg),
        ])->assertStatus(422);

        expect($this->staff->fresh()->signature)->toBeNull();
    });

    test('a script wearing a PNG label is refused', function () {
        $this->postJson(route('staff.settings.signature.store', $this->school), [
            'signature' => 'data:image/png;base64,'.base64_encode('<?php echo "not a signature"; ?>'),
        ])->assertStatus(422);

        expect($this->staff->fresh()->signature)->toBeNull();
    });

    test('an SVG is refused, label and all', function () {
        // The one image format that is also a script host, and these files are
        // served from the school's own origin.
        $this->postJson(route('staff.settings.signature.store', $this->school), [
            'signature' => 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])->assertStatus(422);

        expect($this->staff->fresh()->signature)->toBeNull();
    });

    test('an oversized payload is refused before it is decoded', function () {
        $this->postJson(route('staff.settings.signature.store', $this->school), [
            'signature' => 'data:image/png;base64,'.str_repeat('A', 3_000_000),
        ])->assertStatus(422);

        expect($this->staff->fresh()->signature)->toBeNull();
    });

    test('an empty canvas is refused', function () {
        $this->postJson(route('staff.settings.signature.store', $this->school), ['signature' => ''])
            ->assertStatus(422);
    });
});

test('what lands on disk is a PNG we wrote, not the bytes that were posted', function () {
    // Re-encoded from its pixels, so a polyglot that is a valid PNG *and*
    // something else keeps only the pixels.
    $posted = drawnSignature();
    $stored = app(SignatureImage::class)->decode($posted);

    expect($stored)->not->toBe(base64_decode(Str::after($posted, 'base64,')))
        ->and(substr($stored, 0, 8))->toBe("\x89PNG\r\n\x1a\n");

    $size = getimagesizefromstring($stored);

    expect($size[2])->toBe(IMAGETYPE_PNG);
});

test('a rolled-back registration does not destroy the signature already there', function () {
    // A filesystem has no rollback. Deleting the replaced file inline meant a
    // transaction that later rolled back left the row pointing at the old
    // path and the old FILE already gone, a signature that exists everywhere
    // except on disk, which renders as nothing at all.
    //
    // This is not hypothetical: it is how a real school's Principal signature
    // was lost, by a preview script that wrapped its work in a transaction.
    Storage::disk('local')->put('admin-signatures/live.png', 'the real one');
    $this->admin->registerSignature('admin-signatures/live.png');

    DB::beginTransaction();
    Storage::disk('local')->put('admin-signatures/temporary.png', 'a preview');
    $this->admin->registerSignature('admin-signatures/temporary.png');
    DB::rollBack();

    $admin = $this->admin->fresh();

    expect($admin->signature->path)->toBe('admin-signatures/live.png')
        ->and(Storage::disk('local')->exists('admin-signatures/live.png'))->toBeTrue()
        ->and($admin->signatureDataUri())->not->toBeNull();
});

test('a committed registration still clears the file it replaced', function () {
    // Deferring the delete must not become never deleting it.
    Storage::disk('local')->put('admin-signatures/first.png', 'one');
    $this->admin->registerSignature('admin-signatures/first.png');

    DB::transaction(function () {
        Storage::disk('local')->put('admin-signatures/second.png', 'two');
        $this->admin->registerSignature('admin-signatures/second.png');
    });

    expect(Storage::disk('local')->exists('admin-signatures/first.png'))->toBeFalse()
        ->and(Storage::disk('local')->exists('admin-signatures/second.png'))->toBeTrue()
        ->and($this->admin->fresh()->signature->path)->toBe('admin-signatures/second.png');
});
