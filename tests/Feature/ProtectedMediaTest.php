<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Signature;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Photographs of people, and signatures, are not public files.
 *
 * They used to be. /storage/students/<uuid>.jpg had no access control at all -
 * the name was unguessable, and that was the entire protection. Anyone who ever
 * obtained an address could fetch that child's photograph for ever, from any
 * network, signed in or not.
 *
 * The two now differ on purpose:
 *
 *   PHOTOGRAPHS get a short-lived signed URL. Two pages that legitimately show
 *   one have no session - a parent opening a result with a token, and somebody
 *   scanning the QR on a printed ID card - so the authority has to travel in
 *   the link rather than in a cookie.
 *
 *   SIGNATURES get no URL at all. They are a few kilobytes, appear once per
 *   document, and are embedded inline - so there is no address to leak and
 *   nothing to expire.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

describe('photographs are no longer public files', function () {
    test('an uploaded photograph lands on the private disk, not the public one', function () {
        Storage::fake('local');
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('students.store'), [
                'admission_number' => 'ADM-001',
                'first_name' => 'Chidi',
                'last_name' => 'Okeke',
                'gender' => 'male',
                'class_name' => 'JSS 1',
                'photo' => UploadedFile::fake()->image('pupil.jpg', 400, 400),
            ])
            ->assertSessionHasNoErrors();

        $student = Student::where('school_id', $this->school->id)->firstOrFail();

        expect($student->photo_path)->not->toBeNull();

        Storage::disk('local')->assertExists($student->photo_path);
        Storage::disk('public')->assertMissing($student->photo_path);
    });

    test('the old public address no longer serves anything', function () {
        // The whole point. /storage/students/... was the exposure.
        //
        // Asserting "not a success" rather than a specific code: the app
        // answers a bare /storage path with a 403 and the web server would
        // answer with a 404, and which one arrives is not what matters. What
        // matters is that no photograph comes back.
        $status = $this->get('/storage/students/whatever.jpg')->getStatusCode();

        expect($status)->toBeGreaterThanOrEqual(400);
    });
});

describe('the signed URL is the access control', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->student = Student::factory()->create([
            'school_id' => $this->school->id,
            'photo_path' => 'students/pupil.jpg',
        ]);

        Storage::disk('local')->put('students/pupil.jpg', 'not-really-a-jpeg');
    });

    test('a properly signed link serves the photograph', function () {
        $this->get($this->student->photoUrl())->assertOk();
    });

    test('the same path without a signature is refused', function () {
        // Guessing the address is no longer enough, which it always was before.
        $this->get(route('media.photo', ['subject' => 'student', 'uuid' => $this->student->uuid]))
            ->assertForbidden();
    });

    test('an altered signature is refused', function () {
        $tampered = $this->student->photoUrl().'x';

        $this->get($tampered)->assertForbidden();
    });

    test('the link expires', function () {
        $url = $this->student->photoUrl();

        $this->travel(Student::PHOTO_URL_MINUTES + 1)->minutes();

        // An address captured from a screenshot or a referrer header is
        // worthless by the time anybody tries it.
        $this->get($url)->assertForbidden();
    });

    test('a signature for one pupil does not serve another', function () {
        $other = Student::factory()->create([
            'school_id' => $this->school->id,
            'photo_path' => 'students/other.jpg',
        ]);

        $url = $this->student->photoUrl();
        $swapped = str_replace($this->student->uuid, $other->uuid, $url);

        expect($swapped)->not->toBe($url);

        $this->get($swapped)->assertForbidden();
    });

    test('it works with no session, which is why it is signed rather than gated', function () {
        // A parent reading a result with a token has never signed in.
        $this->assertGuest();

        $this->get($this->student->photoUrl())->assertOk();
    });

    test('nothing is served when the row has no photograph', function () {
        $bare = Student::factory()->create(['school_id' => $this->school->id, 'photo_path' => null]);

        expect($bare->photoUrl())->toBeNull();
    });

    test('every kind of person is covered, not just pupils', function () {
        $staff = Staff::factory()->create(['school_id' => $this->school->id, 'photo_path' => 'staff/x.jpg']);
        $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'photo_path' => 'guardians/x.jpg']);

        Storage::disk('local')->put('staff/x.jpg', 'x');
        Storage::disk('local')->put('guardians/x.jpg', 'x');

        $this->get($staff->photoUrl())->assertOk();
        $this->get($guardian->photoUrl())->assertOk();
    });
});

