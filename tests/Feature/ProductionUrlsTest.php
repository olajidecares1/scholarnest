<?php

use App\Support\ProductionConfiguration;

/**
 * The addresses a production deployment answers at.
 *
 * APP_URL is not a label. Every link route() generates is built from it, every
 * link in an email, the host RedirectToCanonicalHost sends stray traffic to,
 * and the default target schools point their custom domains at.
 *
 * Get it wrong and the application still starts, serves pages happily, and
 * hands out links to somewhere unreachable, and a school following one is
 * signed out, because the session cookie belongs to the host they left. That
 * failure is silent, so it is refused at boot instead.
 *
 * The plan-specific addresses are deliberately NOT refused. Each affects one
 * tier, and taking Standard and Exclusive schools offline over a Basic
 * misconfiguration would be a worse outcome than the misconfiguration.
 * `php artisan production:urls` reports those.
 */
function productionSafeConfig(): void
{
    config([
        'app.debug' => false,
        'session.secure' => true,
        'session.encrypt' => true,
        'app.url' => 'https://akademicanest.com',
    ]);
}

describe('production refuses to serve with a wrong platform address', function () {
    test('a correct configuration is accepted', function () {
        productionSafeConfig();

        ProductionConfiguration::verify(isProduction: true, runningInConsole: false);
    })->throwsNoExceptions();

    test('a missing APP_URL is refused', function () {
        productionSafeConfig();
        config(['app.url' => '']);

        expect(fn () => ProductionConfiguration::verify(true, false))
            ->toThrow(RuntimeException::class, 'APP_URL=https://your-domain');
    });

    test('an http APP_URL is refused', function () {
        // Production forces https on every generated link, so an http APP_URL
        // disagrees with what is actually served.
        productionSafeConfig();
        config(['app.url' => 'http://akademicanest.com']);

        expect(fn () => ProductionConfiguration::verify(true, false))
            ->toThrow(RuntimeException::class, 'must use https');
    });

    test('a local address is refused, however plausible it looks', function () {
        // These are the values that survive a copied .env, which is exactly how
        // they reach production.
        foreach (['https://localhost', 'https://127.0.0.1', 'https://lvh.me', 'https://akademicnest.test'] as $url) {
            productionSafeConfig();
            config(['app.url' => $url]);

            expect(fn () => ProductionConfiguration::verify(true, false))
                ->toThrow(RuntimeException::class);
        }
    });

    test('the message names every fault at once, not just the first', function () {
        // Reporting one at a time would mean four failed deployments to learn
        // about four settings.
        config([
            'app.debug' => true,
            'session.secure' => false,
            'session.encrypt' => false,
            'app.url' => 'http://localhost',
        ]);

        try {
            ProductionConfiguration::verify(true, false);
            $this->fail('Expected the check to refuse this configuration.');
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toContain('APP_DEBUG')
                ->toContain('SESSION_SECURE_COOKIE')
                ->toContain('SESSION_ENCRYPT')
                ->toContain('APP_URL');
        }
    });

    test('nothing is refused outside production', function () {
        config(['app.url' => 'http://lvh.me', 'app.debug' => true]);

        ProductionConfiguration::verify(isProduction: false, runningInConsole: false);
    })->throwsNoExceptions();

    test('console commands are never refused, so the fix can be applied', function () {
        // If this ran in the console, a bad value cached into config:cache would
        // make `php artisan config:clear` refuse to run, locking the deployer
        // out of the one command that fixes it.
        config(['app.url' => 'http://lvh.me', 'app.debug' => true]);

        ProductionConfiguration::verify(isProduction: true, runningInConsole: true);
    })->throwsNoExceptions();
});

describe('the readiness report', function () {
    test('it fails when an address will not work', function () {
        config(['app.url' => 'http://lvh.me']);

        $this->artisan('production:urls')
            ->assertExitCode(1);
    });

    test('it passes and names every tier when everything is set', function () {
        config([
            'app.url' => 'https://akademicanest.com',
            'basic_portal.token' => str_repeat('a', 32),
            'custom_domain.tenant_base_domain' => 'akademicanest.com',
            'custom_domain.a_record_ip' => '203.0.113.10',
        ]);

        $this->artisan('production:urls')
            ->expectsOutputToContain('Platform')
            ->expectsOutputToContain('Basic plan')
            ->expectsOutputToContain('Standard plan')
            ->expectsOutputToContain('Exclusive plan')
            ->assertExitCode(0);
    });

    test('a missing Basic token is reported as fatal, because it locks a whole tier out', function () {
        config(['app.url' => 'https://akademicanest.com', 'basic_portal.token' => '']);

        $this->artisan('production:urls')
            ->expectsOutputToContain('BASIC_PORTAL_TOKEN')
            ->assertExitCode(1);
    });

    test('a token of the wrong shape is caught, not just a missing one', function () {
        // The route pattern requires exactly 32 alphanumerics. Anything else
        // 404s just as thoroughly as an empty value, but looks configured.
        config(['app.url' => 'https://akademicanest.com', 'basic_portal.token' => 'too-short']);

        $this->artisan('production:urls')->assertExitCode(1);
    });

    test('a missing subdomain base is a warning, not a failure', function () {
        // Standard schools fall back to /p/{key} paths. Not what was sold, but
        // it works, so it must not block a deploy.
        config([
            'app.url' => 'https://akademicanest.com',
            'basic_portal.token' => str_repeat('a', 32),
            'custom_domain.tenant_base_domain' => '',
        ]);

        $this->artisan('production:urls')
            ->expectsOutputToContain('TENANT_BASE_DOMAIN')
            ->assertExitCode(0);
    });
});
