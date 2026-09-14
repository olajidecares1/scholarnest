<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $student = Auth::guard('student')->user();

        abort_unless($student?->is_active, 403);

        // The route's {school:slug} is client-supplied and must never be
        // trusted on its own, without this check, a student authenticated
        // for one school could browse another school's portal URLs and get
        // served pages branded with (and in principle scoped to) a school
        // they don't belong to, even though every query in this portal is
        // ultimately keyed off the student's own school_id.
        $school = $request->route('school');
        abort_if($school && $school->id !== $student->school_id, 404);

        return $next($request);
    }
}
