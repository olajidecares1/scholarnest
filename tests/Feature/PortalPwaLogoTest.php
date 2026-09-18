<?php

use App\Enums\PlanKey;
use App\Enums\PortalApp;
use App\Models\BrandingImage;
use App\Models\School;
use App\Models\Setting;
use App\Models\Student;
use App\Support\PortalPwa;

/**
 * The icon on the home screen follows the AkademicNest logo.
 *
 * Not a file checked into public/, which is what it was: re-drawing the mark
 * then meant a deploy, and a phone that had already installed the app kept the
 * old icon for ever, because a launcher caches the icon it was handed at
 * install time and never asks for that URL again.
 *
 * So the icon is drawn from the logo the Super Admin has uploaded, and its URL
 * carries a fingerprint of that logo. Upload a new one and the URL changes,
 * which is the only thing a browser re-reading the manifest can notice, and
 * the only way the icon on an installed app is ever replaced.
 */
function logoSchool(): School
{
    return activateSchool(School::factory()->create(['name' => 'Greenfield College']), PlanKey::Standard)->fresh();
}

/**
 * A solid red square as the uploaded platform logo. Red so it cannot be
 * confused with either the white backing or the bundled blue artwork.
 */
function uploadPlatformLogo(string $path = 'branding/logo-first.png', array $rgb = [220, 30, 30]): void
{
    $image = imagecreatetruecolor(400, 120);
    imagefilledrectangle($image, 0, 0, 400, 120, imagecolorallocate($image, ...$rgb));

    ob_start();
    imagepng($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    BrandingImage::remember($path, $bytes);
    Setting::current()->update(['logo_path' => $path]);
}

describe('the installed icon is the AkademicNest logo, whatever it currently is', function () {
    test('the icon is drawn from the uploaded logo, not from the bundled file', function () {
        uploadPlatformLogo();

        $icon = imagecreatefromstring($this->get(route('pwa.icon', ['size' => 512]))->getContent());

        // The centre of a 400x120 logo contained in a 512 tile is the logo.
        $centre = imagecolorat($icon, 256, 256);

        expect(($centre >> 16) & 0xFF)->toBeGreaterThan(180)
            ->and(($centre >> 8) & 0xFF)->toBeLessThan(90)
            ->and($centre & 0xFF)->toBeLessThan(90);
    });

    test('a wide logo is contained, never stretched to fill the tile', function () {
        uploadPlatformLogo();

        $icon = imagecreatefromstring($this->get(route('pwa.icon', ['size' => 512]))->getContent());

        // A 400x120 logo fitted to the safe zone is about 410x123, centred, so
        // the top and bottom of the tile are backing colour and not logo. A
        // stretched one would paint red from edge to edge.
        foreach ([[256, 4], [256, 507]] as [$x, $y]) {
            $c = imagecolorat($icon, $x, $y);
            expect(($c >> 16) & 0xFF)->toBe(255)
                ->and(($c >> 8) & 0xFF)->toBe(255)
                ->and($c & 0xFF)->toBe(255);
        }
    });

    test('the icon stays square, opaque and at the size asked for', function () {
        uploadPlatformLogo();

        $response = $this->get(route('pwa.icon', ['size' => 192]));
        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        $icon = imagecreatefromstring($response->getContent());

        expect(imagesx($icon))->toBe(192)->and(imagesy($icon))->toBe(192);

        // A maskable icon with transparent corners gets them filled in by the
        // launcher, usually with black.
        foreach ([[2, 2], [189, 2], [2, 189], [189, 189]] as [$x, $y]) {
            expect((imagecolorat($icon, $x, $y) >> 24) & 0x7F)->toBe(0);
        }
    });

    test('uploading a new logo changes the icon URL, which is what replaces it on a phone', function () {
        $school = logoSchool();

        uploadPlatformLogo('branding/logo-first.png');
        $before = $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'student']))->json('icons.0.src');

        uploadPlatformLogo('branding/logo-second.png', [20, 120, 220]);
        $after = $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'student']))->json('icons.0.src');

        expect($after)->not->toBe($before);
    });

    test('and the new URL serves the new logo', function () {
        uploadPlatformLogo('branding/logo-first.png', [220, 30, 30]);
        uploadPlatformLogo('branding/logo-second.png', [20, 120, 220]);

        $icon = imagecreatefromstring($this->get(route('pwa.icon', ['size' => 512]))->getContent());
        $centre = imagecolorat($icon, 256, 256);

        expect(($centre >> 16) & 0xFF)->toBeLessThan(90)
            ->and($centre & 0xFF)->toBeGreaterThan(180);
    });

    test('the manifest is short-lived, so a browser notices a new icon within minutes', function () {
        $school = logoSchool();

        $cacheControl = $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'student']))
            ->headers->get('Cache-Control');

        expect($cacheControl)->toContain('max-age=300');
    });

    test('with nothing uploaded it still falls back to the bundled mark', function () {
        expect(Setting::platformLogoPath())->toBeNull();

        $this->get(route('pwa.icon', ['size' => 192]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    });

    test('the icon is still AkademicNest, never the school', function () {
        uploadPlatformLogo();

        $mine = logoSchool();
        $theirs = activateSchool(School::factory()->create(['name' => 'Riverside Academy']), PlanKey::Standard)->fresh();
        $theirs->update(['logo_path' => 'schools/riverside-crest.png']);

        $a = $this->get(route('pwa.manifest', ['school' => $mine, 'portal' => 'student']))->json('icons.0.src');
        $b = $this->get(route('pwa.manifest', ['school' => $theirs->fresh(), 'portal' => 'student']))->json('icons.0.src');

        expect($b)->toBe($a)->and($b)->not->toContain('riverside');
    });
});

