<?php

namespace App\Support;

use RuntimeException;

/**
 * Settings that are fine on a laptop and dangerous on a server.
 *
 * Three values decide whether a production deployment leaks: APP_DEBUG prints
 * stack traces containing database credentials to whoever triggered the error,
 * and the two session settings decide whether the cookie that IS the session
 * travels in clear over http and sits readable in the session store.
 *
 * Until now nothing recorded that they had to change at deployment, so the
 * person deploying had to already know. Documentation alone has the same
 * weakness - it only helps the person who reads it - so this refuses to serve
 * a request instead. The failure is loud, immediate and names the fix, which
 * is the opposite of the alternative: an application that comes up looking
 * perfectly healthy and quietly hands out credentials in its error pages.
 *
 * Every fault is reported at once. Reporting the first would mean three failed
 * deployments to learn about three settings.
 *
 * Console commands are exempt on purpose. If the check ran there too, a
 * misconfiguration cached into config:cache would make `php artisan
 * config:clear` refuse to run - locking the deployer out of the one command
 * that fixes it. HTTP is where the exposure is, and HTTP is what is guarded.
 *
 * The two facts about the environment are passed in rather than read from the
 * container, because the second of them is always true under a test runner:
 * a check that asked for itself could not be tested at all.
 */
class ProductionConfiguration
{
    /**
     * @throws RuntimeException when production is configured unsafely.
     */
    public static function verify(bool $isProduction, bool $runningInConsole): void
    {
        if (! $isProduction || $runningInConsole) {
            return;
        }

        $problems = [];

        if (config('app.debug')) {
            $problems[] = 'APP_DEBUG=false (debug pages print database credentials to visitors)';
        }

        if (! config('session.secure')) {
            $problems[] = 'SESSION_SECURE_COOKIE=true (otherwise the session cookie is sent over plain http)';
        }

        if (! config('session.encrypt')) {
            $problems[] = 'SESSION_ENCRYPT=true (otherwise session payloads are readable wherever they are stored)';
        }

        if ($problems === []) {
            return;
        }

        throw new RuntimeException(
            "Refusing to serve requests: this production environment is configured unsafely.\n\n"
            ."Set the following in .env, then run `php artisan config:clear`:\n  - "
            .implode("\n  - ", $problems)
            ."\n\nSee docs/PRODUCTION.md. Console commands are unaffected, so this can be fixed in place."
        );
    }
}
