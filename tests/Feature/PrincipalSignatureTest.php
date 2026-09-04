<?php

use App\Enums\IdCardHolderType;
use App\Enums\IdCardOrientation;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use App\Services\GoldSignature;
use App\Support\IdCardSample;
use App\Support\PrincipalSignature;
use App\Support\ReportCardSample;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * School Admin IS the Principal.
 *
 * There is no separate Principal account and no second signature to keep in
 * step: the School Admin's registered signature is the school's official
 * Principal signature, it is resolved from that school's own admin account,
 * and it prints in gold on every document that carries a Principal's line.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = activateSchool(School::factory()->create(['name' => 'Gold School']), PlanKey::Standard);
    $this->admin = User::factory()->create([
        'name' => 'Gregory Eze',
        'role' => UserRole::SchoolAdmin,
        'school_id' => $this->school->id,
    ]);
});

/**
 * A signature PNG on the faked disk, drawn in near-black like a real one.
 */
function storedSignature(string $path, string $hex = '#111827'): string
{
    $image = imagecreatetruecolor(200, 80);
    imagesavealpha($image, true);
    imagealphablending($image, false);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

    [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    imagesetthickness($image, 2);

    $ink = imagecolorallocate($image, (int) $r, (int) $g, (int) $b);
    imageline($image, 10, 55, 190, 25, $ink);

    // The path is written into the image, so two signatures are never the
    // same picture. This helper used to draw an identical line every time,
    // which did not matter while a document carried a URL - the path told two
    // signatures apart - and matters entirely now that the document carries
    // the image itself. Byte-identical marks would let "one school never
    // resolves another's" pass however the resolver behaved. Written rather
    // than hashed into an offset because two paths must never collide.
    imagesetthickness($image, 1);
    imagestring($image, 1, 6, 6, $path, $ink);

    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    Storage::disk('local')->put($path, $png);

    return $path;
}

/**
 * Every distinct visible colour in a stored PNG.
 *
 * @return list<string>
 */
function visibleColoursIn(string $path): array
{
    $image = imagecreatefromstring(Storage::disk('local')->get($path));
    $colours = [];

    for ($y = 0; $y < imagesy($image); $y++) {
        for ($x = 0; $x < imagesx($image); $x++) {
            $rgba = imagecolorat($image, $x, $y);

            if ((($rgba >> 24) & 0x7F) > 100) {
                continue; // Effectively transparent.
            }

            $colours[sprintf('#%02x%02x%02x', ($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF)] = true;
        }
    }

    imagedestroy($image);

    return array_keys($colours);
}

test('the School Admin\'s registered signature is the school\'s Principal signature', function () {
    $this->admin->registerSignature(storedSignature('admin-signatures/mine.png'));

    $principal = PrincipalSignature::for($this->school->fresh());

    expect($principal)->not->toBeNull()
        ->and($principal->dataUri())->not->toBeNull();
});

test('a school with no registered admin signature has no Principal signature', function () {
    // The honest placeholder: the line prints blank rather than carrying
    // somebody else's mark.
    expect(PrincipalSignature::for($this->school))->toBeNull();
});

test('one school never resolves another school\'s Principal signature', function () {
    $this->admin->registerSignature(storedSignature('admin-signatures/ours.png'));

    $otherSchool = activateSchool(School::factory()->create(['name' => 'Other School']), PlanKey::Standard);
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $otherSchool->id]);
    $otherAdmin->registerSignature(storedSignature('admin-signatures/theirs.png'));

    $ours = PrincipalSignature::for($this->school->fresh());
    $theirs = PrincipalSignature::for($otherSchool->fresh());

    expect($ours->dataUri())->not->toBe($theirs->dataUri());
});

test('a school with several admins resolves the same signature every time', function () {
    // Deterministic: the longest-standing account that has actually
    // registered one. Two admins must not produce two different cards.
    $second = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->admin->registerSignature(storedSignature('admin-signatures/first.png'));
    $second->registerSignature(storedSignature('admin-signatures/second.png'));

    // Asserted against WHICH admin, not merely that repeated calls agree:
    // "three calls returned the same thing" would also pass if the
    // resolver consistently picked the wrong one.
    $expected = app(GoldSignature::class)->dataUriFor($this->admin->fresh()->signature->path);

    $resolved = collect(range(1, 5))->map(fn () => PrincipalSignature::for($this->school->fresh())?->dataUri())->unique();

    expect($resolved->all())->toBe([$expected]);

});

