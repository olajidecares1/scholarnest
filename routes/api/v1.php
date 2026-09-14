<?php

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Read-only, and deliberately so for a first version. Everything here answers
| a question a mobile client needs to draw a screen; nothing here changes a
| record. Writes, submitting homework, marking a register, are a second
| conversation about idempotency and offline conflict that this version does
| not start.
|
| Three middleware do the work on every signed-in route:
|
|   auth:sanctum   the token is real and unexpired
|   api.active     the account and its school are still active, checked per
|                  request, because a token outlives a sign-in by weeks
|   api.actor      this KIND of account may be here at all; a student's token
|                  is a perfectly valid token at a guardian's endpoint
|
*/

use App\Http\Controllers\Api\V1\Guardian\ChildController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Student\AssignmentController;
use App\Http\Controllers\Api\V1\Student\AttendanceController;
use App\Http\Controllers\Api\V1\Student\NoticeController;
use App\Http\Controllers\Api\V1\Student\ResultController;
use App\Http\Controllers\Api\V1\Student\TimetableController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

/*
 * Signing in.
 *
 * Throttled per IP on top of the per-account, per-school limiter the shared
 * login request already applies. The two answer different questions: that one
 * stops somebody guessing at one account, this one stops somebody working
 * through many.
 */
Route::post('tokens', [TokenController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('tokens.store');

Route::middleware(['auth:sanctum', 'api.active'])->group(function () {
    Route::get('me', MeController::class)->name('me');

    Route::delete('tokens/current', [TokenController::class, 'destroyCurrent'])->name('tokens.destroy-current');
    Route::delete('tokens', [TokenController::class, 'destroyAll'])->name('tokens.destroy-all');

    Route::prefix('student')->name('student.')->middleware('api.actor:student')->group(function () {
        Route::get('results', [ResultController::class, 'index'])->name('results.index');

        // The exam token travels with this request. There is no session to
        // remember an unlocked result in, see the controller.
        Route::post('results/{examination}', [ResultController::class, 'show'])->name('results.show');

        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('timetable', [TimetableController::class, 'index'])->name('timetable.index');
        Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('notices', [NoticeController::class, 'index'])->name('notices.index');
    });

    Route::prefix('guardian')->name('guardian.')->middleware('api.actor:guardian')->group(function () {
        Route::get('children', [ChildController::class, 'index'])->name('children.index');
        Route::get('children/{student}', [ChildController::class, 'show'])->name('children.show');
        Route::get('children/{student}/attendance', [ChildController::class, 'attendance'])->name('children.attendance');
    });
});
