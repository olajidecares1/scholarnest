<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the platform on the platform's address, and each school's accounts on
 * that school's address.
 *
 * A school's subdomain serves more than its website: once someone signs in
 * there, the dashboards (which are not host-bound routes) are served on that
 * host too, which is what keeps a teacher on greenfield.akademicanest.com
 * after signing in. That is intended. Two things are not:
 *
 *   1. PLATFORM PAGES ON A SCHOOL'S HOST. The Super Admin console, its hidden
 *      sign-in and school registration belong to akademicanest.com. Served on
 *      a school's host they would share an origin with content that school
 *      writes for its own website, so they are sent to the platform address.
 *
 *   2. ANOTHER SCHOOL'S ACCOUNT ON THIS HOST. Session cookies are host-only,
 *      so this cannot normally happen, but tenant isolation should not rest on
 *      one cookie attribute. A signed-in account whose school is not the school
 *      this host belongs to is refused.
 *
 * Platform hosts (akademicanest.com, localhost, an IP) pass straight through
 * without a database query.
 */
class EnforceTenantHostBoundary
{
    /** Route names that only exist on the platform's own address. */
    private const PLATFORM_ROUTE_PREFIXES = ['super-admin.', 'register', 'registration.', 'basic-portal.'];

    private const GUARDS = ['web', 'staff', 'student', 'guardian'];

    public function __construct(private readonly TenantResolver $resolver) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resolution = $this->resolver->resolve($request->getHost());

        if (! $resolution->isTenantHost()) {
            return $next($request);
        }

        if ($this->isPlatformRoute($request)) {
            abort_unless($request->isMethodSafe(), 404);

            return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri());
        }

        $schoolId = $resolution->school?->id;

        foreach (self::GUARDS as $guard) {
            $account = auth($guard)->user();

            if ($account !== null && (int) ($account->school_id ?? 0) !== (int) $schoolId) {
                abort(403, 'This account does not belong to this school.');
            }
        }

        return $next($request);
    }

    private function isPlatformRoute(Request $request): bool
    {
        $route = $request->route();

        if (! $route) {
            return false;
        }

        $name = (string) $route->getName();

        foreach (self::PLATFORM_ROUTE_PREFIXES as $prefix) {
            if ($name === rtrim($prefix, '.') || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        // Some POST halves are unnamed; the controller still says whose they are.
        $action = (string) $route->getActionName();

        return str_contains($action, 'Controllers\\SuperAdmin\\')
            || str_contains($action, 'Auth\\SuperAdminSessionController')
            || str_contains($action, 'Auth\\RegisteredUserController');
    }
}
