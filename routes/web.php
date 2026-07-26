<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Subscriptions\BillingDetailsController;
use App\Http\Controllers\Subscriptions\ChoosePlanController;
use App\Http\Controllers\Subscriptions\ConfirmationController;
use App\Http\Controllers\Subscriptions\ContactSalesController;
use App\Http\Controllers\Subscriptions\PaymentMethodController;
use App\Http\Controllers\Subscriptions\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
        Route::get('choose-plan', [ChoosePlanController::class, 'create'])->name('choose-plan');
        Route::post('choose-plan', [ChoosePlanController::class, 'store'])->name('choose-plan.store');

        Route::get('billing-details', [BillingDetailsController::class, 'create'])->name('billing-details');
        Route::post('billing-details', [BillingDetailsController::class, 'store'])->name('billing-details.store');

        Route::get('payment-method', [PaymentMethodController::class, 'create'])->name('payment-method');
        Route::post('payment-method', [PaymentMethodController::class, 'store'])->name('payment-method.store');

        Route::get('review', [ReviewController::class, 'create'])->name('review');
        Route::post('review', [ReviewController::class, 'store'])->name('review.store');

        Route::get('contact-sales', [ContactSalesController::class, 'show'])->name('contact-sales');

        Route::get('confirmation/{subscription}', [ConfirmationController::class, 'show'])->name('confirmation');
    });
});

require __DIR__.'/auth.php';