test('a staff signature is never mistaken for the Principal\'s', function () {
    // Only School Admin accounts are Principals. A teacher registering a
    // signature must not become their school's signatory.
    $teacher = Staff::factory()->create(['school_id' => $this->school->id]);
    $teacher->registerSignature(storedSignature('staff-signatures/teacher.png'));

    expect(PrincipalSignature::for($this->school->fresh()))->toBeNull();
});

describe('gold', function () {
    test('the rendered signature is gold, whatever it was drawn in', function () {
        foreach (['#111827', '#1d4ed8', '#c0392b'] as $drawnIn) {
            $source = storedSignature('admin-signatures/'.md5($drawnIn).'.png', $drawnIn);
            $gold = app(GoldSignature::class)->pathFor($source);

            expect(visibleColoursIn($gold))->toBe([GoldSignature::GOLD]);
        }
    });

    test('the rendered signature is bolder than the one drawn', function () {
        $source = storedSignature('admin-signatures/thin.png');
        $gold = app(GoldSignature::class)->pathFor($source);

        $countVisible = function (string $path): int {
            $image = imagecreatefromstring(Storage::disk('local')->get($path));
            $visible = 0;

            for ($y = 0; $y < imagesy($image); $y++) {
                for ($x = 0; $x < imagesx($image); $x++) {
                    if ((((imagecolorat($image, $x, $y) >> 24) & 0x7F)) < 100) {
                        $visible++;
                    }
                }
            }

            imagedestroy($image);

            return $visible;
        };

        expect($countVisible($gold))->toBeGreaterThan($countVisible($source));
    });

    test('the gold render is cached, and a redrawn signature is not served stale', function () {
        $first = storedSignature('admin-signatures/one.png');

        $goldPath = app(GoldSignature::class)->pathFor($first);
        expect(app(GoldSignature::class)->pathFor($first))->toBe($goldPath);

        // Registering always writes a new random filename, so the cache key
        // moves with it and a stale copy cannot be served.
        $second = storedSignature('admin-signatures/two.png');
        expect(app(GoldSignature::class)->pathFor($second))->not->toBe($goldPath);
    });

    test('a missing source file yields no signature rather than an error', function () {
        expect(app(GoldSignature::class)->pathFor('admin-signatures/gone.png'))->toBeNull();
    });
});

describe('where it prints', function () {
    beforeEach(function () {
        $this->admin->registerSignature(storedSignature('admin-signatures/principal.png'));
        $this->school = $this->school->fresh();
        $this->goldPath = app(GoldSignature::class)->pathFor($this->admin->fresh()->signature->path);
        $this->goldUri = app(GoldSignature::class)->dataUriFor($this->admin->fresh()->signature->path);
    });

    /*
     * Screen and print carry the signature differently, and the assertions
     * follow that rather than papering over it.
     *
     * On screen the image is embedded, so what proves the right signature
     * arrived is its bytes - there is no longer a filename anywhere in the
     * page to look for. In print dompdf is pointed at a local file, because
     * it cannot fetch an address and a base64 copy would bloat every card, so
     * the filename is exactly what appears.
     */
    test('the report card prints it, on screen and in print alike', function () {
        $data = ReportCardSample::for($this->school);

        expect(view('school-admin.results._report-card', $data)->render())
            ->toContain($this->goldUri);

        expect(view('school-admin.results.pdf.report-card', $data)->render())
            ->toContain(basename($this->goldPath));
    });

    test('the ID card prints it, on screen and in print alike', function () {
        $card = IdCardSample::for($this->school, IdCardHolderType::Student, IdCardOrientation::Portrait, []);

        expect(view('school-admin.id-cards._card_back', ['card' => $card])->render())
            ->toContain($this->goldUri);

        // The printed face embeds it as a data URI rather than a URL, so the
        // assertion is that a signature image is there at all.
        expect(view('school-admin.id-cards.pdf._back', ['card' => $card])->render())
            ->toContain('data:image/png;base64,');
    });

    test('the name under the line falls back to the admin\'s own', function () {
        expect($this->school->principalName())->toBe('Gregory Eze');

        $this->school->update(['principal_name' => 'Mr. Gregory A. Eze']);

        expect($this->school->fresh()->principalName())->toBe('Mr. Gregory A. Eze');
    });

    test('redrawing the signature changes the documents with no further action', function () {
        $before = PrincipalSignature::for($this->school->fresh())->dataUri();

        $this->admin->registerSignature(storedSignature('admin-signatures/redrawn.png'));

        expect(PrincipalSignature::for($this->school->fresh())->dataUri())->not->toBe($before);
    });

    test('withdrawing it leaves the line blank rather than substituting one', function () {
        $this->admin->withdrawSignature();

        $html = view('school-admin.results._report-card', ReportCardSample::for($this->school->fresh()))->render();

        expect(PrincipalSignature::for($this->school->fresh()))->toBeNull()
            ->and($html)->toContain('Principal')
            ->and($html)->not->toContain('signatures/gold');
    });
});

