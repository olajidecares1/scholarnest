<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuardianIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guardian = Auth::guard('guardian')->user();

        abort_unless($guardian?->is_active, 403);

        // See EnsureStudentIsActive for why the route's {school:slug} can't
        // be trusted without cross-checking it against the authenticated
        // user's own school.
        $school = $request->route('school');
        abort_if($school && $school->id !== $guardian->school_id, 404);

        return $next($request);
    }
}
