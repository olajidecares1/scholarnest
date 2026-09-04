<?php

use App\Support\ProductionConfiguration;

/**
 * The safe production baseline, which individual tests then break one value of.
 *
 * @param  array<string, mixed>  $overrides
 */
function withProductionConfig(array $overrides = []): void
{
    config([
        'app.debug' => false,
        'session.secure' => true,
        'session.encrypt' => true,

        // A real production address. The baseline used to leave this at the
        // test default of http://localhost, which was fine while the check
        // looked only at debug and session settings - and stopped being fine
        // the moment APP_URL joined them, because a platform whose every
        // generated link points at localhost is not correctly configured.
        'app.url' => 'https://scholarnest.com.ng',

        ...$overrides,
    ]);
}

/**
 * Serving HTTP in production: the only case the check acts on.
 */
function verifyProductionRequest(): void
{
    ProductionConfiguration::verify(isProduction: true, runningInConsole: false);
}

test('a correctly configured production environment serves requests', function () {
    withProductionConfig();

    verifyProductionRequest();
})->throwsNoExceptions();

test('production refuses to serve requests with APP_DEBUG on', function () {
    // The whole finding in one line: a debug page prints database credentials
    // to whoever managed to trigger the error.
    withProductionConfig(['app.debug' => true]);

    expect(fn () => verifyProductionRequest())
        ->toThrow(RuntimeException::class, 'APP_DEBUG=false');
});

test('production refuses to serve requests with an insecure session cookie', function () {
    withProductionConfig(['session.secure' => false]);

    expect(fn () => verifyProductionRequest())
        ->toThrow(RuntimeException::class, 'SESSION_SECURE_COOKIE=true');
});

test('production refuses to serve requests with unencrypted sessions', function () {
    withProductionConfig(['session.encrypt' => false]);

    expect(fn () => verifyProductionRequest())
        ->toThrow(RuntimeException::class, 'SESSION_ENCRYPT=true');
});

test('every problem is reported at once, not one deployment at a time', function () {
    withProductionConfig([
        'app.debug' => true,
        'session.secure' => false,
        'session.encrypt' => false,
    ]);

    try {
        verifyProductionRequest();
        $this->fail('Expected the unsafe configuration to be refused.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->toContain('APP_DEBUG=false')
            ->toContain('SESSION_SECURE_COOKIE=true')
            ->toContain('SESSION_ENCRYPT=true');
    }
});

test('console commands are exempt, so a bad cached config can still be cleared', function () {
    // Without this exemption a misconfiguration baked into config:cache would
    // stop `php artisan config:clear` - the one command that fixes it - from
    // running at all.
    withProductionConfig([
        'app.debug' => true,
        'session.secure' => false,
        'session.encrypt' => false,
    ]);

    ProductionConfiguration::verify(isProduction: true, runningInConsole: true);
})->throwsNoExceptions();

test('the check does nothing outside production', function () {
    // Local development runs with all three of these off, which is correct
    // there and must stay silent.
    withProductionConfig([
        'app.debug' => true,
        'session.secure' => false,
        'session.encrypt' => false,
    ]);

    ProductionConfiguration::verify(isProduction: false, runningInConsole: false);
})->throwsNoExceptions();
