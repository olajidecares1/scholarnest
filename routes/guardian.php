<?php

use App\Http\Controllers\Guardian\AssignmentController as GuardianAssignmentController;
use App\Http\Controllers\Guardian\AttendanceController as GuardianAttendanceController;
use App\Http\Controllers\Guardian\Auth\AuthenticatedSessionController as GuardianAuthenticatedSessionController;
use App\Http\Controllers\Guardian\ChildProfileController as GuardianChildProfileController;
use App\Http\Controllers\Guardian\DashboardController as GuardianDashboardController;
use App\Http\Controllers\Guardian\FeeController as GuardianFeeController;
use App\Http\Controllers\Guardian\HelpController as GuardianHelpController;
use App\Http\Controllers\Guardian\MessageController as GuardianMessageController;
use App\Http\Controllers\Guardian\NotificationController as GuardianNotificationController;
use App\Http\Controllers\Guardian\PortalLockedController as GuardianPortalLockedController;
use App\Http\Controllers\Guardian\ProfileChangeRequestController as GuardianProfileChangeRequestController;
use App\Http\Controllers\Guardian\ResultController as GuardianResultController;
use App\Http\Controllers\Guardian\SettingsController as GuardianSettingsController;
use App\Http\Controllers\Guardian\TimetableController as GuardianTimetableController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Parent portal
|--------------------------------------------------------------------------
|
| Signed in on the `guardian` guard, scoped to one school by slug.
|
*/

// Parent Portal — same school-slug-scoped, readable convention as the Student Portal above.
Route::prefix('schools/{school:slug}/parent-portal')->name('guardian.')->group(function () {
    Route::middleware(['guest:guardian', 'portal_token'])->group(function () {
        Route::get('{token}/login', [GuardianAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('{token}/login', [GuardianAuthenticatedSessionController::class, 'store']);
    });

    Route::middleware(['auth:guardian', 'guardian_active'])->group(function () {
        Route::post('logout', [GuardianAuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('locked', [GuardianPortalLockedController::class, 'show'])->name('locked');

        Route::middleware('portal_access')->group(function () {
            Route::get('dashboard', [GuardianDashboardController::class, 'index'])->name('dashboard');

            Route::name('children.')->prefix('children/{student}')->group(function () {
                Route::get('profile', [GuardianChildProfileController::class, 'show'])->name('profile');
                Route::get('timetable', [GuardianTimetableController::class, 'index'])->name('timetable');
                Route::get('results', [GuardianResultController::class, 'index'])->name('results');
                Route::get('results/{examination}', [GuardianResultController::class, 'show'])->name('results.show');
                Route::get('results/{examination}/print', [GuardianResultController::class, 'print'])->name('results.print');
                Route::get('results/{examination}/pdf', [GuardianResultController::class, 'pdf'])->name('results.pdf');
                Route::post('results/{examination}/unlock', [GuardianResultController::class, 'unlock'])->name('results.unlock');
                Route::get('attendance', [GuardianAttendanceController::class, 'index'])->name('attendance');
                Route::get('assignments', [GuardianAssignmentController::class, 'index'])->name('assignments');
                Route::get('fees', [GuardianFeeController::class, 'index'])->name('fees');
                Route::post('profile-change-requests', [GuardianProfileChangeRequestController::class, 'store'])->name('profile-change-requests.store');
            });

            Route::name('messages.')->prefix('messages')->group(function () {
                Route::get('/', [GuardianMessageController::class, 'index'])->name('index');
            });

            Route::name('notifications.')->prefix('notifications')->group(function () {
                Route::get('/', [GuardianNotificationController::class, 'index'])->name('index');
            });

            Route::name('settings.')->prefix('settings')->group(function () {
                Route::get('/', [GuardianSettingsController::class, 'index'])->name('index');
                Route::put('profile', [GuardianSettingsController::class, 'updateProfile'])->name('update-profile');
            });

            Route::name('help.')->prefix('help')->group(function () {
                Route::get('/', [GuardianHelpController::class, 'index'])->name('index');
            });
        });
    });
});
