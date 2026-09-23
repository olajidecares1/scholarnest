<?php

use App\Enums\PlanKey;
use App\Enums\PortalApp;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\PortalPwa;

/**
 * Installing a school portal.
 *
 * The promise is narrow and the whole feature rests on it: tap the icon and
 * you are in YOUR school's portal. Not the platform's front door, not a school
 * finder, not whichever school the browser saw last, and never a different
 * school from the one the app was installed from.
 *
 * That comes down to one field, start_url, and to every school getting its own
 * manifest with its own identity. These tests hold that.
 */
function pwaSchool(PlanKey $plan = PlanKey::Standard, string $name = 'Greenfield College'): School
{
    return activateSchool(School::factory()->create(['name' => $name]), $plan)->fresh();
}

describe('the manifest is what makes an installed app a school', function () {
    test('launching the student app opens that school, not the platform', function () {
        $school = pwaSchool();

        $manifest = $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'student']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->json();

        expect($manifest['start_url'])->toBe(route('student.dashboard', $school, absolute: false))
            ->and($manifest['start_url'])->toContain($school->portal_key)
            ->and($manifest['display'])->toBe('standalone')
            ->and($manifest['name'])->toContain('Greenfield College');
    });

    test('two schools produce two different apps, not one that overwrites the other', function () {
        $mine = pwaSchool(name: 'Greenfield College');
        $theirs = pwaSchool(name: 'Riverside Academy');

        $a = $this->get(route('pwa.manifest', ['school' => $mine, 'portal' => 'student']))->json();
        $b = $this->get(route('pwa.manifest', ['school' => $theirs, 'portal' => 'student']))->json();

        // A launcher identifies an app by its id. Same id would mean the
        // second install replaced the first, and a parent with children at two
        // schools would be left with one icon.
        expect($a['id'])->not->toBe($b['id'])
            ->and($a['start_url'])->not->toBe($b['start_url'])
            ->and($a['scope'])->not->toBe($b['scope'])
            ->and($b['start_url'])->not->toContain($mine->portal_key);
    });

    test('the four portals of one school are four apps', function () {
        $school = pwaSchool();

        $ids = collect(['student', 'staff', 'guardian', 'admin'])
            ->map(fn (string $portal) => $this->get(route('pwa.manifest', ['school' => $school, 'portal' => $portal]))->json('id'));

        expect($ids->unique())->toHaveCount(4);
    });

    test('a parent app opens the parent portal and a staff app the staff portal', function () {
        $school = pwaSchool();

        $guardian = $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'guardian']))->json();
        $staff = $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'staff']))->json();

        expect($guardian['start_url'])->toBe(route('guardian.dashboard', $school, absolute: false))
            ->and($staff['start_url'])->toBe(route('staff.dashboard', $school, absolute: false))
            ->and($guardian['start_url'])->not->toBe($staff['start_url']);
    });

    test('the admin app opens its own school login, which carries the school', function () {
        $school = pwaSchool();

        $manifest = $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'admin']))->json();

        // The School Admin dashboard is an obfuscated root path with no school
        // in it, so it cannot be an entry point: launched signed out it would
        // have nothing to say which school this app belongs to.
        expect($manifest['start_url'])->toContain($school->portal_key)
            ->and($manifest['scope'])->toBe('/');
    });

    test('every path is relative, so an install stays on the host it was made from', function () {
        $school = pwaSchool();

        $manifest = $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'student']))->json();

        // A school is reachable on the platform host, on its subdomain and on
        // its own domain. An absolute URL here would send somebody who
        // installed from one to a different one.
        foreach ([$manifest['start_url'], $manifest['scope'], $manifest['icons'][0]['src']] as $path) {
            expect($path)->toStartWith('/')
                ->and($path)->not->toContain('http://')
                ->and($path)->not->toContain('https://');
        }
    });

    test('the scope ends in a slash so it cannot claim a neighbouring path', function () {
        $school = pwaSchool();

        $manifest = $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'student']))->json();

        // "/p/k/portal" without the slash would also match "/p/k/portal-x".
        expect($manifest['scope'])->toEndWith('/');
    });

    test('it declares both sizes Android needs, each maskable as well as plain', function () {
        $school = pwaSchool();

        $icons = collect($this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'student']))->json('icons'));

        expect($icons->pluck('sizes')->unique()->values()->all())->toBe(['192x192', '512x512'])
            ->and($icons->pluck('purpose')->unique()->values()->all())->toBe(['any', 'maskable']);
    });

    test('it names itself as a related app, so the page can tell a real install from a shortcut', function () {
        $school = pwaSchool();
        $url = route('pwa.manifest', ['school' => $school, 'portal' => 'guardian']);

        $manifest = $this->get($url)->json();

        // getInstalledRelatedApps() only answers for an entry that points at
        // the manifest itself, and relative so it holds on every host.
        expect($manifest['related_applications'])->toBe([
            ['platform' => 'webapp', 'url' => route('pwa.manifest', ['school' => $school, 'portal' => 'guardian'], absolute: false)],
        ])
            ->and($manifest['related_applications'][0]['url'])->toStartWith('/')
            ->and($manifest['prefer_related_applications'])->toBeFalse();
    });
});

