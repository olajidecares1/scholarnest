<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSchoolAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->role === UserRole::SchoolAdmin, 403);

        if ($user->school === null) {
            return self::signOutOrphan($request);
        }

        return $next($request);
    }

    /**
     * End the session of a School Admin whose school no longer exists.
     *
     * `users.school_id` is nullable and SET NULL on delete, so an account can
     * outlive its school. Every school-side page reaches for the school on its
     * first line - the layout reads its name, logo and plan - so such an
     * account produced a fatal error on whatever it opened rather than a
     * refusal.
     *
     * Signing them out is the honest outcome: a School Admin without a school
     * has nothing left in the application to be shown, and leaving the session
     * alive only defers the same crash to the next click.
     */
    public static function signOutOrphan(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The unified sign-in, not route('login') - that name redirects on to
        // registration, and this message beside a "register your school" form
        // reads as an instruction to do exactly that.
        return redirect()->route('portal.show')->withErrors([
            'login' => 'This school account is no longer available. Please contact ScholarNest support if you believe this is a mistake.',
        ]);
    }
}
