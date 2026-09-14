<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\PortalLoginRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogsOutIdleUsers
{
    /**
     * Applied globally (see bootstrap/app.php) rather than to individual
     * route groups, so every current and future protected route is covered
     * without relying on remembering to attach it, a missed route group
     * would otherwise silently exempt that page from the timeout.
     */
    private const IDLE_SECONDS = 180;

    /**
     * @var list<string>
     */
    private const GUARDS = ['web', 'student', 'staff', 'guardian'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // The public website is not a portal and must never be decided by one.
        //
        // This middleware is global, which is right for covering every
        // protected route without having to remember to attach it, but global
        // meant it also ran on the school's PUBLIC website, and there it did
        // real damage. A School Admin looking at their own site, signed in in
        // the same browser, spends four minutes writing a message on the
        // contact form; this saw an idle session on the way in, logged them
        // out, and answered the POST with the portal login. The message was
        // never written and the visitor was thrown off the public site, both
        // halves of the reported bug, from one line.
        //
        // Asking the ROUTE whether it requires authentication keeps that from
        // coming back: a new protected route is covered the moment it is given
        // auth middleware, and a new public one is exempt without anybody
        // having to think about it.
        if (! $this->requiresAuthentication($request)) {
            return $next($request);
        }

        $authenticated = false;

        foreach (self::GUARDS as $guard) {
            $user = Auth::guard($guard)->user();

            if (! $user) {
                continue;
            }

            // Super Admins have no school and are not part of the
            // school-portal idle-timeout requirement this middleware exists
            // for, they keep the framework's normal session lifetime.
            if ($guard === 'web' && $user->role === UserRole::SuperAdmin) {
                continue;
            }

            $authenticated = true;

            $sessionKey = "idle_last_activity_{$guard}";
            $lastActivity = $request->session()->get($sessionKey);

            // diffInSeconds() returns a signed value (negative when $lastActivity
            // is in the past relative to now), absolute: true is required or a
            // stale/expired timestamp would never compare greater than the limit.
            if ($lastActivity !== null && now()->diffInSeconds($lastActivity, absolute: true) > self::IDLE_SECONDS) {
                return $this->expire($request, $guard, $user, $sessionKey);
            }

            $request->session()->put($sessionKey, now());
        }

        $response = $next($request);

        // A stale, cached copy of a protected page must not be servable
        // after logout/expiry via the browser's back button, the auth
        // check on the next real request is the actual security boundary,
        // this just stops the browser from showing a cached page without
        // even making that request.
        if ($authenticated) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    /**
     * Does the matched route sit behind an auth guard?
     *
     * Read off the route's own middleware rather than kept as a list of paths
     * here, because a list would go stale the first time somebody added a
     * route and did not know this file existed.
     */
    private function requiresAuthentication(Request $request): bool
    {
        $route = $request->route();

        if (! $route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            // "auth", "auth:staff", "auth.session", all of them mean this
            // route is somebody's signed-in page.
            if (is_string($middleware) && preg_match('/^auth(\.|:|$)/', $middleware) === 1) {
                return true;
            }
        }

        return false;
    }

    private function expire(Request $request, string $guard, mixed $user, string $sessionKey): Response
    {
        Auth::guard($guard)->logout();
        $request->session()->forget($sessionKey);

        // The school is read off the user before the session goes, which is
        // what keeps the redirect school-specific: once the session is gone
        // there is nothing left in the request that says which school this
        // was. The global login is only for a user with no school of their
        // own, a Super Admin, or an account whose school has been deleted.
        // Never route('login'): that name redirects on to registration, so the
        // old fallback ended an expired session at "register your school".
        // PortalLoginRedirect works the portal out from the request instead,
        // and its own last resort is the unified sign-in.
        $destination = $user->school?->portalLoginUrl($guard) ?? PortalLoginRedirect::for($request);

        if ($request->expectsJson()) {
            // No message: an expiry is not news the person needs told. They
            // stepped away, they come back to a login page, they sign in
            // again. A banner explaining it only draws attention to an
            // interruption they had already worked out.
            return response()->json(['redirect' => $destination], 401);
        }

        return redirect()->to($destination);
    }
}
