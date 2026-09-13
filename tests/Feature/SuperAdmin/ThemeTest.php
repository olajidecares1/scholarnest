<?php

use App\Enums\UserRole;
use App\Models\BrandingImage;
use App\Models\Setting;
use App\Models\User;
use App\Support\Favicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('super admin can view the themes page', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.themes.index'))
        ->assertStatus(200)
        ->assertSee('Color Theme');
});

test('super admin can switch the color theme and it applies platform-wide', function () {
    $this->actingAs($this->superAdmin)->put(route('super-admin.themes.update'), [
        'theme_preset' => 'emerald-green',
    ])->assertRedirect();

    expect(Setting::current()->theme_preset)->toBe('emerald-green');

    $response = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));
    $response->assertSee('--color-primary-500: #10b981;', false);
});

test('an invalid theme preset is rejected', function () {
    $this->actingAs($this->superAdmin)->put(route('super-admin.themes.update'), [
        'theme_preset' => 'not-a-real-preset',
    ])->assertSessionHasErrors('theme_preset');
});

test('super admin can upload a new logo', function () {
    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.themes.logo.update'), [
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $response->assertRedirect();

    $settings = Setting::current();
    expect($settings->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($settings->logo_path);
});

test('super admin can upload a new favicon', function () {
    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.themes.favicon.update'), [
        'favicon' => UploadedFile::fake()->image('favicon.png'),
    ]);

    $response->assertRedirect();

    $settings = Setting::current();
    expect($settings->favicon_path)->not->toBeNull();
    Storage::disk('public')->assertExists($settings->favicon_path);
});

/*
 * The favicon, end to end.
 *
 * Updating it looked broken for two independent reasons, and each needed its
 * own fix: an .ico could never pass validation, and the icon that did get
 * stored lost to the bundled default in the page head.
 */
describe('the favicon actually changes', function () {
    /*
     * These fixtures name the MIME type explicitly, and the type they name is
     * the one the SERVER would derive.
     *
     * The rule reads $file->getMimeType(). For a real upload that is finfo
     * reading the file's content - "image/vnd.microsoft.icon" for an icon,
     * "text/x-php" for PHP source whatever it has been named. Laravel's test
     * doubles skip finfo and map the extension instead, and their map has
     * .ico as "application/ico", which no real upload ever reports. Letting
     * the fixture supply the type keeps the test honest about what production
     * sees, rather than teaching the rule to accept a type that only exists
     * inside the test suite.
     */
    test('an ICO is accepted, which is the format the form offers', function () {
        // The bug. The rules read ['required', 'image', 'mimes:png,ico'], and
        // `image` admits jpg, jpeg, png, bmp, gif and webp - not ico. So every
        // .ico the form invited was rejected by the rule sitting next to the
        // one that allowed it.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), [
                'favicon' => UploadedFile::fake()->create('favicon.ico', 8, 'image/vnd.microsoft.icon'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(Setting::current()->favicon_path)->toEndWith('.ico');
    });

    test('the other spelling of an icon is accepted too', function () {
        // Which of the two names finfo reports depends on the platform's magic
        // database, so both have to be allowed or the feature works on the
        // developer's machine and not on the server.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), [
                'favicon' => UploadedFile::fake()->create('favicon.ico', 8, 'image/x-icon'),
            ])
            ->assertSessionHasNoErrors();

        expect(Setting::current()->favicon_path)->not->toBeNull();
    });

    test('a file that is not an icon is refused, whatever it is called', function () {
        // Named .png, and PHP source inside. finfo reports what it is.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), [
                'favicon' => UploadedFile::fake()->create('favicon.png', 2, 'text/x-php'),
            ])
            ->assertSessionHasErrors('favicon');

        expect(Setting::current()->favicon_path)->toBeNull();
    });

    test('the new icon is the only one the page offers', function () {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), [
                'favicon' => UploadedFile::fake()->image('favicon.png'),
            ]);

        $html = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'))->getContent();

        // One <link rel="icon">, not three. Offering several let the browser
        // choose, and it chose the bundled 32x32 - so the upload worked and
        // the tab never changed.
        expect(substr_count($html, 'rel="icon"'))->toBe(1)
            ->and($html)->toContain(Setting::current()->favicon_path);
    });

    test('replacing it removes the file it replaced', function () {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), ['favicon' => UploadedFile::fake()->image('first.png')]);

        $first = Setting::current()->favicon_path;

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), ['favicon' => UploadedFile::fake()->image('second.png')]);

        expect(Setting::current()->favicon_path)->not->toBe($first);

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists(Setting::current()->favicon_path);
    });

    test('the address changes when the icon does, so a cached one cannot linger', function () {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), ['favicon' => UploadedFile::fake()->image('favicon.png')]);

        // Browsers cache favicons hard. The stored name is random per upload
        // and the query string is derived from it, so a new icon is never a
        // request the browser thinks it already has an answer for.
        expect(Favicon::for()->href)->toContain('?v=');
    });
});

/**
 * The size limit, which was simply too small.
 *
 * 512KB was the tightest upload limit in the application - half what a SCHOOL
 * is allowed for its own favicon, a quarter of the platform logo's - and a
 * favicon legitimately exceeds it: a .ico carrying the usual 16/32/48/64/128/
 * 256px set runs to several hundred KB, and a 512px PNG passes it alone. The
 * file is stored once and served from cache, so there was nothing to buy by
 * being mean about it.
 */
