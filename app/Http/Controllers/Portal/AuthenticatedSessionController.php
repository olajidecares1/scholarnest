<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\LoginRequest;
use App\Services\PortalSessionBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * The single dashboard route each guard lands on once authenticated -
     * Trees 1/3/4/5 still resolve their own school from {school:slug}
     * today, unchanged from before this controller existed; the token
     * minted below is proven end-to-end here but isn't consumed by those
     * routes until the Phase 3 cutover.
     *
     * @var array<string, string>
     */
    private const DASHBOARD_ROUTES = [
        'web' => 'dashboard',
        'student' => 'student.dashboard',
        'staff' => 'staff.dashboard',
        'guardian' => 'guardian.dashboard',
    ];

    /**
     * Display the unified portal login view.
     */
    public function create(Request $request): View
    {
        return view('portal.login', [
            'school' => $request->route('tenantDomain'),
        ]);
    }

    /**
     * Handle an incoming unified portal authentication request.
     */
    public function store(LoginRequest $request, PortalSessionBroker $broker): RedirectResponse
    {
        $school = $request->resolveSchool();
        $guard = $request->string('role')->toString();

        $request->authenticateAs($guard, $school);

        $request->session()->regenerate();

        $user = Auth::guard($guard)->user();

        $broker->issue($school, $guard, $user, $request->session()->getId());

        $dashboardRoute = self::DASHBOARD_ROUTES[$guard];
        $routeParams = $guard === 'web' ? [] : [$school];

        return redirect()->intended(route($dashboardRoute, $routeParams, absolute: false));
    }
}
