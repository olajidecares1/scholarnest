<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = Setting::current();

        $isBypassRoute = $request->routeIs('login')
            || $request->routeIs('logout')
            || $request->routeIs('super-admin.*');

        if ($settings->maintenance_mode && ! $isBypassRoute && $request->user()?->role !== UserRole::SuperAdmin) {
            return response()->view('maintenance', [
                'message' => $settings->maintenance_message,
            ], 503);
        }

        return $next($request);
    }
}
