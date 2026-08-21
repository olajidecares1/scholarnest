<?php

use App\Http\Controllers\Auth\AdminPasswordResetController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Support\SecureRoute as R;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get(R::uri('register'), [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post(R::uri('register'), [RegisteredUserController::class, 'store']);

    Route::get(R::uri('login'), [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post(R::uri('login'), [AuthenticatedSessionController::class, 'store']);

    Route::get(R::uri('password.request'), [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::get(R::uri('password.reset').'/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    // Both of these are limited per IP address.
    //
    // Laravel's password broker already refuses to send a second link for the
    // SAME email address within 60 seconds. What it does not stop is one
    // address requesting links for thousands of DIFFERENT accounts, which
    // would spray reset emails at a school's users and get EduNest's sending
    // domain marked as spam.
    //
    // The same limit protects password.store from having reset tokens guessed.
    Route::middleware('throttle:5,1')->group(function () {
        Route::post(R::uri('password.request'), [PasswordResetLinkController::class, 'store'])
            ->name('password.email');

        Route::post(R::uri('password.store'), [NewPasswordController::class, 'store'])
            ->name('password.store');
    });

    // The School Admin (web-guard) forgot-password flow: link + 6-digit
    // code. Deliberately has no Student/Staff/Guardian equivalent - see
    // App\Http\Controllers\Auth\AdminPasswordResetController's docblock.
    Route::middleware('throttle:10,1')->group(function () {
        Route::get(R::uri('admin.password-reset.request'), [AdminPasswordResetController::class, 'create'])
            ->name('admin.password-reset.request');

        Route::post(R::uri('admin.password-reset.request'), [AdminPasswordResetController::class, 'store'])
            ->name('admin.password-reset.send');

        Route::get(R::uri('admin.password-reset.show').'/{token}', [AdminPasswordResetController::class, 'show'])
            ->name('admin.password-reset.show');

        Route::post(R::uri('admin.password-reset.show').'/{token}/verify-code', [AdminPasswordResetController::class, 'verifyCode'])
            ->name('admin.password-reset.verify-code');

        Route::post(R::uri('admin.password-reset.show').'/{token}/complete', [AdminPasswordResetController::class, 'complete'])
            ->name('admin.password-reset.complete');
    });
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
