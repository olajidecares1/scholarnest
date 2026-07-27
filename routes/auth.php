<?php

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

    Route::post(R::uri('password.request'), [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get(R::uri('password.reset').'/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post(R::uri('password.store'), [NewPasswordController::class, 'store'])
        ->name('password.store');
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
