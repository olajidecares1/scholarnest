<?php

namespace App\Http\Middleware;

use App\Enums\StaffRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffIsTeacher
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth('staff')->user()?->role === StaffRole::Teacher, 403, 'This feature is only available to teaching staff.');

        return $next($request);
    }
}
