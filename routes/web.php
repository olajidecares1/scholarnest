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
use App\Http\Controllers\SuperAdmin\MediaController;
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
use App\Support\SecureRoute as R;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('throttle:5,1')->group(function () {
    Route::get(R::uri('reports.create'), [ReportController::class, 'create'])->name('reports.create');
    Route::post(R::uri('reports.create'), [ReportController::class, 'store'])->name('reports.store');
});
Route::get(R::uri('reports.confirmation'), [ReportController::class, 'confirmation'])->name('reports.confirmation');

Route::get(R::uri('dashboard'), function () {
    if (auth()->user()->role === UserRole::SuperAdmin) {
        return redirect()->route('super-admin.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get(R::uri('profile.edit'), [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch(R::uri('profile.edit'), [ProfileController::class, 'update'])->name('profile.update');
    Route::delete(R::uri('profile.edit'), [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::name('subscriptions.')->group(function () {
        Route::middleware('school_admin')->group(function () {
            Route::get(R::uri('subscriptions.choose-plan'), [ChoosePlanController::class, 'create'])->name('choose-plan');
            Route::post(R::uri('subscriptions.choose-plan'), [ChoosePlanController::class, 'store'])->name('choose-plan.store');

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

    Route::middleware('school_admin')->name('support-tickets.')->group(function () {
        Route::get(R::uri('support-tickets.index'), [SupportTicketController::class, 'index'])->name('index');
        Route::get(R::uri('support-tickets.create'), [SupportTicketController::class, 'create'])->name('create');
        Route::post(R::uri('support-tickets.index'), [SupportTicketController::class, 'store'])->name('store');
        Route::get(R::uri('support-tickets.show').'/{ticket}', [SupportTicketController::class, 'show'])->name('show');
        Route::post(R::uri('support-tickets.reply').'/{ticket}', [SupportTicketController::class, 'reply'])->name('reply');
    });

    Route::name('notifications.')->group(function () {
        Route::get(R::uri('notifications.read').'/{notification}', [NotificationController::class, 'read'])->name('read');
        Route::post(R::uri('notifications.read-all'), [NotificationController::class, 'readAll'])->name('read-all');
    });

    Route::middleware('super_admin')->name('super-admin.')->group(function () {
        Route::get(R::uri('super-admin.dashboard'), [SuperAdminDashboardController::class, 'index'])->name('dashboard');

        Route::get(R::uri('super-admin.search'), [SearchController::class, 'search'])->name('search');

        Route::name('schools.')->middleware('permission:manage_schools')->group(function () {
            Route::get(R::uri('super-admin.schools.index'), [SchoolController::class, 'index'])->name('index');
            Route::get(R::uri('super-admin.schools.create'), [SchoolController::class, 'create'])->name('create');
            Route::post(R::uri('super-admin.schools.index'), [SchoolController::class, 'store'])->name('store');
            Route::get(R::uri('super-admin.schools.show').'/{school}', [SchoolController::class, 'show'])->name('show');
            Route::post(R::uri('super-admin.schools.activate').'/{school}', [SchoolController::class, 'activate'])->name('activate');
            Route::post(R::uri('super-admin.schools.deactivate').'/{school}', [SchoolController::class, 'deactivate'])->name('deactivate');
        });

        Route::name('subscriptions.')->middleware('permission:manage_subscriptions')->group(function () {
            Route::get(R::uri('super-admin.subscriptions.index'), [SubscriptionApprovalController::class, 'index'])->name('index');
            Route::get(R::uri('super-admin.subscriptions.export'), [SubscriptionApprovalController::class, 'export'])->name('export');
            Route::post(R::uri('super-admin.subscriptions.bulk-approve'), [SubscriptionApprovalController::class, 'bulkApprove'])->name('bulk-approve');
            Route::post(R::uri('super-admin.subscriptions.bulk-reject'), [SubscriptionApprovalController::class, 'bulkReject'])->name('bulk-reject');
            Route::post(R::uri('super-admin.subscriptions.approve').'/{subscription}', [SubscriptionApprovalController::class, 'approve'])->name('approve');
            Route::post(R::uri('super-admin.subscriptions.reject').'/{subscription}', [SubscriptionApprovalController::class, 'reject'])->name('reject');
        });

        Route::get(R::uri('super-admin.payments.index'), [SuperAdminPaymentController::class, 'index'])->name('payments.index')->middleware('permission:manage_payments');

        Route::name('users.')->middleware('permission:manage_users')->group(function () {
            Route::get(R::uri('super-admin.users.index'), [SuperAdminUserController::class, 'index'])->name('index');
            Route::get(R::uri('super-admin.users.create'), [SuperAdminUserController::class, 'create'])->name('create');
            Route::post(R::uri('super-admin.users.index'), [SuperAdminUserController::class, 'store'])->name('store');
            Route::post(R::uri('super-admin.users.activate').'/{user}', [SuperAdminUserController::class, 'activate'])->name('activate');
            Route::post(R::uri('super-admin.users.deactivate').'/{user}', [SuperAdminUserController::class, 'deactivate'])->name('deactivate');
        });

        Route::name('roles.')->middleware('permission:manage_roles')->group(function () {
            Route::get(R::uri('super-admin.roles.index'), [RoleController::class, 'index'])->name('index');
            Route::get(R::uri('super-admin.roles.create'), [RoleController::class, 'create'])->name('create');
            Route::post(R::uri('super-admin.roles.index'), [RoleController::class, 'store'])->name('store');
            Route::get(R::uri('super-admin.roles.team.create'), [RoleController::class, 'createTeamMember'])->name('team.create');
            Route::post(R::uri('super-admin.roles.team.store'), [RoleController::class, 'storeTeamMember'])->name('team.store');
            Route::get(R::uri('super-admin.roles.edit').'/{role}', [RoleController::class, 'edit'])->name('edit');
            Route::put(R::uri('super-admin.roles.update').'/{role}', [RoleController::class, 'update'])->name('update');
            Route::delete(R::uri('super-admin.roles.destroy').'/{role}', [RoleController::class, 'destroy'])->name('destroy');
        });

        Route::name('reports.')->middleware('permission:manage_reports')->group(function () {
            Route::get(R::uri('super-admin.reports.index'), [SuperAdminReportController::class, 'index'])->name('index');
            Route::get(R::uri('super-admin.reports.show').'/{report}', [SuperAdminReportController::class, 'show'])->name('show');
            Route::put(R::uri('super-admin.reports.update').'/{report}', [SuperAdminReportController::class, 'update'])->name('update');
            Route::get(R::uri('super-admin.reports.media').'/{report}', [SuperAdminReportController::class, 'downloadMedia'])->name('media');
        });

        Route::get(R::uri('super-admin.analytics.index'), [AnalyticsController::class, 'index'])->name('analytics.index')->middleware('permission:manage_analytics');

        Route::name('communications.')->middleware('permission:manage_communications')->group(function () {
            Route::get(R::uri('super-admin.communications.index'), [CommunicationController::class, 'index'])->name('index');
            Route::post(R::uri('super-admin.communications.index'), [CommunicationController::class, 'store'])->name('store');
        });

        Route::name('support-tickets.')->middleware('permission:manage_support_tickets')->group(function () {
            Route::get(R::uri('super-admin.support-tickets.index'), [SuperAdminSupportTicketController::class, 'index'])->name('index');
            Route::get(R::uri('super-admin.support-tickets.show').'/{ticket}', [SuperAdminSupportTicketController::class, 'show'])->name('show');
            Route::post(R::uri('super-admin.support-tickets.reply').'/{ticket}', [SuperAdminSupportTicketController::class, 'reply'])->name('reply');
            Route::put(R::uri('super-admin.support-tickets.update').'/{ticket}', [SuperAdminSupportTicketController::class, 'update'])->name('update');
        });

        Route::name('cms.')->middleware('permission:manage_cms')->group(function () {
            Route::get(R::uri('super-admin.cms.index'), [CmsController::class, 'index'])->name('index');

            Route::post(R::uri('super-admin.cms.pages.store'), [CmsController::class, 'storePage'])->name('pages.store');
            Route::put(R::uri('super-admin.cms.pages.update').'/{page}', [CmsController::class, 'updatePage'])->name('pages.update');
            Route::delete(R::uri('super-admin.cms.pages.destroy').'/{page}', [CmsController::class, 'destroyPage'])->name('pages.destroy');

            Route::post(R::uri('super-admin.cms.blog-posts.store'), [CmsController::class, 'storeBlogPost'])->name('blog-posts.store');
            Route::put(R::uri('super-admin.cms.blog-posts.update').'/{blogPost}', [CmsController::class, 'updateBlogPost'])->name('blog-posts.update');
            Route::delete(R::uri('super-admin.cms.blog-posts.destroy').'/{blogPost}', [CmsController::class, 'destroyBlogPost'])->name('blog-posts.destroy');

            Route::post(R::uri('super-admin.cms.testimonials.store'), [CmsController::class, 'storeTestimonial'])->name('testimonials.store');
            Route::put(R::uri('super-admin.cms.testimonials.update').'/{testimonial}', [CmsController::class, 'updateTestimonial'])->name('testimonials.update');
            Route::delete(R::uri('super-admin.cms.testimonials.destroy').'/{testimonial}', [CmsController::class, 'destroyTestimonial'])->name('testimonials.destroy');

            Route::post(R::uri('super-admin.cms.faq-items.store'), [CmsController::class, 'storeFaqItem'])->name('faq-items.store');
            Route::put(R::uri('super-admin.cms.faq-items.update').'/{faqItem}', [CmsController::class, 'updateFaqItem'])->name('faq-items.update');
            Route::delete(R::uri('super-admin.cms.faq-items.destroy').'/{faqItem}', [CmsController::class, 'destroyFaqItem'])->name('faq-items.destroy');

            Route::post(R::uri('super-admin.cms.team-members.store'), [CmsController::class, 'storeTeamMember'])->name('team-members.store');
            Route::put(R::uri('super-admin.cms.team-members.update').'/{teamMember}', [CmsController::class, 'updateTeamMember'])->name('team-members.update');
            Route::delete(R::uri('super-admin.cms.team-members.destroy').'/{teamMember}', [CmsController::class, 'destroyTeamMember'])->name('team-members.destroy');
        });

        Route::name('themes.')->middleware('permission:manage_themes')->group(function () {
            Route::get(R::uri('super-admin.themes.index'), [ThemeController::class, 'edit'])->name('index');
            Route::put(R::uri('super-admin.themes.index'), [ThemeController::class, 'update'])->name('update');
            Route::post(R::uri('super-admin.themes.logo.update'), [ThemeController::class, 'updateLogo'])->name('logo.update');
            Route::post(R::uri('super-admin.themes.favicon.update'), [ThemeController::class, 'updateFavicon'])->name('favicon.update');
        });

        Route::name('media.')->middleware('permission:manage_media')->group(function () {
            Route::get(R::uri('super-admin.media.index'), [MediaController::class, 'index'])->name('index');
            Route::post(R::uri('super-admin.media.index'), [MediaController::class, 'store'])->name('store');
            Route::put(R::uri('super-admin.media.backgrounds.update'), [MediaController::class, 'updateBackgrounds'])->name('backgrounds.update');
            Route::put(R::uri('super-admin.media.update').'/{media}', [MediaController::class, 'update'])->name('update');
            Route::post(R::uri('super-admin.media.replace').'/{media}', [MediaController::class, 'replace'])->name('replace');
            Route::delete(R::uri('super-admin.media.destroy').'/{media}', [MediaController::class, 'destroy'])->name('destroy');
        });

        Route::get(R::uri('super-admin.settings.index'), [SettingsController::class, 'edit'])->name('settings.index')->middleware('permission:manage_settings');
        Route::put(R::uri('super-admin.settings.index'), [SettingsController::class, 'update'])->name('settings.update')->middleware('permission:manage_settings');

        Route::get(R::uri('super-admin.audit-logs.index'), [AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('permission:manage_audit_logs');
    });
});

require __DIR__.'/auth.php';
