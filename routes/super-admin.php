<?php

use App\Http\Controllers\SuperAdmin\AnalyticsController;
use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\CbtDocumentUploadController;
use App\Http\Controllers\SuperAdmin\CbtExamBodyController;
use App\Http\Controllers\SuperAdmin\CbtExamController;
use App\Http\Controllers\SuperAdmin\CbtQuestionController;
use App\Http\Controllers\SuperAdmin\CbtSubjectController;
use App\Http\Controllers\SuperAdmin\CmsController;
use App\Http\Controllers\SuperAdmin\CommunicationController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\DocumentTemplateController;
use App\Http\Controllers\SuperAdmin\LegalDocumentController as SuperAdminLegalDocumentController;
use App\Http\Controllers\SuperAdmin\MediaController;
use App\Http\Controllers\SuperAdmin\PaymentController as SuperAdminPaymentController;
use App\Http\Controllers\SuperAdmin\PaymentReceiptController;
use App\Http\Controllers\SuperAdmin\PaymentSettingsController;
use App\Http\Controllers\SuperAdmin\PlanPricingController;
use App\Http\Controllers\SuperAdmin\ReportController as SuperAdminReportController;
use App\Http\Controllers\SuperAdmin\ResultPinController;
use App\Http\Controllers\SuperAdmin\RoleController;
use App\Http\Controllers\SuperAdmin\SchoolController;
use App\Http\Controllers\SuperAdmin\SearchController;
use App\Http\Controllers\SuperAdmin\SettingsController;
use App\Http\Controllers\SuperAdmin\SubscriptionApprovalController;
use App\Http\Controllers\SuperAdmin\SupportTicketController as SuperAdminSupportTicketController;
use App\Http\Controllers\SuperAdmin\ThemeController;
use App\Http\Controllers\SuperAdmin\UserController as SuperAdminUserController;
use App\Support\SecureRoute as R;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin
|--------------------------------------------------------------------------
|
| Grouped from routes/authenticated.php behind ['auth', 'auth.session',
| 'super_admin'] and the 'super-admin.' name prefix.
|
*/

Route::get(R::uri('super-admin.dashboard'), [SuperAdminDashboardController::class, 'index'])->name('dashboard');

Route::get(R::uri('super-admin.search'), [SearchController::class, 'search'])->name('search');

Route::name('schools.')->middleware('permission:manage_schools')->group(function () {
    Route::get(R::uri('super-admin.schools.index'), [SchoolController::class, 'index'])->name('index');
    Route::get(R::uri('super-admin.schools.create'), [SchoolController::class, 'create'])->name('create');
    Route::post(R::uri('super-admin.schools.index'), [SchoolController::class, 'store'])->name('store');
    Route::get(R::uri('super-admin.schools.show').'/{school}', [SchoolController::class, 'show'])->name('show');
    Route::post(R::uri('super-admin.schools.activate').'/{school}', [SchoolController::class, 'activate'])->name('activate');
    Route::post(R::uri('super-admin.schools.deactivate').'/{school}', [SchoolController::class, 'deactivate'])->name('deactivate');

    // Deleting a school erases everything belonging to it - students,
    // staff, results, invoices, 43 tables in all - and cannot be
    // undone. Deactivating is the reversible option and is what the
    // interface offers first; this exists for schools that were never
    // real, such as test records and abandoned registrations.
    Route::delete(R::uri('super-admin.schools.destroy').'/{school}', [SchoolController::class, 'destroy'])->name('destroy');
});