describe('the school Portal is installable in its own right', function () {
    test('the hub has a manifest, and launching it opens that school\'s portal', function () {
        $school = logoSchool();

        $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'hub']))
            ->assertOk()
            ->assertJsonPath('start_url', route('portal.index', $school, absolute: false))
            ->assertJsonPath('name', 'Greenfield College Portal');
    });

    test('every plan gets it, because every plan has a portal', function () {
        $basic = activateSchool(School::factory()->create(['name' => 'Little Acorns']), PlanKey::Basic)->fresh();

        $this->get(route('pwa.manifest', ['school' => $basic, 'portal' => 'hub']))->assertOk();
    });

    test('two schools get two apps, never one that overwrites the other', function () {
        $mine = logoSchool();
        $theirs = activateSchool(School::factory()->create(['name' => 'Riverside Academy']), PlanKey::Standard)->fresh();

        $a = $this->get(route('pwa.manifest', ['school' => $mine, 'portal' => 'hub']))->json();
        $b = $this->get(route('pwa.manifest', ['school' => $theirs, 'portal' => 'hub']))->json();

        expect($a['id'])->not->toBe($b['id'])
            ->and($a['start_url'])->not->toBe($b['start_url']);
    });
});

describe('where the offer to install appears', function () {
    test('on the school Portal hub, which is where somebody enters their school', function () {
        $school = logoSchool();

        $this->get(route('portal.index', $school))
            ->assertOk()
            ->assertSee(route('pwa.manifest', ['school' => $school, 'portal' => 'hub'], absolute: false), false);
    });

    test('on each portal\'s own sign-in page, offering that portal', function () {
        $school = logoSchool();

        $this->get($school->portalLoginUrl('student'))
            ->assertOk()
            ->assertSee(route('pwa.manifest', ['school' => $school, 'portal' => 'student'], absolute: false), false);
    });

    test('NEVER on the registration page', function () {
        // Registration wears the same layout as the portals. Somebody signing
        // a school up has no portal yet, and being asked to install one is
        // being asked to install nothing.
        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee('rel="manifest"', false)
            ->assertDontSee('AkademicNestPwa', false);
    });

    test('the install prompt is caught in the head, where it cannot be missed', function () {
        $school = logoSchool();

        // The bundle is a deferred module, so a listener added there can be
        // too late: the browser fires beforeinstallprompt exactly once.
        $this->get(route('portal.index', $school))
            ->assertSee('AkademicNestPwaPrompt', false)
            ->assertSee('beforeinstallprompt', false);
    });

    test('and still on the dashboards, for somebody who signed in before installing', function () {
        $school = logoSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'is_active' => true]);

        $this->actingAs($student, 'student')
            ->get(route('student.dashboard', $school))
            ->assertOk()
            ->assertSee(route('pwa.manifest', ['school' => $school, 'portal' => 'student'], absolute: false), false);
    });
});

test('the fingerprint follows the logo and nothing else', function () {
    $school = logoSchool();

    uploadPlatformLogo('branding/logo-first.png');
    $first = (new PortalPwa($school->fresh(), PortalApp::Student))->iconFingerprint();

    // A different school, same logo: the icon is the platform's, so the
    // fingerprint must not move.
    $other = activateSchool(School::factory()->create(['name' => 'Riverside Academy']), PlanKey::Standard)->fresh();
    expect((new PortalPwa($other, PortalApp::Student))->iconFingerprint())->toBe($first);

    uploadPlatformLogo('branding/logo-second.png');
    expect((new PortalPwa($school->fresh(), PortalApp::Student))->iconFingerprint())->not->toBe($first);
});
