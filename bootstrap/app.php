<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsureUserIsSchoolAdmin;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\TrackPageView;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
            'permission' => EnsureHasPermission::class,
        ]);

        $middleware->web(append: [
            CheckMaintenanceMode::class,
            TrackPageView::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