describe('signatures have no URL at all', function () {
    test('they are embedded inline', function () {
        Storage::fake('local');
        Storage::disk('local')->put('admin-signatures/sig.png', 'png-bytes');

        $this->admin->registerSignature('admin-signatures/sig.png');

        $uri = $this->admin->fresh()->signatureDataUri();

        expect($uri)->toStartWith('data:image/png;base64,')
            ->and(base64_decode(substr($uri, strlen('data:image/png;base64,'))))->toBe('png-bytes');
    });

    test('a signature that does not exist yields nothing rather than a broken image', function () {
        expect($this->admin->signatureDataUri())->toBeNull();
    });

    test('the model no longer offers a url at all', function () {
        // Not 'the URL is protected' - there is no method to call. A signature
        // on a document is a claim that a named person signed it, and a
        // downloadable copy is what lets somebody else make that claim.
        expect(method_exists(Signature::class, 'url'))->toBeFalse();
    });
});

describe('deleting a school deletes its files', function () {
    test('photographs, signatures and receipts all go with it', function () {
        Storage::fake('local');
        Storage::fake('public');

        $student = Student::factory()->create(['school_id' => $this->school->id, 'photo_path' => 'students/a.jpg']);
        $staff = Staff::factory()->create(['school_id' => $this->school->id, 'photo_path' => 'staff/b.jpg']);

        $staff->registerSignature('staff-signatures/c.png');

        $this->school->update(['logo_path' => 'school-logos/d.png']);

        $receipt = "receipts/{$this->school->id}/e.pdf";

        Storage::disk('local')->put('students/a.jpg', 'a');
        Storage::disk('local')->put('staff/b.jpg', 'b');
        Storage::disk('local')->put('staff-signatures/c.png', 'c');
        Storage::disk('local')->put($receipt, 'e');
        Storage::disk('public')->put('school-logos/d.png', 'd');

        $this->school->delete();

        // 'Permanently deleted' used to be untrue of the half that mattered
        // most: the database cascaded and every file stayed exactly where it
        // was, the public ones still served at their old addresses.
        Storage::disk('local')->assertMissing('students/a.jpg');
        Storage::disk('local')->assertMissing('staff/b.jpg');
        Storage::disk('local')->assertMissing('staff-signatures/c.png');
        Storage::disk('local')->assertMissing($receipt);
        Storage::disk('public')->assertMissing('school-logos/d.png');
    });

    test('another school\'s files are untouched', function () {
        Storage::fake('local');

        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        Student::factory()->create(['school_id' => $other->id, 'photo_path' => 'students/theirs.jpg']);
        Student::factory()->create(['school_id' => $this->school->id, 'photo_path' => 'students/ours.jpg']);

        Storage::disk('local')->put('students/theirs.jpg', 'theirs');
        Storage::disk('local')->put('students/ours.jpg', 'ours');

        $this->school->delete();

        Storage::disk('local')->assertMissing('students/ours.jpg');
        Storage::disk('local')->assertExists('students/theirs.jpg');
    });
});

describe('every write path lands in private storage, not just the ones that moved', function () {
    /*
     * Moving the existing files and changing where documents READ from is only
     * half of it. A single upload path still writing to the public disk puts
     * the exposure straight back, one file at a time, and nothing about the
     * page would look wrong - which is exactly what had happened to the
     * teacher's own signature upload.
     */
    test("a teacher's uploaded signature is private, like a drawn one", function () {
        Storage::fake('local');
        Storage::fake('public');

        $staff = Staff::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($staff, 'staff')
            ->post(route('staff.settings.update-signature', $this->school), [
                'signature' => UploadedFile::fake()->image('signature.png', 300, 120),
            ])
            ->assertSessionHasNoErrors();

        $path = $staff->fresh()->signature?->path;

        expect($path)->not->toBeNull();

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    });

    test('a drawn signature is private too', function () {
        Storage::fake('local');
        Storage::fake('public');

        // What the signature pad posts: a PNG data URL. Drawn here rather
        // than pasted as a literal because the endpoint refuses anything
        // under 8px a side, and a fixture that only passes by accident
        // teaches the next person the wrong shape.
        $canvas = imagecreatetruecolor(120, 40);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, false);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imageline($canvas, 5, 30, 115, 10, imagecolorallocate($canvas, 17, 24, 39));

        ob_start();
        imagepng($canvas);
        $drawn = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
        imagedestroy($canvas);

        $staff = Staff::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($staff, 'staff')
            ->post(route('staff.settings.signature.store', $this->school), ['signature' => $drawn])
            ->assertOk();

        $path = $staff->fresh()->signature?->path;

        expect($path)->not->toBeNull();

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    });
});

test('a photograph is served with a type the browser may not second-guess', function () {
    Storage::fake('local');

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'photo_path' => 'students/pupil.jpg',
    ]);

    Storage::disk('local')->put('students/pupil.jpg', 'not-really-a-jpeg');

    // These are files somebody uploaded, served inline from this origin. If a
    // browser is allowed to sniff the bytes, a file that got past the image
    // check but reads as HTML runs as a page here, with this origin's cookies.
    $this->get($student->photoUrl())
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'max-age=1800, private');
});
