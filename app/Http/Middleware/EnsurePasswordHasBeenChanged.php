<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a School Admin sets/resets a Student, Staff, or Guardian's password,
 * that account is flagged must_change_password so the temporary password
 * the admin chose can't just go on being used indefinitely - this
 * middleware blocks every page except the settings screen (where the
 * change-password form lives) and logout until the user sets their own.
 */
class EnsurePasswordHasBeenChanged
{
    /**
     * Route names (without the guard's own name prefix) that stay reachable
     * while a password change is pending.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTE_SUFFIXES = [
        'settings.index',
        'settings.update-password',
        'settings.update-profile',
        'logout',
        'locked',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $guard): Response
    {
        $user = Auth::guard($guard)->user();

        if (! $user?->must_change_password) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $suffix = $routeName ? str($routeName)->after("{$guard}.") : null;

        if ($suffix && in_array((string) $suffix, self::ALLOWED_ROUTE_SUFFIXES, true)) {
            return $next($request);
        }

        return redirect()
            ->route("{$guard}.settings.index", $request->route('school'))
            ->with('status', 'Your school admin reset your password. Please set a new password before continuing.');
    }
}
