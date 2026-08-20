<?php

namespace App\Http\Middleware;

use App\Enums\PlanKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolHasCustomDomainAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hasCustomDomainAccess = $request->user()?->school?->hasPlanAccess(PlanKey::Exclusive) ?? false;

        abort_unless($hasCustomDomainAccess, 403, 'Custom Domain requires the Exclusive plan.');

        return $next($request);
    }
}