test('the settings page offers the Principal signature pad, not an upload', function () {
    // The signature is registered against the account, never uploaded as a
    // school-level file - one authoritative source per school.
    $this->actingAs($this->admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Principal&rsquo;s Signature', false)
        ->assertSee('signaturePad(', false)
        ->assertDontSee('name="principal_signature"', false);
});

test('a School Admin cannot set a Principal signature through the settings form', function () {
    $this->actingAs($this->admin)
        ->put(route('settings.update'), [
            'name' => $this->school->name,
            'timezone' => 'Africa/Lagos',
            'principal_signature' => UploadedFile::fake()->image('sneaky.png'),
        ])
        ->assertRedirect();

    // The field is not read, so the school gains no signature from it.
    expect(PrincipalSignature::for($this->school->fresh()))->toBeNull();
});

describe('the Principal\'s block never moves', function () {
    test('the signature space is reserved on a card with no signature at all', function () {
        // A school that registers a signature later must not get cards laid
        // out differently from the ones it printed last term.
        $unsigned = view('school-admin.results._report-card', ReportCardSample::for($this->school))->render();

        $this->admin->registerSignature(storedSignature('admin-signatures/now-signed.png'));
        $signed = view('school-admin.results._report-card', ReportCardSample::for($this->school->fresh()))->render();

        foreach ([$unsigned, $signed] as $html) {
            // The block, the reserved signature box, the role label's two
            // lines and the name line are all fixed heights.
            expect(substr_count($html, 'height: 76px;'))->toBe(4)
                ->and(substr_count($html, 'height: 30px;'))->toBe(3)
                ->and(substr_count($html, 'height: 18px;'))->toBe(3)
                ->and(substr_count($html, 'height: 11px;'))->toBe(3);
        }
    });

    test('the blocks are top-aligned, so the ruled lines cannot drift apart', function () {
        // `items-end` bottom-aligned four blocks of different heights: a
        // two-line label under one and a one-line label under another put
        // their ruled lines at different heights, and the Principal's
        // signature drifted up and down depending on how long a name was.
        $html = view('school-admin.results._report-card', ReportCardSample::for($this->school))->render();

        expect($html)->toContain('grid grid-cols-4 items-start')
            ->and($html)->not->toContain('grid grid-cols-4 items-end');
    });

    test('a very long name does not push the Principal\'s line out of place', function () {
        $this->school->update(['principal_name' => str_repeat('Wilhelmina Oluwafunmilayo ', 4)]);

        $html = view('school-admin.results._report-card', ReportCardSample::for($this->school->fresh()))->render();

        // Still exactly four fixed-height blocks: the name is clipped to its
        // line rather than growing the block and shifting the rule.
        expect(substr_count($html, 'height: 76px;'))->toBe(4)
            ->and($html)->toContain('truncate');
    });

    test('the ID card reserves the same space, signed or not', function () {
        $card = IdCardSample::for($this->school, IdCardHolderType::Student, IdCardOrientation::Portrait, []);
        $unsigned = view('school-admin.id-cards._card_back', ['card' => $card])->render();

        $this->admin->registerSignature(storedSignature('admin-signatures/card.png'));
        $signedCard = IdCardSample::for($this->school->fresh(), IdCardHolderType::Student, IdCardOrientation::Portrait, []);
        $signed = view('school-admin.id-cards._card_back', ['card' => $signedCard])->render();

        foreach ([$unsigned, $signed] as $html) {
            expect($html)->toContain('height: 15px;')
                ->and($html)->toContain('Principal');
        }
    });

    test('the Principal block is on every card, in second place, always', function () {
        // Not conditional on anything. An unsigned school still has a
        // Principal's line for somebody to sign by hand.
        foreach ([
            'school-admin.results._report-card',
            'school-admin.results.pdf.report-card',
        ] as $template) {
            $html = view($template, ReportCardSample::for($this->school))->render();

            $teacherAt = strpos($html, 'Class Teacher&#039;s Signature');
            $principalAt = strpos($html, 'Principal&#039;s Signature');
            $guardianAt = strpos($html, 'Parent / Guardian&#039;s Signature');

            expect($teacherAt)->not->toBeFalse()
                ->and($principalAt)->not->toBeFalse()
                ->and($guardianAt)->not->toBeFalse()
                ->and($principalAt)->toBeGreaterThan($teacherAt)
                ->and($guardianAt)->toBeGreaterThan($principalAt);
        }
    });
});
