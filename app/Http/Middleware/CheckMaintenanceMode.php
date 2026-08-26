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
            || $request->routeIs('super-admin.*')

            // The registration page carries the hidden Super Admin sign-in, and
            // "login" now redirects here rather than rendering a page of its
            // own. Without this the only way in during maintenance redirects
            // straight into the maintenance screen - locking the Super Admin
            // out of the switch that turns maintenance off.
            || $request->routeIs('register')

            // Schools sign in at their own portals, so those have to stay
            // reachable for the same reason the shared page used to be.
            || $request->routeIs('portal.admin.login')
            || $request->routeIs('tenant.portal.admin.login');

        if ($settings->maintenance_mode && ! $isBypassRoute && $request->user()?->role !== UserRole::SuperAdmin) {
            return response()->view('maintenance', [
                'message' => $settings->maintenance_message,
            ], 503);
        }

        return $next($request);
    }
}