Route::name('subscriptions.')->middleware('permission:manage_subscriptions')->group(function () {
    Route::get(R::uri('super-admin.subscriptions.index'), [SubscriptionApprovalController::class, 'index'])->name('index');
    Route::get(R::uri('super-admin.subscriptions.export'), [SubscriptionApprovalController::class, 'export'])->name('export');
    Route::post(R::uri('super-admin.subscriptions.bulk-approve'), [SubscriptionApprovalController::class, 'bulkApprove'])->name('bulk-approve');
    Route::post(R::uri('super-admin.subscriptions.bulk-reject'), [SubscriptionApprovalController::class, 'bulkReject'])->name('bulk-reject');

    // Reviewing one subscription without leaving the Super Admin panel.
    // The school's own signup confirmation is not a Super Admin screen
    // and must not be where "View" leads.
    Route::get(R::uri('super-admin.subscriptions.show').'/{subscription}', [SubscriptionApprovalController::class, 'show'])->name('show');
    Route::get(R::uri('super-admin.subscriptions.receipt').'/{subscription}', [PaymentReceiptController::class, 'showSubscriptionReceipt'])->name('receipt');
    Route::post(R::uri('super-admin.subscriptions.approve').'/{subscription}', [SubscriptionApprovalController::class, 'approve'])->name('approve');
    Route::post(R::uri('super-admin.subscriptions.reject').'/{subscription}', [SubscriptionApprovalController::class, 'reject'])->name('reject');
    Route::post(R::uri('super-admin.subscriptions.top-ups.approve').'/{topUp}', [SubscriptionApprovalController::class, 'approveTopUp'])->name('top-ups.approve');
    Route::post(R::uri('super-admin.subscriptions.top-ups.reject').'/{topUp}', [SubscriptionApprovalController::class, 'rejectTopUp'])->name('top-ups.reject');

    // Receipts live on the private disk, so they are streamed through
    // the application behind this middleware rather than served from
    // public storage where anyone guessing a filename could read them.
    Route::get(R::uri('super-admin.subscriptions.top-ups.receipt').'/{topUp}', [PaymentReceiptController::class, 'showTopUpReceipt'])->name('top-ups.receipt');
});

