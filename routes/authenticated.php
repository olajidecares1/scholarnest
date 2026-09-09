<?php

use App\Enums\UserRole;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SchoolAdmin\DashboardController as SchoolAdminDashboardController;
use App\Http\Controllers\SubscriptionInvoiceController;
use App\Http\Controllers\Subscriptions\BillingDetailsController;
use App\Http\Controllers\Subscriptions\ChoosePlanController;
use App\Http\Controllers\Subscriptions\ConfirmationController;
use App\Http\Controllers\Subscriptions\ContactSalesController;
use App\Http\Controllers\Subscriptions\PaymentMethodController;
use App\Http\Controllers\Subscriptions\ReviewController;
use App\Http\Controllers\Subscriptions\StudentCapacityController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Middleware\EnsureUserIsSchoolAdmin;
use App\Support\SecureRoute as R;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Signed-in AkademicNest accounts
|--------------------------------------------------------------------------
|
| The `web` guard: School Admins and the Super Admin. The two large areas
| are files of their own, grouped from here so their middleware and name
| prefix are stated once rather than on every route.
|
*/

Route::get(R::uri('dashboard'), function (Request $request) {
    if (auth()->user()->role === UserRole::SuperAdmin) {
        return redirect()->route('super-admin.dashboard');
    }

    // This route is the one place a School Admin lands that is NOT behind the
    // school_admin middleware, so the orphan check has to be repeated here.
    // An account whose school has been deleted keeps a valid session, and
    // every school page reaches for the school immediately - so without this
    // it is a fatal error rather than a sign-out.
    if ($request->user()->school === null) {
        return EnsureUserIsSchoolAdmin::signOutOrphan($request);
    }

    return app(SchoolAdminDashboardController::class)->index($request);
})->middleware(['auth', 'verified', 'auth.session'])->name('dashboard');

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::get(R::uri('profile.edit'), [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch(R::uri('profile.edit'), [ProfileController::class, 'update'])->name('profile.update');
    Route::delete(R::uri('profile.edit'), [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::name('subscriptions.')->group(function () {
        Route::middleware('school_admin')->group(function () {
            Route::get(R::uri('subscriptions.choose-plan'), [ChoosePlanController::class, 'create'])->name('choose-plan');
            Route::post(R::uri('subscriptions.choose-plan'), [ChoosePlanController::class, 'store'])->name('choose-plan.store');

            // How many students, and what that costs. Its own step because for
            // a Basic school this number decides both the price and the
            // capacity it lives with for the term.
            Route::get(R::uri('subscriptions.students'), [StudentCapacityController::class, 'create'])->name('students');
            Route::post(R::uri('subscriptions.students'), [StudentCapacityController::class, 'store'])->name('students.store');

            Route::get(R::uri('subscriptions.billing-details'), [BillingDetailsController::class, 'create'])->name('billing-details');
            Route::post(R::uri('subscriptions.billing-details'), [BillingDetailsController::class, 'store'])->name('billing-details.store');

            Route::get(R::uri('subscriptions.payment-method'), [PaymentMethodController::class, 'create'])->name('payment-method');
            Route::post(R::uri('subscriptions.payment-method'), [PaymentMethodController::class, 'store'])->name('payment-method.store');

            Route::get(R::uri('subscriptions.review'), [ReviewController::class, 'create'])->name('review');
            Route::post(R::uri('subscriptions.review'), [ReviewController::class, 'store'])->name('review.store');

            Route::get(R::uri('subscriptions.contact-sales'), [ContactSalesController::class, 'show'])->name('contact-sales');
        });

        Route::get(R::uri('subscriptions.confirmation').'/{subscription}', [ConfirmationController::class, 'show'])->name('confirmation');
    });

    // Invoices, for whoever is signed in. Authorised against the account's own
    // school inside the controller - a School Admin reaches their school's
    // invoices and no others.
    Route::get(R::uri('invoices.index'), [SubscriptionInvoiceController::class, 'index'])
        ->middleware('school_admin')
        ->name('invoices.index');

    Route::get(R::uri('invoices.download').'/{invoice}', [SubscriptionInvoiceController::class, 'download'])
        ->name('invoices.download');

    Route::middleware('school_admin')->name('support-tickets.')->group(function () {
        Route::get(R::uri('support-tickets.index'), [SupportTicketController::class, 'index'])->name('index');
        Route::get(R::uri('support-tickets.create'), [SupportTicketController::class, 'create'])->name('create');
        Route::post(R::uri('support-tickets.index'), [SupportTicketController::class, 'store'])->name('store');
        Route::get(R::uri('support-tickets.show').'/{ticket}', [SupportTicketController::class, 'show'])->name('show');
        Route::post(R::uri('support-tickets.reply').'/{ticket}', [SupportTicketController::class, 'reply'])->name('reply');
    });

    Route::middleware(['school_admin', 'school_activated'])
        ->group(base_path('routes/school-admin.php'));

    Route::name('notifications.')->group(function () {
        Route::get(R::uri('notifications.read').'/{notification}', [NotificationController::class, 'read'])->name('read');
        Route::post(R::uri('notifications.read-all'), [NotificationController::class, 'readAll'])->name('read-all');
    });

    Route::middleware('super_admin')->name('super-admin.')
        ->group(base_path('routes/super-admin.php'));
});
