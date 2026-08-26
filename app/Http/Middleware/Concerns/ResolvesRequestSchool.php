<?php

namespace App\Http\Middleware\Concerns;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Finds the school behind a request, whichever portal it arrived through.
 *
 * Plan gates are asked the same question on School Admin routes and on portal
 * routes, but the answer used to live in different places: School Admin runs on
 * the default `web` guard, while each portal has its own. A gate written for
 * one guard silently misfires on the other - `$request->user()` is simply null
 * there, so a `?->` chain collapses to "no access" for everyone.
 */
trait ResolvesRequestSchool
{
    /**
     * The acting user's school, resolved from whichever guard owns this route.
     */
    protected function resolveSchool(Request $request): ?School
    {
        if ($user = $request->user()) {
            return $user->school;
        }

        // Portal route names are prefixed with their guard: "staff.cbt.index".
        $guardName = str($request->route()?->getName())->before('.')->toString();

        if ($guardName === '' || ! array_key_exists($guardName, config('auth.guards', []))) {
            return null;
        }

        return Auth::guard($guardName)->user()?->school;
    }
}
