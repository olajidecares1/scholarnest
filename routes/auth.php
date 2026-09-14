<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SuperAdminSessionController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Support\SecureRoute as R;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get(R::uri('register'), [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post(R::uri('register'), [RegisteredUserController::class, 'store'])->middleware('honeypot');

    Route::get(R::uri('login'), [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post(R::uri('login'), [AuthenticatedSessionController::class, 'store']);

    // The hidden Super Admin sign-in, revealed by the logo click sequence on
    // the registration page. Only the DIALOG is hidden, this endpoint applies
    // the full credential, role, status, throttle and CSRF checks to every
    // request that reaches it, however it got here. See
    // App\Http\Controllers\Auth\SuperAdminSessionController.
    Route::get(R::uri('super-admin.login'), [SuperAdminSessionController::class, 'create'])
        ->name('super-admin.login');

    Route::post(R::uri('super-admin.login'), [SuperAdminSessionController::class, 'store']);

    Route::get(R::uri('password.request'), [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::get(R::uri('password.reset').'/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    // Both of these are limited per IP address.
    //
    // Laravel's password broker already refuses to send a second link for the
    // SAME email address within 60 seconds. What it does not stop is one
    // address requesting links for thousands of DIFFERENT accounts, which
    // would spray reset emails at a school's users and get AkademicNest's sending
    // domain marked as spam.
    //
    // The same limit protects password.store from having reset tokens guessed.
    Route::middleware('throttle:5,1')->group(function () {
        Route::post(R::uri('password.request'), [PasswordResetLinkController::class, 'store'])
            ->name('password.email');

        Route::post(R::uri('password.store'), [NewPasswordController::class, 'store'])
            ->name('password.store');
    });

    // There was a second, parallel reset flow here, its own token table, its
    // own broker, its own four pages, and NOTHING LINKED TO IT. The sign-in
    // page pointed at password.request above, so every administrator who ever
    // clicked "Forgot password?" used the route without the verification code,
    // while the stronger flow sat unreachable and unaudited.
    //
    // Two ways into the same account, one of them weaker and neither of them
    // obviously the real one, is worse than either alone. The code moved onto
    // the routes above, where the sign-in page already sends people, and the
    // parallel flow is gone.
});

Route::middleware('auth')->group(function () {
    Route::get(R::uri('verification.notice'), EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get(R::uri('verification.verify').'/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post(R::uri('verification.send'), [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get(R::uri('password.confirm'), [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post(R::uri('password.confirm'), [ConfirmablePasswordController::class, 'store']);

    Route::put(R::uri('password.update'), [PasswordController::class, 'update'])->name('password.update');

    Route::post(R::uri('logout'), [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
