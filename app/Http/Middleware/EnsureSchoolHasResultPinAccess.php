<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Result tokens are available on every plan.
 *
 * They used to be gated to Basic, which made them look like a consolation
 * prize for schools with no website. They are not a plan feature - they are
 * how a result reaches a parent safely - so a Standard or Exclusive school
 * needs them exactly as much.
 *
 * What is still required is an active subscription: a school that has not paid,
 * or has been suspended, should not be issuing anything.
 */
class EnsureSchoolHasResultPinAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $school = $request->user()?->school;

        abort_unless(
            $school?->hasActiveSubscription() ?? false,
            403,
            'Result tokens require an active subscription.',
        );

        return $next($request);
    }
}
