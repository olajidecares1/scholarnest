<?php

namespace App\Http\Middleware;

use App\Enums\PlanKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the school-scoped portals by plan.
 *
 * Not every portal is worth the same money, so they are not gated alike.
 *
 * The STAFF portal is available on every plan. It is where teachers take
 * attendance, enter marks and read results, the school's own academic
 * workings, not a premium extra, and locking a Basic school out of it left
 * that school with an academic system its teachers could not reach, so the
 * School Admin had to type in every score personally.
 *
 * The STUDENT and GUARDIAN portals stay on Standard and Exclusive. Those are
 * outward-facing accounts for families, which is exactly the premium tier
 * Basic does not buy. A Basic parent is not shut out of results, though, they
 * reach them through the result-token flow, which needs no account at all.
 */
class EnsureSchoolHasPortalAccess
{
    /**
     * Portals that any active subscription may use, keyed by guard name.
     *
     * @var list<string>
     */
    private const PORTALS_ON_EVERY_PLAN = ['staff'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Route names are always prefixed with the owning guard's name
        // ("student.dashboard", "staff.dashboard"...), so the guard to check
        // and the "locked" route to redirect to can both be derived from the
        // current route name, keeping this middleware reusable across every
        // portal guarded by the same plan gate.
        $guardName = str($request->route()?->getName())->before('.')->toString();

        $school = Auth::guard($guardName)->user()?->school;

        $hasPortalAccess = in_array($guardName, self::PORTALS_ON_EVERY_PLAN, true)
            ? ($school?->hasActiveSubscription() ?? false)
            : ($school?->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive) ?? false);

        $lockedRoute = "{$guardName}.locked";

        if (! $hasPortalAccess && ! $request->routeIs($lockedRoute)) {
            return redirect()->route($lockedRoute, $request->route('school'));
        }

        return $next($request);
    }
}
