<?php

namespace App\Http\Middleware;

use App\Enums\PlanKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolHasCbtAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hasCbtAccess = $request->user()?->school?->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive) ?? false;

        abort_unless($hasCbtAccess, 403, 'CBT requires the Standard or Exclusive plan.');

        return $next($request);
    }
}
