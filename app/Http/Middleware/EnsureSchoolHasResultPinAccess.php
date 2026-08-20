<?php

namespace App\Http\Middleware;

use App\Enums\PlanKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolHasResultPinAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hasResultPinAccess = $request->user()?->school?->hasPlanAccess(PlanKey::Basic) ?? false;

        abort_unless($hasResultPinAccess, 403, 'Result-checking PINs require the Basic plan.');

        return $next($request);
    }
}
