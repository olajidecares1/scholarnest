<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Who a page belongs to, for the purposes of keeping it on the device.
 *
 * The service worker keeps signed-in pages so a portal can be used with no
 * connection, see resources/views/pwa/service-worker.blade.php. Those pages
 * are personal, and a shared family phone can hold a pupil's portal and a
 * parent's portal side by side, so every kept page is filed under a SCOPE:
 * an opaque identifier for one account on one guard. Signing out clears that
 * scope's pages from the device, and pages from one scope are never served to
 * a request made while working in another.
 *
 * The identifier is an HMAC over the guard and the account id with the
 * application key, so it says nothing about who the account is and cannot be
 * forged for an account somebody does not hold.
 */
class OfflineScope
{
    /**
     * Every guard a portal signs in on. Mirrors LogsOutIdleUsers::GUARDS.
     *
     * @var list<string>
     */
    public const GUARDS = ['web', 'staff', 'student', 'guardian'];

    public static function for(string $guard, Authenticatable $user): string
    {
        return substr(
            hash_hmac('sha256', $guard.'|'.$user->getAuthIdentifier(), (string) config('app.key')),
            0,
            32,
        );
    }

    /**
     * The guards the matched route is protected by, in the order the route
     * names them. Empty for a public route.
     *
     * Read off the route's own middleware, like LogsOutIdleUsers does, so a
     * new protected route is covered the moment it is given auth middleware.
     *
     * @return list<string>
     */
    public static function routeGuards(Request $request): array
    {
        $route = $request->route();

        if (! $route || ! is_object($route)) {
            return [];
        }

        $guards = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if ($middleware === 'auth') {
                $guards[] = config('auth.defaults.guard', 'web');

                continue;
            }

            if (str_starts_with($middleware, 'auth:')) {
                foreach (explode(',', substr($middleware, 5)) as $guard) {
                    $guards[] = trim($guard);
                }
            }
        }

        return array_values(array_unique(array_filter($guards)));
    }

    public static function requiresAuthentication(Request $request): bool
    {
        return self::routeGuards($request) !== [];
    }

    /**
     * The guard and scope the current request is being served for, or null on
     * a public page or when nobody is signed in on the route's guard.
     *
     * @return array{guard: string, scope: string}|null
     */
    public static function current(Request $request): ?array
    {
        foreach (self::routeGuards($request) as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user) {
                return ['guard' => $guard, 'scope' => self::for($guard, $user)];
            }
        }

        return null;
    }

    /**
     * Every scope with a live session in this browser, keyed by guard.
     *
     * @return array<string, string>
     */
    public static function active(): array
    {
        $scopes = [];

        foreach (self::GUARDS as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user) {
                $scopes[$guard] = self::for($guard, $user);
            }
        }

        return $scopes;
    }

    /**
     * Whether anybody at all is signed in, on any guard.
     */
    public static function anyoneSignedIn(): bool
    {
        return self::active() !== [];
    }
}
