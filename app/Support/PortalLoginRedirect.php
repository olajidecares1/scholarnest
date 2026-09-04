<?php

namespace App\Support;

use App\Models\School;
use Illuminate\Http\Request;

/**
 * Where to send somebody whose session has gone.
 *
 * THE BUG THIS EXISTS FOR. Laravel's auth middleware redirects a guest to
 * route('login'), and route('login') in this application redirects again, to
 * route('register') - the global sign-in page was removed once every portal
 * got its own, and the name was left pointing at the public front door. So the
 * end of every expired session was SCHOOL REGISTRATION: a School Admin who
 * stepped away for four minutes came back to a form inviting them to register
 * the school they already own.
 *
 * An expired session means one thing only - "this person needs to sign in
 * again". It never means the school needs creating. So this works out which
 * portal they were in and sends them to that portal's own login.
 *
 * The school is looked for in three places, in descending order of certainty:
 *
 *   1. The ROUTE. The staff, student and guardian portals all carry
 *      /p/{portal_key}/... in the URL, so the school is right there in the
 *      address they just asked for, whatever their session says. The key is
 *      opaque - it identifies the school without naming it.
 *   2. The HOST. A school reached at its own domain or subdomain is identified
 *      by the request itself.
 *   3. A COOKIE written when they signed in. The School Admin dashboard is an
 *      obfuscated path with no school in it and may be reached on the default
 *      host, so for that portal the first two find nothing. Rule 2.4 asks that
 *      the school context survive independently of the authentication session,
 *      and a cookie is the only thing here that does.
 *
 * If all three come up empty the destination is the unified portal sign-in -
 * still a login page, still not registration.
 */
final class PortalLoginRedirect
{
    /**
     * Remembers which school and portal this browser last signed in to.
     *
     * Encrypted in transit like every other cookie this application sets, and
     * it holds nothing the person does not already know - the id of their own
     * school and the name of the portal they use.
     */
    public const COOKIE = 'scholarnest_portal';

    public const COOKIE_MINUTES = 60 * 24 * 365;

    public static function for(Request $request): string
    {
        // The Super Admin is the one exception, and deliberately so: they have
        // no school and no portal, and they sign in through the hidden dialog
        // on the registration page. Sending them to a school portal login would
        // be sending them somewhere they can never get in.
        if ($request->routeIs('super-admin.*')) {
            return route('login');
        }

        $guard = self::guard($request);
        $school = self::school($request, $guard);

        if ($school instanceof School) {
            try {
                return $school->portalLoginUrl($guard);
            } catch (\InvalidArgumentException) {
                // An unknown guard is not a reason to fall through to
                // registration; the unified sign-in below still fits.
            }
        }

        return route('portal.show');
    }

    /**
     * Which portal the request was for.
     *
     * Read from the route rather than from the session, which by definition is
     * the thing that has gone.
     */
    public static function guard(Request $request): string
    {
        $route = $request->route();

        foreach ((array) ($route?->gatherMiddleware() ?? []) as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'auth:')) {
                return explode(',', substr($middleware, 5))[0];
            }
        }

        // No explicit guard means the default one, which is the School Admin's.
        // The route name is a second opinion for the portals that reach their
        // pages without naming a guard in the middleware.
        $name = (string) ($route?->getName() ?? '');

        return match (true) {
            str_starts_with($name, 'staff.') => 'staff',
            str_starts_with($name, 'student.') => 'student',
            str_starts_with($name, 'guardian.') => 'guardian',
            default => 'web',
        };
    }

    private static function school(Request $request, string $guard): ?School
    {
        return self::fromRoute($request)
            ?? self::fromHost($request)
            ?? self::fromCookie($request, $guard);
    }

    private static function fromRoute(Request $request): ?School
    {
        foreach (['school', 'tenantDomain'] as $parameter) {
            $value = $request->route($parameter);

            if ($value instanceof School) {
                return $value;
            }

            // Normally the parameter is already a School - model binding has
            // run. This is the fallback for a request that failed before
            // binding, where all we have is the raw segment.
            //
            // BOTH COLUMNS, because the two parameters carry different things:
            // "school" is now a portal_key on the portal routes, while
            // "tenantDomain" is a subdomain built from the slug. Checking only
            // one silently loses half the cases.
            if (is_string($value) && $value !== '') {
                $school = School::where('portal_key', $value)
                    ->orWhere('slug', $value)
                    ->first();

                if ($school) {
                    return $school;
                }
            }
        }

        return null;
    }

    private static function fromHost(Request $request): ?School
    {
        $host = $request->getHost();
        $base = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! $base || $host === $base || ! str_ends_with($host, ".{$base}")) {
            return null;
        }

        return School::where('slug', substr($host, 0, -(strlen($base) + 1)))->first();
    }

    /**
     * Only honoured for the portal it was written for, so a browser that last
     * signed in as a teacher is not handed the admin login.
     */
    private static function fromCookie(Request $request, string $guard): ?School
    {
        $value = $request->cookie(self::COOKIE);

        if (! is_string($value) || ! str_contains($value, ':')) {
            return null;
        }

        [$schoolId, $rememberedGuard] = explode(':', $value, 2);

        if ($rememberedGuard !== $guard || ! ctype_digit($schoolId)) {
            return null;
        }

        return School::find((int) $schoolId);
    }
}