describe('what a school is not offered', function () {
    test('a Basic school has no student or parent app to install', function () {
        $school = pwaSchool(PlanKey::Basic);

        // Basic has no family accounts at all. An icon for one would sit on a
        // parent's phone opening the "locked" page for ever.
        $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'student']))->assertNotFound();
        $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'guardian']))->assertNotFound();
    });

    test('a Basic school still gets its staff and admin apps', function () {
        $school = pwaSchool(PlanKey::Basic);

        $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'staff']))->assertOk();
        $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'admin']))->assertOk();
    });

    test('a school with no active plan is not installable at all', function () {
        $school = School::factory()->create();

        $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'staff']))->assertNotFound();
    });

    test('a portal that does not exist is a 404, not a manifest', function () {
        $school = pwaSchool();

        $this->get(route('pwa.manifest', ['school' => $school, 'portal' => 'superadmin']))->assertNotFound();
    });
});

describe('the icon is AkademicNest, never a school', function () {
    test('is a real PNG at the size asked for', function () {
        $response = $this->get(route('pwa.icon', ['size' => 192]));

        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        $info = getimagesizefromstring($response->getContent());

        expect($info[0])->toBe(192)->and($info[1])->toBe(192);
    });

    test('the icon URL carries no school at all', function () {
        $mine = pwaSchool(name: 'Greenfield College');
        $theirs = pwaSchool(name: 'Riverside Academy');

        $a = $this->get(route('pwa.manifest', ['school' => $mine, 'portal' => 'student']))->json('icons');
        $b = $this->get(route('pwa.manifest', ['school' => $theirs, 'portal' => 'student']))->json('icons');

        // One icon, one URL, one cache entry for the whole platform. The app
        // being installed is AkademicNest's, and the icon is what says so.
        expect($a[0]['src'])->toBe($b[0]['src'])
            ->and($a[0]['src'])->not->toContain($mine->portal_key)
            ->and($a[0]['src'])->not->toContain($theirs->portal_key);
    });

    test('a school that uploaded its own logo still gets the AkademicNest icon', function () {
        $plain = pwaSchool(name: 'Greenfield College');
        $branded = pwaSchool(name: 'Riverside Academy');
        $branded->update(['logo_path' => 'schools/riverside-crest.png']);

        $a = $this->get(route('pwa.manifest', ['school' => $plain, 'portal' => 'student']))->json('icons.0.src');
        $b = $this->get(route('pwa.manifest', ['school' => $branded->fresh(), 'portal' => 'student']))->json('icons.0.src');

        // A school's crest belongs in its portal, on its website, on its ID
        // cards and in the browser tab. Not on the home screen.
        expect($b)->toBe($a)->and($b)->not->toContain('riverside');
    });

    test('the icon carries the platform artwork where that artwork is opaque', function () {
        $served = imagecreatefromstring($this->get(route('pwa.icon', ['size' => 512]))->getContent());
        $bundled = imagecreatefrompng(public_path('images/pwa-512x512.png'));

        $compared = 0;

        for ($x = 8; $x < 512; $x += 24) {
            for ($y = 8; $y < 512; $y += 24) {
                $source = imagecolorat($bundled, $x, $y);

                // Only where the artwork actually paints something. Elsewhere
                // it is transparent and the backing colour is all there is.
                if ((($source >> 24) & 0x7F) !== 0) {
                    continue;
                }

                expect(imagecolorat($served, $x, $y) & 0xFFFFFF)->toBe($source & 0xFFFFFF);
                $compared++;
            }
        }

        // Guard the guard: a sampling grid that hit nothing would pass while
        // asserting nothing at all.
        expect($compared)->toBeGreaterThan(50);
    });

    test('THE MARK IS NOT SWALLOWED BY ITS OWN BACKGROUND', function () {
        $icon = imagecreatefromstring($this->get(route('pwa.icon', ['size' => 512]))->getContent());

        // The artwork is a blue squircle with the mark PUNCHED OUT of it, the
        // mark is transparent, not white. Backing it with the brand blue, the
        // obvious thing to do, fills the mark with blue and leaves a plain blue
        // tile with no logo on it. This is that bug's test.
        $lightest = 0;
        $darkest = 255 * 3;

        for ($x = 100; $x < 412; $x += 8) {
            for ($y = 100; $y < 412; $y += 8) {
                $c = imagecolorat($icon, $x, $y);
                $sum = (($c >> 16) & 0xFF) + (($c >> 8) & 0xFF) + ($c & 0xFF);

                $lightest = max($lightest, $sum);
                $darkest = min($darkest, $sum);
            }
        }

        // A visible mark means real contrast inside the tile. A blue-on-blue
        // square would have almost none.
        expect($lightest - $darkest)->toBeGreaterThan(200);
    });

    test('the icon is fully opaque, so a circular crop shows no black corners', function () {
        $icon = imagecreatefromstring($this->get(route('pwa.icon', ['size' => 192]))->getContent());

        // The bundled art has transparent corners and a transparent mark. A
        // maskable icon may be cropped to a circle, and whatever is still
        // transparent is filled in by the launcher, usually with black.
        foreach ([[2, 2], [189, 2], [2, 189], [189, 189], [96, 96]] as [$x, $y]) {
            expect((imagecolorat($icon, $x, $y) >> 24) & 0x7F)->toBe(0);
        }
    });

    test('only the sizes a launcher asks for are served', function () {
        $this->get(route('pwa.icon', ['size' => 999]))->assertNotFound();
    });

    test('iOS gets the same mark through apple-touch-icon', function () {
        $school = pwaSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'is_active' => true]);

        $this->actingAs($student, 'student')
            ->get(route('student.dashboard', $school))
            ->assertOk()
            ->assertSee('rel="apple-touch-icon" href="'.route('pwa.icon', ['size' => 180, 'v' => (new PortalPwa($school, PortalApp::Student))->iconFingerprint()], absolute: false).'"', false);
    });
});

