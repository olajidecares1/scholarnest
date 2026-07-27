<?php

use App\Enums\UserRole;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Subscriptions\BillingDetailsController;
use App\Http\Controllers\Subscriptions\ChoosePlanController;
use App\Http\Controllers\Subscriptions\ConfirmationController;
use App\Http\Controllers\Subscriptions\ContactSalesController;
use App\Http\Controllers\Subscriptions\PaymentMethodController;
use App\Http\Controllers\Subscriptions\ReviewController;
use App\Http\Controllers\SuperAdmin\AnalyticsController;
use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\CmsController;
use App\Http\Controllers\SuperAdmin\CommunicationController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\PaymentController as SuperAdminPaymentController;
use App\Http\Controllers\SuperAdmin\ReportController as SuperAdminReportController;
use App\Http\Controllers\SuperAdmin\RoleController;
use App\Http\Controllers\SuperAdmin\SchoolController;
use App\Http\Controllers\SuperAdmin\SearchController;
use App\Http\Controllers\SuperAdmin\SettingsController;
use App\Http\Controllers\SuperAdmin\SubscriptionApprovalController;
use App\Http\Controllers\SuperAdmin\SupportTicketController as SuperAdminSupportTicketController;
use App\Http\Controllers\SuperAdmin\ThemeController;
use App\Http\Controllers\SuperAdmin\UserController as SuperAdminUserController;
use App\Http\Controllers\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('throttle:5,1')->group(function () {
    Route::get('report-misconduct', [ReportController::class, 'create'])->name('reports.create');
    Route::post('report-misconduct', [ReportController::class, 'store'])->name('reports.store');
});
Route::get('report-misconduct/confirmation', [ReportController::class, 'confirmation'])->name('reports.confirmation');

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

    Route::middleware('school_admin')->prefix('support-tickets')->name('support-tickets.')->group(function () {
        Route::get('/', [SupportTicketController::class, 'index'])->name('index');
        Route::get('create', [SupportTicketController::class, 'create'])->name('create');
        Route::post('/', [SupportTicketController::class, 'store'])->name('store');
        Route::get('{ticket}', [SupportTicketController::class, 'show'])->name('show');
        Route::post('{ticket}/reply', [SupportTicketController::class, 'reply'])->name('reply');
    });

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('{notification}/read', [NotificationController::class, 'read'])->name('read');
        Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
    });

    Route::middleware('super_admin')->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('search', [SearchController::class, 'search'])->name('search');

        Route::prefix('schools')->name('schools.')->middleware('permission:manage_schools')->group(function () {
            Route::get('/', [SchoolController::class, 'index'])->name('index');
            Route::get('create', [SchoolController::class, 'create'])->name('create');
            Route::post('/', [SchoolController::class, 'store'])->name('store');
            Route::get('{school}', [SchoolController::class, 'show'])->name('show');
            Route::post('{school}/activate', [SchoolController::class, 'activate'])->name('activate');
            Route::post('{school}/deactivate', [SchoolController::class, 'deactivate'])->name('deactivate');
        });

        Route::prefix('subscriptions')->name('subscriptions.')->middleware('permission:manage_subscriptions')->group(function () {
            Route::get('/', [SubscriptionApprovalController::class, 'index'])->name('index');
            Route::get('export', [SubscriptionApprovalController::class, 'export'])->name('export');
            Route::post('bulk-approve', [SubscriptionApprovalController::class, 'bulkApprove'])->name('bulk-approve');
            Route::post('bulk-reject', [SubscriptionApprovalController::class, 'bulkReject'])->name('bulk-reject');
            Route::post('{subscription}/approve', [SubscriptionApprovalController::class, 'approve'])->name('approve');
            Route::post('{subscription}/reject', [SubscriptionApprovalController::class, 'reject'])->name('reject');
        });

        Route::get('payments', [SuperAdminPaymentController::class, 'index'])->name('payments.index')->middleware('permission:manage_payments');

        Route::prefix('users')->name('users.')->middleware('permission:manage_users')->group(function () {
            Route::get('/', [SuperAdminUserController::class, 'index'])->name('index');
            Route::get('create', [SuperAdminUserController::class, 'create'])->name('create');
            Route::post('/', [SuperAdminUserController::class, 'store'])->name('store');
            Route::post('{user}/activate', [SuperAdminUserController::class, 'activate'])->name('activate');
            Route::post('{user}/deactivate', [SuperAdminUserController::class, 'deactivate'])->name('deactivate');
        });

        Route::prefix('roles')->name('roles.')->middleware('permission:manage_roles')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->name('index');
            Route::get('create', [RoleController::class, 'create'])->name('create');
            Route::post('/', [RoleController::class, 'store'])->name('store');
            Route::get('team/create', [RoleController::class, 'createTeamMember'])->name('team.create');
            Route::post('team', [RoleController::class, 'storeTeamMember'])->name('team.store');
            Route::get('{role}/edit', [RoleController::class, 'edit'])->name('edit');
            Route::put('{role}', [RoleController::class, 'update'])->name('update');
            Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('reports')->name('reports.')->middleware('permission:manage_reports')->group(function () {
            Route::get('/', [SuperAdminReportController::class, 'index'])->name('index');
            Route::get('{report}', [SuperAdminReportController::class, 'show'])->name('show');
            Route::put('{report}', [SuperAdminReportController::class, 'update'])->name('update');
            Route::get('{report}/media', [SuperAdminReportController::class, 'downloadMedia'])->name('media');
        });
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index')->middleware('permission:manage_analytics');
        Route::prefix('communications')->name('communications.')->middleware('permission:manage_communications')->group(function () {
            Route::get('/', [CommunicationController::class, 'index'])->name('index');
            Route::post('/', [CommunicationController::class, 'store'])->name('store');
        });
        Route::prefix('support-tickets')->name('support-tickets.')->middleware('permission:manage_support_tickets')->group(function () {
            Route::get('/', [SuperAdminSupportTicketController::class, 'index'])->name('index');
            Route::get('{ticket}', [SuperAdminSupportTicketController::class, 'show'])->name('show');
            Route::post('{ticket}/reply', [SuperAdminSupportTicketController::class, 'reply'])->name('reply');
            Route::put('{ticket}', [SuperAdminSupportTicketController::class, 'update'])->name('update');
        });
        Route::prefix('cms')->name('cms.')->middleware('permission:manage_cms')->group(function () {
            Route::get('/', [CmsController::class, 'index'])->name('index');

            Route::post('pages', [CmsController::class, 'storePage'])->name('pages.store');
            Route::put('pages/{page}', [CmsController::class, 'updatePage'])->name('pages.update');
            Route::delete('pages/{page}', [CmsController::class, 'destroyPage'])->name('pages.destroy');

            Route::post('blog-posts', [CmsController::class, 'storeBlogPost'])->name('blog-posts.store');
            Route::put('blog-posts/{blogPost}', [CmsController::class, 'updateBlogPost'])->name('blog-posts.update');
            Route::delete('blog-posts/{blogPost}', [CmsController::class, 'destroyBlogPost'])->name('blog-posts.destroy');

            Route::post('testimonials', [CmsController::class, 'storeTestimonial'])->name('testimonials.store');
            Route::put('testimonials/{testimonial}', [CmsController::class, 'updateTestimonial'])->name('testimonials.update');
            Route::delete('testimonials/{testimonial}', [CmsController::class, 'destroyTestimonial'])->name('testimonials.destroy');

            Route::post('faq-items', [CmsController::class, 'storeFaqItem'])->name('faq-items.store');
            Route::put('faq-items/{faqItem}', [CmsController::class, 'updateFaqItem'])->name('faq-items.update');
            Route::delete('faq-items/{faqItem}', [CmsController::class, 'destroyFaqItem'])->name('faq-items.destroy');

            Route::post('team-members', [CmsController::class, 'storeTeamMember'])->name('team-members.store');
            Route::put('team-members/{teamMember}', [CmsController::class, 'updateTeamMember'])->name('team-members.update');
            Route::delete('team-members/{teamMember}', [CmsController::class, 'destroyTeamMember'])->name('team-members.destroy');
        });
        Route::prefix('themes')->name('themes.')->middleware('permission:manage_themes')->group(function () {
            Route::get('/', [ThemeController::class, 'edit'])->name('index');
            Route::put('/', [ThemeController::class, 'update'])->name('update');
            Route::post('logo', [ThemeController::class, 'updateLogo'])->name('logo.update');
            Route::post('favicon', [ThemeController::class, 'updateFavicon'])->name('favicon.update');
        });

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.index')->middleware('permission:manage_settings');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update')->middleware('permission:manage_settings');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('permission:manage_audit_logs');
    });
});

require __DIR__.'/auth.php';
