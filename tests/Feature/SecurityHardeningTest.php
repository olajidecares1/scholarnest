<?php

use App\Http\Middleware\RejectBotSubmissions;
use App\Models\School;
use App\Support\StoredUpload;
use Illuminate\Http\UploadedFile;

// ---------------------------------------------------------------------------
// Security headers
// ---------------------------------------------------------------------------

test('every response carries the headers a browser needs to defend itself', function () {
    $response = $this->get('/');

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Permissions-Policy'))->toContain('camera=()')
        ->and($response->headers->get('Content-Security-Policy'))->not->toBeNull();
});

test('the content security policy closes the doors it can actually close', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    // These four are the ones not weakened for anybody's convenience: no
    // plugin embedding, no injected <base> rewriting every relative URL, no
    // form posting a password to another origin, and no framing at all.
    expect($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("base-uri 'self'")
        ->and($csp)->toContain("form-action 'self'")
        ->and($csp)->toContain("frame-ancestors 'none'");
});

test('HSTS is not sent from a development machine', function () {
    // Sent over http from a laptop it would pin localhost to https for a year
    // in the developer's own browser, which survives clearing the cache.
    expect($this->get('/')->headers->get('Strict-Transport-Security'))->toBeNull();
});

test('the policy still permits what the application actually does', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    // Alpine evaluates its own attribute expressions, every layout has an
    // inline script that applies dark mode before first paint, brand colours
    // are inline styles, and barcodes and QR codes are data: URIs. A policy
    // that forbade any of these would break the application - and a broken
    // policy gets deleted rather than fixed.
    expect($csp)->toContain("'unsafe-eval'")
        ->and($csp)->toContain("'unsafe-inline'")
        ->and($csp)->toContain('img-src')
        ->and($csp)->toContain('data:');
});

// ---------------------------------------------------------------------------
// Bot protection
// ---------------------------------------------------------------------------

test('a filled trap field is refused on every public form', function () {
    $school = School::factory()->create();

    $forms = [
        route('register'),
        route('check-result.identify', $school),
    ];

    foreach ($forms as $url) {
        $response = $this->post($url, [RejectBotSubmissions::FIELD => 'http://spam.example']);

        // Refused before the controller ever runs, so nothing is created.
        expect($response->status())->toBeIn([302, 422]);
    }
});

test('the trap does not require a person to do anything', function () {
    // A form submitted WITHOUT the field is normal, not suspicious. Anything
    // else would break every legitimate client that does not render it.
    $school = School::factory()->create();

    $this->post(route('check-result.identify', $school), [])
        ->assertSessionHasErrors(); // its own validation, not the honeypot

    expect(session('errors')->has('form'))->toBeFalse();
});

test('the trap field is hidden from people and from screen readers', function () {
    $markup = file_get_contents(resource_path('views/components/honeypot.blade.php'));

    expect($markup)->toContain('aria-hidden="true"')
        ->and($markup)->toContain('tabindex="-1"')
        ->and($markup)->toContain('autocomplete="off"')

        // Positioned off-screen rather than display:none, which some scripts
        // specifically skip.
        ->and($markup)->toContain('left: -9999px');
});

test('the public forms actually render the trap', function () {
    foreach ([
        'auth/register',
        'reports/create',
        'check-result/show',
        'check-result/confirm',
        'portal/basic/finder',
    ] as $view) {
        expect(file_get_contents(resource_path("views/{$view}.blade.php")))
            ->toContain('<x-honeypot />');
    }
});

// ---------------------------------------------------------------------------
// Uploads
// ---------------------------------------------------------------------------

test('an SVG can never be written to disk as an SVG', function () {
    // SVG is the one image format that is also a script host, and these files
    // are served from the school's own origin. Whatever the caller validated,
    // this refuses to give one a name a browser would render.
    $svg = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
    );

    expect(StoredUpload::name($svg))->toEndWith('.bin')
        ->and(StoredUpload::name($svg))->not->toContain('.svg');
});

test('a php file disguised as an image gets a harmless name', function () {
    $disguised = UploadedFile::fake()->createWithContent('shell.php', '<?php echo shell_exec($_GET["c"]); ?>');

    $name = StoredUpload::name($disguised);

    expect($name)->not->toContain('.php')
        ->and($name)->not->toContain('.phtml')
        ->and($name)->toEndWith('.bin');
});

test('a real image keeps its own format', function () {
    expect(StoredUpload::name(UploadedFile::fake()->image('photo.jpg')))->toEndWith('.jpg');
});

// ---------------------------------------------------------------------------
// Transport
// ---------------------------------------------------------------------------

test('plain http is not forced to https outside production', function () {
    // The redirect is production-only on purpose: local development is http,
    // and redirecting there sends every developer to a dead port.
    //
    // Asserted on the destination rather than the status, because "/" has its
    // own redirect for reasons of its own - what matters is that nothing was
    // sent to an https URL.
    $location = $this->get('/')->headers->get('Location');

    expect($location === null || ! str_starts_with($location, 'https://'))->toBeTrue();
});
