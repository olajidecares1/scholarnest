<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureGuardianIsActive;
use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsureSchoolHasCbtAccess;
use App\Http\Middleware\EnsureSchoolHasCustomDomainAccess;
use App\Http\Middleware\EnsureSchoolHasIdCardAccess;
use App\Http\Middleware\EnsureSchoolHasPortalAccess;
use App\Http\Middleware\EnsureSchoolHasResultPinAccess;
use App\Http\Middleware\EnsureSchoolHasWebsiteAccess;
use App\Http\Middleware\EnsureSchoolIsActivated;
use App\Http\Middleware\EnsureStaffIsActive;
use App\Http\Middleware\EnsureStaffIsTeacher;
use App\Http\Middleware\EnsureStudentIsActive;
use App\Http\Middleware\EnsureUserIsSchoolAdmin;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\LogsOutIdleUsers;
use App\Http\Middleware\RedirectToCustomDomain;
use App\Http\Middleware\ResolveTenantFromCustomDomain;
use App\Http\Middleware\TrackPageView;
use App\Http\Middleware\ValidateSchoolPortalToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'super_admin' => EnsureUserIsSuperAdmin::class,
            'school_admin' => EnsureUserIsSchoolAdmin::class,
            'school_activated' => EnsureSchoolIsActivated::class,
            'permission' => EnsureHasPermission::class,
            'student_active' => EnsureStudentIsActive::class,
            'guardian_active' => EnsureGuardianIsActive::class,
            'staff_active' => EnsureStaffIsActive::class,
            'staff_is_teacher' => EnsureStaffIsTeacher::class,
            'portal_access' => EnsureSchoolHasPortalAccess::class,
            'id_card_access' => EnsureSchoolHasIdCardAccess::class,
            'cbt_access' => EnsureSchoolHasCbtAccess::class,
            'result_pin_access' => EnsureSchoolHasResultPinAccess::class,
            'website_access' => EnsureSchoolHasWebsiteAccess::class,
            'custom_domain_access' => EnsureSchoolHasCustomDomainAccess::class,
            'resolve_tenant_domain' => ResolveTenantFromCustomDomain::class,
            'redirect_to_custom_domain' => RedirectToCustomDomain::class,
            'portal_token' => ValidateSchoolPortalToken::class,
            'auth.session' => AuthenticateSession::class,
        ]);

        $middleware->web(append: [
            CheckMaintenanceMode::class,
            TrackPageView::class,
            LogsOutIdleUsers::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