describe('the favicon size limit', function () {
    test('a favicon between the old and new limits is accepted', function () {
        // 800KB. Refused outright before, which is what "it says too large
        // when it is not" actually was.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), [
                'favicon' => UploadedFile::fake()->create('favicon.ico', 800, 'image/vnd.microsoft.icon'),
            ])
            ->assertSessionHasNoErrors();

        expect(Setting::current()->favicon_path)->toEndWith('.ico');
    });

    test('a genuinely oversized file is still refused, and told why', function () {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), [
                'favicon' => UploadedFile::fake()->create('favicon.png', 3000, 'image/png'),
            ])
            ->assertSessionHasErrors('favicon');

        expect(session('errors')->first('favicon'))->toContain('larger than 2MB');
    });

    test('the form advertises the limit it actually enforces', function () {
        // The hint said 512KB while the rule said 512KB - both wrong for a
        // real favicon. They have to agree, and they have to be workable.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.themes.index'))
            ->assertOk()
            ->assertSee('Max 2MB')
            ->assertDontSee('Max 512KB');
    });

    test('an upload that never arrived is not blamed for its size', function () {
        // Laravel reports a broken upload as "failed to upload" and never
        // reaches the size rule, so the two cases stay distinguishable.
        $file = UploadedFile::fake()->create('favicon.png', 4, 'image/png');

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), [
                'favicon' => new UploadedFile($file->getPathname(), 'favicon.png', 'image/png', UPLOAD_ERR_PARTIAL, true),
            ])
            ->assertSessionHasErrors('favicon');

        expect(session('errors')->first('favicon'))->not->toContain('larger than');
    });
});

/**
 * The platform logo and favicon are served by the application, from the
 * database - not from the disk.
 *
 * In production the "public" disk is a directory Laravel Cloud does not serve
 * at /storage and wipes on every deploy. Uploads reported success and every
 * page showed a broken image, however the address was built. These tests
 * delete the disk copy after uploading, which is what a deploy does, and expect
 * the image anyway.
 */
describe('the logo and favicon survive losing the disk', function () {
    test('an uploaded logo is still served once its disk copy is gone', function () {
        $upload = UploadedFile::fake()->image('logo.png', 40, 40);
        $bytes = file_get_contents($upload->getRealPath());

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.logo.update'), ['logo' => $upload])
            ->assertSessionHasNoErrors();

        $path = Setting::current()->logo_path;
        Storage::disk('public')->delete($path);

        $url = Setting::current()->logoUrl();
        expect($url)->toBe('/branding/'.basename($path));

        $response = $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        expect($response->getContent())->toBe($bytes)
            ->and($response->headers->get('Cache-Control'))->toContain('immutable');
    });

    test('an uploaded favicon is still served once its disk copy is gone', function () {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), ['favicon' => UploadedFile::fake()->image('favicon.png', 32, 32)])
            ->assertSessionHasNoErrors();

        $path = Setting::current()->favicon_path;
        Storage::disk('public')->delete($path);

        $href = Favicon::for()->href;
        expect($href)->toStartWith('/branding/'.basename($path).'?v=');

        $this->get($href)->assertOk()->assertHeader('Content-Type', 'image/png');
    });

    test('pages point at the served address, never at /storage', function () {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.logo.update'), ['logo' => UploadedFile::fake()->image('logo.png')]);
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.favicon.update'), ['favicon' => UploadedFile::fake()->image('favicon.png')]);

        $logo = '/branding/'.basename(Setting::current()->logo_path);
        $favicon = '/branding/'.basename(Setting::current()->favicon_path);

        $staffPages = [route('super-admin.dashboard'), route('super-admin.themes.index')];

        foreach ($staffPages as $url) {
            $this->actingAs($this->superAdmin)->get($url)
                ->assertOk()
                ->assertSee($logo, false)
                ->assertSee($favicon, false)
                ->assertDontSee('/storage/branding/', false);
        }

        auth()->logout();

        foreach ([route('portal.find.show'), route('register')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee($logo, false)
                ->assertSee($favicon, false)
                ->assertDontSee('/storage/branding/', false);
        }
    });

    test('replacing the logo removes the old copy from the database as well', function () {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.logo.update'), ['logo' => UploadedFile::fake()->image('first.png')]);
        $first = Setting::current()->logo_path;

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.themes.logo.update'), ['logo' => UploadedFile::fake()->image('second.png')]);

        expect(BrandingImage::where('path', $first)->exists())->toBeFalse()
            ->and(BrandingImage::where('path', Setting::current()->logo_path)->exists())->toBeTrue();

        $this->get('/branding/'.basename($first))->assertNotFound();
    });

    test('a logo uploaded before the database copy existed still loads from the disk', function () {
        // A laptop, or a server where the file survived: nothing to re-upload.
        Storage::disk('public')->put('branding/logo-legacy.png', 'legacy-bytes');
        Setting::current()->update(['logo_path' => 'branding/logo-legacy.png']);

        expect($this->get('/branding/logo-legacy.png')->assertOk()->getContent())->toBe('legacy-bytes');
    });

    test('nothing but a stored image is ever served', function () {
        $this->get('/branding/logo-missing.png')->assertNotFound();

        // Not an image type, so not served - even if something put it there.
        Storage::disk('public')->put('branding/page.html', '<script>alert(1)</script>');
        $this->get('/branding/page.html')->assertNotFound();

        // One path segment only: nothing outside branding/ can be named.
        $this->get('/branding/..%2F..%2F.env')->assertNotFound();
    });

    test('with no logo uploaded the bundled mark stands in', function () {
        expect(Setting::current()->logoUrl())->toBe(asset('images/logo-icon-dark.png'))
            ->and(Setting::current()->logoUrl('images/logo-mark.png'))->toBe(asset('images/logo-mark.png'));
    });

    test('the logo form no longer offers SVG, which is refused', function () {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.themes.index'))
            ->assertSee('PNG, JPG or WebP. Max 10MB.')
            ->assertDontSee('accept=".png,.jpg,.jpeg,.svg"', false);
    });
});
