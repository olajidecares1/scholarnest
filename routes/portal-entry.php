<?php

use App\Http\Controllers\Portal\AuthenticatedSessionController as PortalAuthenticatedSessionController;
use App\Http\Controllers\Portal\Basic\SchoolFinderController as BasicSchoolFinderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchoolPortal\Auth\AuthenticatedSessionController as SchoolPortalAuthenticatedSessionController;
use App\Http\Controllers\SchoolPortalController;
use App\Support\SecureRoute as R;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal entry points
|--------------------------------------------------------------------------
|
| How somebody GETS to one of the three portals above: a school's own portal
| page, the unified sign-in, the Basic-plan token finder - and misconduct
| reporting, which is public for the same reason.
|
*/

// School Portal — single entry point per school linking out to all four
// role-specific logins above, so a school's public website never has to
// send anyone to the shared global AkademicNest login. Same school-slug-scoped,
// readable-by-design convention as the three portals above. Available on
// every plan (login itself has never been plan-gated for any portal - only
// the post-login dashboards are, via "portal_access").
// The school is identified by an opaque key, not its slug - see
// routes/student.php for why, and the add_portal_key_to_schools_table
// migration for what the key is. The parameter is still "school" and still
// resolves to a School, so no controller or route() call changed.
Route::prefix('p/{school:portal_key}/portal')->name('portal.')->group(function () {
    Route::get('/', [SchoolPortalController::class, 'index'])->name('index');

    Route::name('admin.')->prefix('admin')->group(function () {
        Route::middleware(['guest', 'portal_token'])->group(function () {
            Route::get('{token}/login', [SchoolPortalAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('{token}/login', [SchoolPortalAuthenticatedSessionController::class, 'store']);
        });

        Route::middleware(['auth', 'school_admin', 'auth.session'])->group(function () {
            Route::post('logout', [SchoolPortalAuthenticatedSessionController::class, 'destroy'])->name('logout');
        });
    });
});

// THE FRONT DOOR, for every plan.
//
// Type your school's name, land on that school's portal page, and pick the
// portal you need - and which portals are offered there is decided by the
// school's plan, not here. Basic gets School Admin, Staff and result checking;
// Standard and Exclusive add the Student and Parent portals.
//
// Open, with no token in the address, because a school cannot use a door it
// cannot find. The token-gated address below still works and is unchanged;
// this is simply the one a school can be told about.
//
// Throttled on the POST for the same reason it always was: the search runs
// against school names, and an unthrottled one is a way to enumerate
// AkademicNest's customer list.
Route::get('/portal', [BasicSchoolFinderController::class, 'show'])->name('portal.find.show');
Route::post('/portal', [BasicSchoolFinderController::class, 'find'])
    ->middleware(['throttle:20,1', 'honeypot'])
    ->name('portal.find');

// The new unified portal login (Phase 2 of the portal-URL-security rewrite),
// default-host mirror of the tenant-domain pair registered above - this is
// the actual Basic-plan entry point (no {school:slug}, disambiguated by the
// school_code field instead). Additive alongside the school-slug-scoped
// "portal." group above; Phase 3 removes that group and reclaims "/portal"
// for this route.
Route::get('/portal/sign-in', [PortalAuthenticatedSessionController::class, 'create'])->name('portal.show');
Route::post('/portal/sign-in', [PortalAuthenticatedSessionController::class, 'store'])->name('portal.attempt');

// Basic-plan portal - the school finder.
//
// Basic schools have no public website and no subdomain, so unlike Standard
// and Exclusive they cannot be reached directly. Everyone arrives here, at one
// shared token-gated URL, names their school, and is forwarded to it. This
// flow is entirely separate from the two portals above and shares no route,
// controller or view with them. See docs/BASIC-PLAN-PORTAL.md.
//
// The token sits alone at the root - akademicanest.com/6219db402a20f65b63358972bd5274cd
// - so the address gives away nothing at all about the application's shape.
// There is no "/portal" segment to notice, and nothing to strip off and probe.
//
// ORDER MATTERS. This shares the root namespace with the school-slug route at
// the very bottom of this file, and a 32-character hex token also satisfies
// that route's slug pattern. Registering the token first means it always wins,
// and School::booted() refuses to generate a bare 32-character slug so no real
// school can ever be hidden behind this.
//
// It does not shadow the rest of the application: the parameter matches only
// [A-Za-z0-9], so it can never span a "/", and the obfuscated R::uri() paths
// elsewhere in this file are 128 characters, not 32.
//
// Throttled because the POST is a lookup against school names, and an
// unthrottled one would let anyone enumerate AkademicNest's customer list.
Route::middleware(['basic_portal_token', 'throttle:20,1'])
    ->where(['token' => '[A-Za-z0-9]{32}'])
    ->name('basic-portal.')
    ->group(function () {
        Route::get('{token}', [BasicSchoolFinderController::class, 'show'])->name('finder');
        Route::post('{token}', [BasicSchoolFinderController::class, 'find'])->middleware('honeypot')->name('find');
    });

Route::middleware('throttle:5,1')->group(function () {
    Route::get(R::uri('reports.create'), [ReportController::class, 'create'])->name('reports.create');
    Route::post(R::uri('reports.create'), [ReportController::class, 'store'])->middleware('honeypot')->name('reports.store');
});
Route::get(R::uri('reports.confirmation'), [ReportController::class, 'confirmation'])->name('reports.confirmation');
