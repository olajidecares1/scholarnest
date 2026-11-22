<?php

namespace App\Support;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Throwable;

/**
 * Where to send somebody who has just signed in to a portal.
 *
 * THE BUG THIS EXISTS FOR. Every login used redirect()->intended(), and the
 * "intended" address lives in the SESSION, which is shared by all four
 * portals in one browser. Laravel writes it whenever a guest asks for a
 * signed-in page: a bookmarked Staff Portal page, the installed Staff app
 * opening at its dashboard, the offline worker refreshing a teacher's kept
 * pages after their session ended. Any of those left a Staff Portal address
 * waiting in the session, and the next sign-in of ANY kind collected it. So a
 * School Admin signed in at the School Admin login, was sent to the Staff
 * Portal dashboard, was not signed in there, and landed on the Staff Portal
 * login instead of their own dashboard.
 *
 * The intended address is still honoured, it is how an expired session
 * brings you back to the page you were on, but only when it belongs to the
 * portal you have just signed in to. Anything else is thrown away and you go
 * to your own dashboard.
 */
final class PortalIntendedUrl
{
    public const SCHOOL_ADMIN = 'school-admin';

    public const SUPER_ADMIN = 'super-admin';

    public const STAFF = 'staff';

    public const STUDENT = 'student';

    public const GUARDIAN = 'guardian';

    /**
     * Redirect to the intended address if it is this portal's, otherwise to
     * the portal's own dashboard. The intended address is removed from the
     * session either way, so a stale one cannot surface at a later sign-in.
     */
    public static function redirect(Request $request, string $portal, string $default): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && self::belongsTo($intended, $portal, $request)) {
            return redirect()->to($intended);
        }

        return redirect()->to($default);
    }

    /**
     * The portal a School Admin or Super Admin account signs in to.
     */
    public static function portalForWebUser(mixed $user): string
    {
        return ($user->role ?? null) === UserRole::SuperAdmin
            ? self::SUPER_ADMIN
            : self::SCHOOL_ADMIN;
    }

    public static function belongsTo(string $url, string $portal, Request $request): bool
    {
        // Same host only. Session cookies are host-only so this should never
        // differ, but a redirect is not the place to find out.
        $host = parse_url($url, PHP_URL_HOST);

        if (is_string($host) && strcasecmp($host, $request->getHost()) !== 0) {
            return false;
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($url, 'GET'));
        } catch (Throwable) {
            return false;
        }

        $guard = self::authGuard($route);

        if ($guard === null) {
            return false;
        }

        $isSuperAdminPage = str_starts_with((string) $route->getName(), 'super-admin.');

        return match ($portal) {
            self::SCHOOL_ADMIN => $guard === 'web' && ! $isSuperAdminPage,
            self::SUPER_ADMIN => $guard === 'web' && $isSuperAdminPage,
            self::STAFF => $guard === 'staff',
            self::STUDENT => $guard === 'student',
            self::GUARDIAN => $guard === 'guardian',
            default => false,
        };
    }

    /**
     * The guard a route's "auth" middleware names, or null for a page that
     * needs nobody signed in (a login page, a public page).
     */
    private static function authGuard(Route $route): ?string
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if ($middleware === 'auth') {
                return (string) config('auth.defaults.guard', 'web');
            }

            if (str_starts_with($middleware, 'auth:')) {
                return explode(',', substr($middleware, 5))[0];
            }
        }

        return null;
    }
}