Route::name('result-pins.')->middleware('permission:manage_result_pins')->group(function () {
    Route::get(R::uri('super-admin.result-pins.index'), [ResultPinController::class, 'index'])->name('index');
    Route::get(R::uri('super-admin.result-pins.show').'/{school}', [ResultPinController::class, 'show'])->name('show');

    // No "generate" any more. Schools issue their own tokens, because
    // a token has to name its student and its examination and only the
    // school knows those. The platform keeps oversight and the power to
    // revoke.
    Route::post(R::uri('super-admin.result-pins.revoke').'/{pin}', [ResultPinController::class, 'revoke'])->name('revoke');

    // Global token settings: the defaults every school's newly issued
    // tokens inherit. Point 16 of the rule - oversight includes being
    // able to move the baseline without editing code.
    Route::put(R::uri('super-admin.result-pins.settings'), [ResultPinController::class, 'updateSettings'])->name('settings');
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

/*
 * The platform's own legal documents.
 *
 * Its OWN permission rather than manage_cms, and that distinction is the point:
 * a blog post is marketing copy, and the Terms & Conditions are the contract
 * every school on the platform has agreed to. Somebody trusted to write the
 * former is not automatically trusted to alter the latter.
 *
 * Bound by slug, so these read .../legal/privacy and line up with the public
 * URL rather than carrying a row id.
 */
Route::name('legal.')->middleware('permission:manage_legal')->group(function () {
    Route::get(R::uri('super-admin.legal.index'), [SuperAdminLegalDocumentController::class, 'index'])->name('index');
    Route::get(R::uri('super-admin.legal.edit').'/{document}', [SuperAdminLegalDocumentController::class, 'edit'])->name('edit');
    Route::put(R::uri('super-admin.legal.update').'/{document}', [SuperAdminLegalDocumentController::class, 'update'])->name('update');
});

Route::name('cbt.')->middleware('permission:manage_cbt')->group(function () {
    Route::get(R::uri('super-admin.cbt.index'), [CbtExamBodyController::class, 'index'])->name('index');

    Route::name('exam-bodies.')->group(function () {
        Route::post(R::uri('super-admin.cbt.exam-bodies.store'), [CbtExamBodyController::class, 'store'])->name('store');
        Route::get(R::uri('super-admin.cbt.exam-bodies.show').'/{examBody}', [CbtExamBodyController::class, 'show'])->name('show');
        Route::put(R::uri('super-admin.cbt.exam-bodies.update').'/{examBody}', [CbtExamBodyController::class, 'update'])->name('update');
        Route::put(R::uri('super-admin.cbt.exam-bodies.subjects').'/{examBody}', [CbtExamBodyController::class, 'updateSubjects'])->name('subjects.update');
        Route::delete(R::uri('super-admin.cbt.exam-bodies.destroy').'/{examBody}', [CbtExamBodyController::class, 'destroy'])->name('destroy');
    });

    Route::name('subjects.')->group(function () {
        Route::post(R::uri('super-admin.cbt.subjects.store'), [CbtSubjectController::class, 'store'])->name('store');
        Route::put(R::uri('super-admin.cbt.subjects.update').'/{subject}', [CbtSubjectController::class, 'update'])->name('update');
        Route::delete(R::uri('super-admin.cbt.subjects.destroy').'/{subject}', [CbtSubjectController::class, 'destroy'])->name('destroy');
    });

    Route::name('exams.')->group(function () {
        Route::post(R::uri('super-admin.cbt.exams.store').'/{examBody}', [CbtExamController::class, 'store'])->name('store');
        Route::get(R::uri('super-admin.cbt.exams.show').'/{exam}', [CbtExamController::class, 'show'])->name('show');
        Route::put(R::uri('super-admin.cbt.exams.update').'/{exam}', [CbtExamController::class, 'update'])->name('update');
        Route::delete(R::uri('super-admin.cbt.exams.destroy').'/{exam}', [CbtExamController::class, 'destroy'])->name('destroy');
    });

    Route::name('questions.')->group(function () {
        Route::post(R::uri('super-admin.cbt.questions.store').'/{exam}', [CbtQuestionController::class, 'store'])->name('store');
        Route::put(R::uri('super-admin.cbt.questions.update').'/{question}', [CbtQuestionController::class, 'update'])->name('update');
        Route::delete(R::uri('super-admin.cbt.questions.destroy').'/{question}', [CbtQuestionController::class, 'destroy'])->name('destroy');
    });

    Route::name('uploads.')->group(function () {
        Route::get(R::uri('super-admin.cbt.uploads.index'), [CbtDocumentUploadController::class, 'index'])->name('index');
        Route::post(R::uri('super-admin.cbt.uploads.store'), [CbtDocumentUploadController::class, 'store'])->name('store');
        Route::get(R::uri('super-admin.cbt.uploads.show').'/{upload}', [CbtDocumentUploadController::class, 'show'])->name('show');
        Route::get(R::uri('super-admin.cbt.uploads.show').'/{upload}/status', [CbtDocumentUploadController::class, 'status'])->name('status');
        Route::post(R::uri('super-admin.cbt.uploads.retry').'/{upload}', [CbtDocumentUploadController::class, 'retry'])->name('retry');
        Route::put(R::uri('super-admin.cbt.uploads.mapping').'/{upload}', [CbtDocumentUploadController::class, 'confirmMapping'])->name('mapping');
        Route::delete(R::uri('super-admin.cbt.uploads.destroy').'/{upload}', [CbtDocumentUploadController::class, 'destroy'])->name('destroy');
    });
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

/*
 * Payment settings: how schools may pay, and what they pay into.
 *
 * Behind the same manage_settings permission as the rest of the
 * platform's configuration - this decides where money is sent.
 */
Route::middleware('permission:manage_settings')->name('payment-settings.')->group(function () {
    Route::get(R::uri('super-admin.payment-settings.index'), [PaymentSettingsController::class, 'index'])->name('index');
    Route::put(R::uri('super-admin.payment-settings.update').'/{method}', [PaymentSettingsController::class, 'update'])->name('update');
    Route::post(R::uri('super-admin.payment-settings.toggle').'/{method}/toggle', [PaymentSettingsController::class, 'toggle'])->name('toggle');
});

// Plan pricing, including the Basic plan's per-student price - the
// figure the whole student-licence system multiplies by. Kept out of
// the source so it can be changed without a deployment.
Route::name('plans.')->middleware('permission:manage_settings')->group(function () {
    Route::get(R::uri('super-admin.plans.pricing'), [PlanPricingController::class, 'edit'])->name('pricing.edit');
    Route::put(R::uri('super-admin.plans.pricing').'/{plan}', [PlanPricingController::class, 'update'])->name('pricing.update');
});

Route::get(R::uri('super-admin.audit-logs.index'), [AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('permission:manage_audit_logs');

// The report card and ID card as schools receive them. Read-only, and behind
// manage_settings because it can render any school's real crest and details.
Route::get(R::uri('super-admin.document-templates.index'), [DocumentTemplateController::class, 'index'])
    ->name('document-templates.index')
    ->middleware('permission:manage_settings');
