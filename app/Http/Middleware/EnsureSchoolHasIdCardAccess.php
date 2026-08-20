<?php

namespace App\Http\Middleware;

use App\Enums\PlanKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolHasIdCardAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hasIdCardAccess = $request->user()?->school?->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive) ?? false;

        abort_unless($hasIdCardAccess, 403, 'ID Card Management requires the Standard or Exclusive plan.');

        return $next($request);
    }
}
