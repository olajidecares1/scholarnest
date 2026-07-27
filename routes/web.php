<?php

use App\Enums\UserRole;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Subscriptions\BillingDetailsController;
use App\Http\Controllers\Subscriptions\ChoosePlanController;
use App\Http\Controllers\Subscriptions\ConfirmationController;
use App\Http\Controllers\Subscriptions\ContactSalesController;
use App\Http\Controllers\Subscriptions\PaymentMethodController;
use App\Http\Controllers\Subscriptions\ReviewController;
use App\Http\Controllers\SuperAdmin\ComingSoonController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\PaymentController as SuperAdminPaymentController;
use App\Http\Controllers\SuperAdmin\SchoolController;
use App\Http\Controllers\SuperAdmin\SubscriptionApprovalController;
use App\Http\Controllers\SuperAdmin\UserController as SuperAdminUserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    if (auth()->user()->role === UserRole::SuperAdmin) {
        return redirect()->route('super-admin.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
        Route::middleware('school_admin')->group(function () {
            Route::get('choose-plan', [ChoosePlanController::class, 'create'])->name('choose-plan');
            Route::post('choose-plan', [ChoosePlanController::class, 'store'])->name('choose-plan.store');

            Route::get('billing-details', [BillingDetailsController::class, 'create'])->name('billing-details');
            Route::post('billing-details', [BillingDetailsController::class, 'store'])->name('billing-details.store');

            Route::get('payment-method', [PaymentMethodController::class, 'create'])->name('payment-method');
            Route::post('payment-method', [PaymentMethodController::class, 'store'])->name('payment-method.store');

            Route::get('review', [ReviewController::class, 'create'])->name('review');
            Route::post('review', [ReviewController::class, 'store'])->name('review.store');

            Route::get('contact-sales', [ContactSalesController::class, 'show'])->name('contact-sales');
        });

        Route::get('confirmation/{subscription}', [ConfirmationController::class, 'show'])->name('confirmation');
    });

    Route::middleware('super_admin')->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

        Route::prefix('schools')->name('schools.')->group(function () {
            Route::get('/', [SchoolController::class, 'index'])->name('index');
            Route::post('{school}/activate', [SchoolController::class, 'activate'])->name('activate');
            Route::post('{school}/deactivate', [SchoolController::class, 'deactivate'])->name('deactivate');
        });

        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('/', [SubscriptionApprovalController::class, 'index'])->name('index');
            Route::post('{subscription}/approve', [SubscriptionApprovalController::class, 'approve'])->name('approve');
            Route::post('{subscription}/reject', [SubscriptionApprovalController::class, 'reject'])->name('reject');
        });

        Route::get('payments', [SuperAdminPaymentController::class, 'index'])->name('payments.index');

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [SuperAdminUserController::class, 'index'])->name('index');
            Route::post('{user}/activate', [SuperAdminUserController::class, 'activate'])->name('activate');
            Route::post('{user}/deactivate', [SuperAdminUserController::class, 'deactivate'])->name('deactivate');
        });

        Route::get('reports', [ComingSoonController::class, 'show'])->name('reports.index');
        Route::get('analytics', [ComingSoonController::class, 'show'])->name('analytics.index');
        Route::get('communications', [ComingSoonController::class, 'show'])->name('communications.index');
        Route::get('support-tickets', [ComingSoonController::class, 'show'])->name('support-tickets.index');
        Route::get('cms', [ComingSoonController::class, 'show'])->name('cms.index');
        Route::get('settings', [ComingSoonController::class, 'show'])->name('settings.index');
        Route::get('audit-logs', [ComingSoonController::class, 'show'])->name('audit-logs.index');
    });
});

require __DIR__.'/auth.php';
