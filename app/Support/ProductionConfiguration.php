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

        foreach (self::appUrlProblems() as $problem) {
            $problems[] = $problem;
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

    /**
     * Everything wrong with APP_URL, which is not merely cosmetic here.
     *
     * APP_URL is the address this platform answers at, and four separate things
     * are built from it: every link route() generates, every link in an email,
     * the host RedirectToCanonicalHost sends stray traffic to, and the default
     * target schools are told to point their custom domains at.
     *
     * Get it wrong and the application still starts. It serves pages happily
     * while every link it hands out goes somewhere unreachable, and a school
     * following one is signed out because the session cookie belongs to the
     * host they left. That failure is silent, which is why it is checked here
     * rather than left to be noticed.
     *
     * The plan-specific addresses - the Basic portal token, the Standard
     * subdomain base, the Exclusive DNS target - are NOT checked here on
     * purpose. Each affects one tier, and refusing every request over a Basic
     * misconfiguration would take Standard and Exclusive schools down with it.
     * `php artisan production:urls` reports those instead.
     *
     * @return list<string>
     */
    private static function appUrlProblems(): array
    {
        $appUrl = (string) config('app.url');
        $host = parse_url($appUrl, PHP_URL_HOST);

        if (! $host) {
            return ['APP_URL=https://your-domain (it is missing or unparseable, and every link the platform generates is built from it)'];
        }

        $problems = [];

        if (parse_url($appUrl, PHP_URL_SCHEME) !== 'https') {
            $problems[] = 'APP_URL must use https (production forces https on every generated link, so an http APP_URL disagrees with what is actually served)';
        }

        // A hostname nobody outside this machine can resolve. Local values are
        // the ones that survive a copied .env, which is exactly how they reach
        // production.
        if ($host === 'localhost' || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            $problems[] = "APP_URL points at \"{$host}\", which nothing outside this machine can reach - set it to the domain the platform is served at";
        }

        if (str_ends_with($host, '.test') || str_ends_with($host, '.local') || $host === 'lvh.me' || str_ends_with($host, '.lvh.me')) {
            $problems[] = "APP_URL points at \"{$host}\", which is a local development address - set it to the domain the platform is served at";
        }

        return $problems;
    }
}