describe('the service worker', function () {
    test('is served from the root, so one registration covers every portal', function () {
        $this->get('/sw.js')
            ->assertOk()
            ->assertHeader('Service-Worker-Allowed', '/');
    });

    test('is never cached, or a new one could never replace it', function () {
        // Laravel reorders and adds to Cache-Control, so the directives are
        // what matters rather than the exact string.
        $cacheControl = $this->get('/sw.js')->headers->get('Cache-Control');

        expect($cacheControl)->toContain('no-store')
            ->and($cacheControl)->toContain('no-cache');
    });

    test('CACHES NO PAGE - only hashed build assets', function () {
        $script = $this->get('/sw.js')->getContent();

        // The property this whole feature depends on. These portals are
        // multi-tenant and behind a session; a cached dashboard is one that
        // can be handed to the next person to open a shared phone.
        expect($script)->toContain("startsWith('/build/')")
            ->and($script)->toContain("request.method !== 'GET'");
    });

    test('the offline page needs nothing it would have to fetch', function () {
        $page = $this->get(route('pwa.offline'))->assertOk()->getContent();

        // It is shown precisely when the network is gone, so a stylesheet or a
        // font referenced here is one that renders as unstyled text.
        expect($page)->not->toContain('@vite')
            ->and($page)->not->toContain('<link rel="stylesheet"')
            ->and($page)->toContain('offline');
    });
});

describe('the portals declare themselves installable', function () {
    test('a pupil signing in gets a manifest and the install script', function () {
        $school = pwaSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'is_active' => true]);

        $this->actingAs($student, 'student')
            ->get(route('student.dashboard', $school))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee(route('pwa.manifest', ['school' => $school, 'portal' => 'student'], absolute: false), false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('AkademicNestPwa', false);
    });

    test('the school admin dashboard offers its own app', function () {
        $school = pwaSchool();
        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('pwa.manifest', ['school' => $school, 'portal' => 'admin'], absolute: false), false);
    });

    test('a pupil is never handed another school manifest', function () {
        $school = pwaSchool(name: 'Greenfield College');
        $other = pwaSchool(name: 'Riverside Academy');
        $student = Student::factory()->create(['school_id' => $school->id, 'is_active' => true]);

        $this->actingAs($student, 'student')
            ->get(route('student.dashboard', $school))
            ->assertOk()
            ->assertDontSee($other->portal_key, false);
    });
});

test('the short name leads with the school, because that is what tells two apart', function () {
    $school = pwaSchool(name: 'Greenfield College');

    // A launcher shows roughly twelve characters. "Parent" twice would tell a
    // parent with children at two schools nothing at all.
    expect((new PortalPwa($school, PortalApp::Guardian))->shortName())->toStartWith('Greenfield');
});
