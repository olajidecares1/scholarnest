<?php

namespace App\Http\Middleware;

use App\Enums\PlanKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolHasPortalAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Route names are always prefixed with the owning guard's name
        // ("student.dashboard", "guardian.dashboard", ...), so the guard to
        // check and the "locked" route to redirect to can both be derived
        // from the current route name - keeping this middleware reusable
        // across every portal guarded by the same plan gate.
        $guardName = str($request->route()?->getName())->before('.')->toString();

        $user = Auth::guard($guardName)->user();
        $hasPortalAccess = $user?->school?->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive) ?? false;

        $lockedRoute = "{$guardName}.locked";

        if (! $hasPortalAccess && ! $request->routeIs($lockedRoute)) {
            return redirect()->route($lockedRoute, $request->route('school'));
        }

        return $next($request);
    }
}
