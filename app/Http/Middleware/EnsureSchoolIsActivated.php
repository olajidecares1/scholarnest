<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks every School Admin content route (students, staff, finance,
 * website, settings, ...) until a Super Admin has explicitly approved the
 * school's subscription. Redirects to the dashboard rather than aborting,
 * since the dashboard is where the school sees exactly why it's blocked and
 * what to do next (choose a plan, wait for review, or contact support).
 */
class EnsureSchoolIsActivated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->school?->hasActiveSubscription()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
