<?php

namespace App\Http\Middleware;

use App\Enums\PlanKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolHasWebsiteAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hasWebsiteAccess = $request->user()?->school?->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive) ?? false;

        abort_unless($hasWebsiteAccess, 403, 'The Website builder requires the Standard or Exclusive plan.');

        return $next($request);
    }
}
