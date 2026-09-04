<?php

use App\Enums\UserRole;
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
