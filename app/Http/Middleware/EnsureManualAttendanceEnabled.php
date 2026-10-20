<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ResolvesRequestSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes every manual attendance route, web and API, for a school whose
 * Super Admin switch is off.
 *
 * Opening the URL directly or calling the endpoint gets a 403 rather than the
 * page or a save. Nothing is deleted: switching the feature back on brings
 * every previously entered total back exactly as it was.
 */
class EnsureManualAttendanceEnabled
{
    use ResolvesRequestSchool;

    public const MESSAGE = 'This feature has been disabled for your school.';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->resolveSchool($request)?->hasManualAttendance()) {
            return $next($request);
        }

        // A machine-readable code beside the sentence, so the mobile app can
        // hide the feature rather than only display the message.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => self::MESSAGE,
                'code' => 'feature_disabled',
                'feature' => 'manual_attendance',
            ], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, self::MESSAGE);
    }
}
