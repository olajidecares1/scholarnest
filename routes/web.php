<?php

use App\Enums\UserRole;
use App\Http\Controllers\CheckResultController;
use App\Http\Controllers\Guardian\AssignmentController as GuardianAssignmentController;
use App\Http\Controllers\Guardian\AttendanceController as GuardianAttendanceController;
use App\Http\Controllers\Guardian\Auth\AuthenticatedSessionController as GuardianAuthenticatedSessionController;
use App\Http\Controllers\Guardian\ChildProfileController as GuardianChildProfileController;
use App\Http\Controllers\Guardian\DashboardController as GuardianDashboardController;
use App\Http\Controllers\Guardian\FeeController as GuardianFeeController;
use App\Http\Controllers\Guardian\HelpController as GuardianHelpController;
use App\Http\Controllers\Guardian\MessageController as GuardianMessageController;
use App\Http\Controllers\Guardian\NotificationController as GuardianNotificationController;
use App\Http\Controllers\Guardian\PortalLockedController as GuardianPortalLockedController;
use App\Http\Controllers\Guardian\ProfileChangeRequestController as GuardianProfileChangeRequestController;
use App\Http\Controllers\Guardian\ResultController as GuardianResultController;
use App\Http\Controllers\Guardian\SettingsController as GuardianSettingsController;
use App\Http\Controllers\Guardian\TimetableController as GuardianTimetableController;
use App\Http\Controllers\IdCardVerificationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Portal\AuthenticatedSessionController as PortalAuthenticatedSessionController;
use App\Http\Controllers\Portal\Basic\SchoolFinderController as BasicSchoolFinderController;
use App\Http\Controllers\Portal\Basic\SchoolLandingController as BasicSchoolLandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSchoolWebsiteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchoolAdmin\AcademicController;
use App\Http\Controllers\SchoolAdmin\AssignmentController;
use App\Http\Controllers\SchoolAdmin\AttendanceController;
use App\Http\Controllers\SchoolAdmin\CbtPracticeController;
use App\Http\Controllers\SchoolAdmin\CbtTestController as CbtTestOversightController;
use App\Http\Controllers\SchoolAdmin\ClassSubjectController;
use App\Http\Controllers\SchoolAdmin\CoCurricularController;
use App\Http\Controllers\SchoolAdmin\CommunicationController as SchoolCommunicationController;
use App\Http\Controllers\SchoolAdmin\CustomDomainController;
use App\Http\Controllers\SchoolAdmin\DashboardController as SchoolAdminDashboardController;
use App\Http\Controllers\SchoolAdmin\EventController;
use App\Http\Controllers\SchoolAdmin\ExaminationController;
use App\Http\Controllers\SchoolAdmin\FacilityController;
use App\Http\Controllers\SchoolAdmin\FinanceController;
use App\Http\Controllers\SchoolAdmin\GuardianController;
use App\Http\Controllers\SchoolAdmin\HostelController;
use App\Http\Controllers\SchoolAdmin\IdCardController;
use App\Http\Controllers\SchoolAdmin\IdCardTemplateController;
use App\Http\Controllers\SchoolAdmin\IssuedIdCardController;
use App\Http\Controllers\SchoolAdmin\JobPostingController;
use App\Http\Controllers\SchoolAdmin\LibraryController;
use App\Http\Controllers\SchoolAdmin\NewsController;
use App\Http\Controllers\SchoolAdmin\NoticeController;
use App\Http\Controllers\SchoolAdmin\ProfileChangeRequestController;
use App\Http\Controllers\SchoolAdmin\ResultCheckingPinController;
use App\Http\Controllers\SchoolAdmin\ResultController;
use App\Http\Controllers\SchoolAdmin\SchoolReportController;
use App\Http\Controllers\SchoolAdmin\SearchController as SchoolSearchController;
use App\Http\Controllers\SchoolAdmin\SettingsController as SchoolSettingsController;
use App\Http\Controllers\SchoolAdmin\StaffController;
use App\Http\Controllers\SchoolAdmin\StudentController;
use App\Http\Controllers\SchoolAdmin\SubscriptionTopUpController;
use App\Http\Controllers\SchoolAdmin\TeacherAssignmentController;
use App\Http\Controllers\SchoolAdmin\TestimonialController;
use App\Http\Controllers\SchoolAdmin\TimetableController;
use App\Http\Controllers\SchoolAdmin\TransportController;
use App\Http\Controllers\SchoolAdmin\WebsiteController;
use App\Http\Controllers\SchoolPortal\Auth\AuthenticatedSessionController as SchoolPortalAuthenticatedSessionController;
use App\Http\Controllers\SchoolPortalController;
use App\Http\Controllers\Staff\AttendanceController as StaffAttendanceController;
use App\Http\Controllers\Staff\Auth\AuthenticatedSessionController as StaffAuthenticatedSessionController;
use App\Http\Controllers\Staff\Cbt\DocumentUploadController as StaffCbtDocumentUploadController;
use App\Http\Controllers\Staff\Cbt\QuestionController as StaffCbtQuestionController;
use App\Http\Controllers\Staff\Cbt\TestController as StaffCbtTestController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\ExaminationController as StaffExaminationController;
use App\Http\Controllers\Staff\HelpController as StaffHelpController;
use App\Http\Controllers\Staff\IdCardController as StaffIdCardController;
use App\Http\Controllers\Staff\PortalLockedController as StaffPortalLockedController;
use App\Http\Controllers\Staff\ProfileChangeRequestController as StaffProfileChangeRequestController;
use App\Http\Controllers\Staff\ProfileController as StaffProfileController;
use App\Http\Controllers\Staff\ResultController as StaffResultController;
use App\Http\Controllers\Staff\SettingsController as StaffSettingsController;
use App\Http\Controllers\Staff\TimetableController as StaffTimetableController;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\AttendanceController as StudentAttendanceController;
use App\Http\Controllers\Student\Auth\AuthenticatedSessionController as StudentAuthenticatedSessionController;
use App\Http\Controllers\Student\CbtAttemptController as StudentCbtAttemptController;
use App\Http\Controllers\Student\CbtPracticeController as StudentCbtPracticeController;
use App\Http\Controllers\Student\CoCurricularController as StudentCoCurricularController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\HelpController as StudentHelpController;
use App\Http\Controllers\Student\IdCardController as StudentIdCardController;
use App\Http\Controllers\Student\LibraryController as StudentLibraryController;
use App\Http\Controllers\Student\MessageController as StudentMessageController;
use App\Http\Controllers\Student\NotificationController as StudentNotificationController;
use App\Http\Controllers\Student\PortalLockedController;
use App\Http\Controllers\Student\ProfileChangeRequestController as StudentProfileChangeRequestController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\ResultController as StudentResultController;
use App\Http\Controllers\Student\SchoolTestAttemptController as StudentSchoolTestAttemptController;
use App\Http\Controllers\Student\SchoolTestController as StudentSchoolTestController;
use App\Http\Controllers\Student\SettingsController as StudentSettingsController;
use App\Http\Controllers\Student\SubjectController as StudentSubjectController;
use App\Http\Controllers\Student\TimetableController as StudentTimetableController;
use App\Http\Controllers\Subscriptions\BillingDetailsController;
use App\Http\Controllers\Subscriptions\ChoosePlanController;
use App\Http\Controllers\Subscriptions\ConfirmationController;
use App\Http\Controllers\Subscriptions\ContactSalesController;
use App\Http\Controllers\Subscriptions\PaymentMethodController;
use App\Http\Controllers\Subscriptions\ReviewController;
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
use App\Http\Controllers\SuperAdmin\MediaController;
use App\Http\Controllers\SuperAdmin\PaymentController as SuperAdminPaymentController;
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
use App\Http\Controllers\SupportTicketController;
use App\Support\SecureRoute as R;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Custom-domain mirror of the public routes below: the school is resolved from the
// Host header (via ResolveTenantFromCustomDomain) instead of a {school:slug} path
// segment, so every school-facing page can also be reached at its own domain. The
// {tenantDomain} wildcard excludes this app's own host so it can never shadow the
// app's own routes for normal requests. Registered BEFORE the default "/" route
// below (and every other host-agnostic route) because Laravel's router picks the
// FIRST route that matches a given method+URI regardless of domain specificity -
// a host-agnostic route registered earlier would otherwise always win over this
// domain-constrained one for the same "/" URI, even on a foreign host.
Route::group([
    'domain' => '{tenantDomain}',
    'where' => ['tenantDomain' => '^(?!'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost', '/').'$)(?!localhost$)(?!127\.0\.0\.1$).+$'],
    'middleware' => 'resolve_tenant_domain',
    'as' => 'tenant.',
], function () {
    Route::get('/', [PublicSchoolWebsiteController::class, 'show'])->name('school-website');
    Route::get('/news', [PublicSchoolWebsiteController::class, 'news'])->name('school-news.index');
    Route::get('/news/{post}', [PublicSchoolWebsiteController::class, 'newsShow'])->name('school-news.show');
    Route::get('/events', [PublicSchoolWebsiteController::class, 'events'])->name('school-events.index');
    Route::get('/careers', [PublicSchoolWebsiteController::class, 'careers'])->name('school-careers.index');
    Route::get('/gallery', [PublicSchoolWebsiteController::class, 'gallery'])->name('school-gallery.index');
    Route::get('/admissions', [PublicSchoolWebsiteController::class, 'admissions'])->name('school-admissions.index');
    Route::get('/facilities', [PublicSchoolWebsiteController::class, 'facilities'])->name('school-facilities.index');
    Route::get('/about', [PublicSchoolWebsiteController::class, 'about'])->name('school-about.index');
    Route::get('/contact', [PublicSchoolWebsiteController::class, 'contact'])->name('school-contact.index');

    // Mirrors the Portal hub and all four logins so a school reached at its
    // subdomain never has to jump back to the default "/schools/{slug}/..."
    // path just to sign in - same controllers as the default-path routes
    // below, reused exactly like PublicSchoolWebsiteController above. Every
    // store() already redirects with route(..., absolute: false), so once
    // signed in here the browser simply stays on this same host afterward.
    Route::get('/portal', [SchoolPortalController::class, 'index'])->name('portal.index');

    // The new unified portal login (Phase 2 of the portal-URL-security
    // rewrite) - additive, alongside the four per-guard logins below and the
    // portal.index hub above, which is why this can't claim the bare
    // "/portal" path yet. Phase 3 removes the hub/per-guard logins and
    // reclaims "/portal" for this. See
    // App\Http\Controllers\Portal\AuthenticatedSessionController.
    Route::get('/portal/sign-in', [PortalAuthenticatedSessionController::class, 'create'])->name('portal.show');
    Route::post('/portal/sign-in', [PortalAuthenticatedSessionController::class, 'store'])->name('portal.attempt');

    Route::middleware('portal_token')->group(function () {
        Route::name('student.')->group(function () {
            Route::get('/portal/{token}/login', [StudentAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/portal/{token}/login', [StudentAuthenticatedSessionController::class, 'store']);
        });

        Route::name('guardian.')->group(function () {
            Route::get('/parent-portal/{token}/login', [GuardianAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/parent-portal/{token}/login', [GuardianAuthenticatedSessionController::class, 'store']);
        });

        Route::name('staff.')->group(function () {
            Route::get('/staff-portal/{token}/login', [StaffAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/staff-portal/{token}/login', [StaffAuthenticatedSessionController::class, 'store']);
        });

        Route::name('portal.admin.')->group(function () {
            Route::get('/portal/admin/{token}/login', [SchoolPortalAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/portal/admin/{token}/login', [SchoolPortalAuthenticatedSessionController::class, 'store']);
        });
    });
});

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Public, human-readable by design: schools share these links outside the platform.
Route::middleware('redirect_to_custom_domain')->name('public.')->group(function () {
    Route::get('/schools/{school:slug}', [PublicSchoolWebsiteController::class, 'show'])->name('school-website');
    Route::get('/schools/{school:slug}/news', [PublicSchoolWebsiteController::class, 'news'])->name('school-news.index');
    Route::get('/schools/{school:slug}/news/{post}', [PublicSchoolWebsiteController::class, 'newsShow'])->name('school-news.show');
    Route::get('/schools/{school:slug}/events', [PublicSchoolWebsiteController::class, 'events'])->name('school-events.index');
    Route::get('/schools/{school:slug}/careers', [PublicSchoolWebsiteController::class, 'careers'])->name('school-careers.index');
    Route::get('/schools/{school:slug}/gallery', [PublicSchoolWebsiteController::class, 'gallery'])->name('school-gallery.index');
    Route::get('/schools/{school:slug}/admissions', [PublicSchoolWebsiteController::class, 'admissions'])->name('school-admissions.index');
    Route::get('/schools/{school:slug}/facilities', [PublicSchoolWebsiteController::class, 'facilities'])->name('school-facilities.index');
    Route::get('/schools/{school:slug}/about', [PublicSchoolWebsiteController::class, 'about'])->name('school-about.index');
    Route::get('/schools/{school:slug}/contact', [PublicSchoolWebsiteController::class, 'contact'])->name('school-contact.index');
});

// Result-checking PINs: deliberately its own top-level group, not nested under
// the "public." website group above - it must keep working for Basic-plan
// schools, which are barred from the front-facing website entirely and have
// no SchoolWebsite record for that group's middleware/layout to depend on.
Route::prefix('schools/{school:slug}/check-result')->name('check-result.')->group(function () {
    Route::get('/', [CheckResultController::class, 'create'])->name('show');
    Route::post('/', [CheckResultController::class, 'verify'])->name('verify');
    Route::get('/result/{usage}', [CheckResultController::class, 'result'])->name('result');
});

// ID card QR verification: also deliberately ungated and unauthenticated -
// anyone scanning a printed card must be able to reach it without logging in.
// No plan-gating needed here: cards can only ever be issued by Standard/
// Exclusive schools (id_card_access guards generation, not this route).
Route::get('id-verify/{card:uuid}', [IdCardVerificationController::class, 'show'])->name('id-verify.show');

// Student Portal — school-slug-scoped, readable by design (same convention as the
// public marketing site above), separate from the obfuscated staff dashboard URLs.
Route::prefix('schools/{school:slug}/portal')->name('student.')->group(function () {
    Route::middleware(['guest:student', 'portal_token'])->group(function () {
        Route::get('{token}/login', [StudentAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('{token}/login', [StudentAuthenticatedSessionController::class, 'store']);
    });

    Route::middleware(['auth:student', 'student_active', 'password_changed:student'])->group(function () {
        Route::post('logout', [StudentAuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('locked', [PortalLockedController::class, 'show'])->name('locked');

        Route::middleware('portal_access')->group(function () {
            Route::get('dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
            Route::get('profile', [StudentProfileController::class, 'show'])->name('profile');
            Route::get('timetable', [StudentTimetableController::class, 'index'])->name('timetable');
            Route::get('subjects', [StudentSubjectController::class, 'index'])->name('subjects');

            Route::name('assignments.')->prefix('assignments')->group(function () {
                Route::get('/', [StudentAssignmentController::class, 'index'])->name('index');
            });

            Route::name('results.')->prefix('results')->group(function () {
                Route::get('/', [StudentResultController::class, 'index'])->name('index');
                Route::get('/{examination}', [StudentResultController::class, 'show'])->name('show');
                Route::get('/{examination}/print', [StudentResultController::class, 'print'])->name('print');
                Route::get('/{examination}/pdf', [StudentResultController::class, 'pdf'])->name('pdf');
            });

            Route::name('attendance.')->prefix('attendance')->group(function () {
                Route::get('/', [StudentAttendanceController::class, 'index'])->name('index');
            });

            Route::name('library.')->prefix('library')->group(function () {
                Route::get('/', [StudentLibraryController::class, 'index'])->name('index');
            });

            Route::name('cbt-practice.')->prefix('cbt-practice')->group(function () {
                Route::get('/', [StudentCbtPracticeController::class, 'index'])->name('index');
                Route::get('/{examBody}', [StudentCbtPracticeController::class, 'show'])->name('show');
                Route::post('/exams/{exam}/start', [StudentCbtPracticeController::class, 'start'])->name('start');

                Route::name('attempts.')->prefix('attempts')->group(function () {
                    Route::get('/{attempt}', [StudentCbtAttemptController::class, 'show'])->name('show');
                    Route::post('/{attempt}/answer', [StudentCbtAttemptController::class, 'saveAnswer'])->name('answer');
                    Route::post('/{attempt}/submit', [StudentCbtAttemptController::class, 'submit'])->name('submit');
                });
            });

            Route::name('tests.')->prefix('tests')->group(function () {
                Route::get('/', [StudentSchoolTestController::class, 'index'])->name('index');
                Route::post('/{test}/start', [StudentSchoolTestController::class, 'start'])->name('start');

                Route::name('attempts.')->prefix('attempts')->group(function () {
                    Route::get('/{attempt}', [StudentSchoolTestAttemptController::class, 'show'])->name('show');
                    Route::post('/{attempt}/answer', [StudentSchoolTestAttemptController::class, 'saveAnswer'])->name('answer');
                    Route::post('/{attempt}/submit', [StudentSchoolTestAttemptController::class, 'submit'])->name('submit');
                });
            });

            Route::name('settings.')->prefix('settings')->group(function () {
                Route::get('/', [StudentSettingsController::class, 'index'])->name('index');
                Route::put('profile', [StudentSettingsController::class, 'updateProfile'])->name('update-profile');
                Route::put('password', [StudentSettingsController::class, 'updatePassword'])->name('update-password');
            });

            Route::post('profile-change-requests', [StudentProfileChangeRequestController::class, 'store'])->name('profile-change-requests.store');

            Route::name('id-card.')->prefix('id-card')->group(function () {
                Route::get('/', [StudentIdCardController::class, 'show'])->name('show');
                Route::get('/preview', [StudentIdCardController::class, 'preview'])->name('preview');
            });

            Route::name('help.')->prefix('help')->group(function () {
                Route::get('/', [StudentHelpController::class, 'index'])->name('index');
            });

            Route::name('messages.')->prefix('messages')->group(function () {
                Route::get('/', [StudentMessageController::class, 'index'])->name('index');
                Route::get('/{notice}', [StudentMessageController::class, 'show'])->name('show');
            });

            Route::name('notifications.')->prefix('notifications')->group(function () {
                Route::get('/', [StudentNotificationController::class, 'index'])->name('index');
            });

            Route::name('co-curricular.')->prefix('co-curricular')->group(function () {
                Route::get('/', [StudentCoCurricularController::class, 'index'])->name('index');
                Route::post('/{activity}/join', [StudentCoCurricularController::class, 'join'])->name('join');
                Route::delete('/{activity}/leave', [StudentCoCurricularController::class, 'leave'])->name('leave');
            });
        });
    });
});

// Parent Portal — same school-slug-scoped, readable convention as the Student Portal above.
Route::prefix('schools/{school:slug}/parent-portal')->name('guardian.')->group(function () {
    Route::middleware(['guest:guardian', 'portal_token'])->group(function () {
        Route::get('{token}/login', [GuardianAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('{token}/login', [GuardianAuthenticatedSessionController::class, 'store']);
    });

    Route::middleware(['auth:guardian', 'guardian_active', 'password_changed:guardian'])->group(function () {
        Route::post('logout', [GuardianAuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('locked', [GuardianPortalLockedController::class, 'show'])->name('locked');

        Route::middleware('portal_access')->group(function () {
            Route::get('dashboard', [GuardianDashboardController::class, 'index'])->name('dashboard');

            Route::name('children.')->prefix('children/{student}')->group(function () {
                Route::get('profile', [GuardianChildProfileController::class, 'show'])->name('profile');
                Route::get('timetable', [GuardianTimetableController::class, 'index'])->name('timetable');
                Route::get('results', [GuardianResultController::class, 'index'])->name('results');
                Route::get('results/{examination}', [GuardianResultController::class, 'show'])->name('results.show');
                Route::get('results/{examination}/print', [GuardianResultController::class, 'print'])->name('results.print');
                Route::get('results/{examination}/pdf', [GuardianResultController::class, 'pdf'])->name('results.pdf');
                Route::get('attendance', [GuardianAttendanceController::class, 'index'])->name('attendance');
                Route::get('assignments', [GuardianAssignmentController::class, 'index'])->name('assignments');
                Route::get('fees', [GuardianFeeController::class, 'index'])->name('fees');
                Route::post('profile-change-requests', [GuardianProfileChangeRequestController::class, 'store'])->name('profile-change-requests.store');
            });

            Route::name('messages.')->prefix('messages')->group(function () {
                Route::get('/', [GuardianMessageController::class, 'index'])->name('index');
            });

            Route::name('notifications.')->prefix('notifications')->group(function () {
                Route::get('/', [GuardianNotificationController::class, 'index'])->name('index');
            });

            Route::name('settings.')->prefix('settings')->group(function () {
                Route::get('/', [GuardianSettingsController::class, 'index'])->name('index');
                Route::put('profile', [GuardianSettingsController::class, 'updateProfile'])->name('update-profile');
                Route::put('password', [GuardianSettingsController::class, 'updatePassword'])->name('update-password');
            });

            Route::name('help.')->prefix('help')->group(function () {
                Route::get('/', [GuardianHelpController::class, 'index'])->name('index');
            });
        });
    });
});

// Staff Portal — same school-slug-scoped, readable convention as the Student/Parent Portals above.
Route::prefix('schools/{school:slug}/staff-portal')->name('staff.')->group(function () {
    Route::middleware(['guest:staff', 'portal_token'])->group(function () {
        Route::get('{token}/login', [StaffAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('{token}/login', [StaffAuthenticatedSessionController::class, 'store']);
    });

    Route::middleware(['auth:staff', 'staff_active', 'password_changed:staff'])->group(function () {
        Route::post('logout', [StaffAuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('locked', [StaffPortalLockedController::class, 'show'])->name('locked');

        Route::middleware('portal_access')->group(function () {
            Route::get('dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');
            Route::get('profile', [StaffProfileController::class, 'show'])->name('profile');
            Route::get('timetable', [StaffTimetableController::class, 'index'])->name('timetable');

            Route::name('settings.')->prefix('settings')->group(function () {
                Route::get('/', [StaffSettingsController::class, 'index'])->name('index');
                Route::put('profile', [StaffSettingsController::class, 'updateProfile'])->name('update-profile');
                Route::put('password', [StaffSettingsController::class, 'updatePassword'])->name('update-password');
            });

            Route::post('profile-change-requests', [StaffProfileChangeRequestController::class, 'store'])->name('profile-change-requests.store');

            Route::name('id-card.')->prefix('id-card')->group(function () {
                Route::get('/', [StaffIdCardController::class, 'show'])->name('show');
                Route::get('/preview', [StaffIdCardController::class, 'preview'])->name('preview');
            });

            Route::name('help.')->prefix('help')->group(function () {
                Route::get('/', [StaffHelpController::class, 'index'])->name('index');
            });

            Route::middleware('staff_is_teacher')->name('attendance.')->prefix('attendance')->group(function () {
                Route::get('/', [StaffAttendanceController::class, 'index'])->name('index');
                Route::post('/', [StaffAttendanceController::class, 'store'])->name('store');
                Route::get('/history', [StaffAttendanceController::class, 'history'])->name('history');
            });

            Route::middleware('staff_is_teacher')->name('exams.')->prefix('exams')->group(function () {
                Route::get('/', [StaffExaminationController::class, 'index'])->name('index');

                Route::name('scores.')->prefix('/{examination}/subjects/{subject}')->group(function () {
                    Route::get('/', [StaffExaminationController::class, 'edit'])->name('edit');
                    Route::put('/', [StaffExaminationController::class, 'update'])->name('update');
                });
            });

            Route::middleware('staff_is_teacher')->name('results.')->prefix('results')->group(function () {
                Route::get('/', [StaffResultController::class, 'index'])->name('index');
                Route::get('/{examination}/students/{student}', [StaffResultController::class, 'show'])->name('show');
                Route::put('/{examination}/students/{student}/remarks', [StaffResultController::class, 'updateRemarks'])->name('remarks');
                Route::get('/{examination}/students/{student}/print', [StaffResultController::class, 'print'])->name('print');
                Route::get('/{examination}/students/{student}/pdf', [StaffResultController::class, 'pdf'])->name('pdf');
            });

            Route::middleware('staff_is_teacher')->name('cbt.')->prefix('cbt')->group(function () {
                Route::name('tests.')->prefix('tests')->group(function () {
                    Route::get('/', [StaffCbtTestController::class, 'index'])->name('index');
                    Route::post('/', [StaffCbtTestController::class, 'store'])->name('store');
                    Route::get('/{test}', [StaffCbtTestController::class, 'show'])->name('show');
                    Route::put('/{test}', [StaffCbtTestController::class, 'update'])->name('update');
                    Route::delete('/{test}', [StaffCbtTestController::class, 'destroy'])->name('destroy');
                    Route::post('/{test}/status', [StaffCbtTestController::class, 'updateStatus'])->name('status');
                    Route::post('/{test}/duplicate', [StaffCbtTestController::class, 'duplicate'])->name('duplicate');

                    Route::name('questions.')->prefix('/{test}/questions')->group(function () {
                        Route::post('/', [StaffCbtQuestionController::class, 'store'])->name('store');
                        Route::put('/{question}', [StaffCbtQuestionController::class, 'update'])->name('update');
                        Route::delete('/{question}', [StaffCbtQuestionController::class, 'destroy'])->name('destroy');
                    });

                    Route::name('uploads.')->prefix('/{test}/uploads')->group(function () {
                        Route::post('/', [StaffCbtDocumentUploadController::class, 'store'])->name('store');
                        Route::get('/{upload}', [StaffCbtDocumentUploadController::class, 'show'])->name('show');
                        Route::delete('/{upload}', [StaffCbtDocumentUploadController::class, 'destroy'])->name('destroy');
                    });
                });
            });
        });
    });
});

// School Portal — single entry point per school linking out to all four
// role-specific logins above, so a school's public website never has to
// send anyone to the shared global EduNest login. Same school-slug-scoped,
// readable-by-design convention as the three portals above. Available on
// every plan (login itself has never been plan-gated for any portal - only
// the post-login dashboards are, via "portal_access").
Route::prefix('schools/{school:slug}/portal')->name('portal.')->group(function () {
    Route::get('/', [SchoolPortalController::class, 'index'])->name('index');

    Route::name('admin.')->prefix('admin')->group(function () {
        Route::middleware(['guest', 'portal_token'])->group(function () {
            Route::get('{token}/login', [SchoolPortalAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('{token}/login', [SchoolPortalAuthenticatedSessionController::class, 'store']);
        });

        Route::middleware(['auth', 'school_admin', 'auth.session'])->group(function () {
            Route::post('logout', [SchoolPortalAuthenticatedSessionController::class, 'destroy'])->name('logout');
        });
    });
});

// The new unified portal login (Phase 2 of the portal-URL-security rewrite),
// default-host mirror of the tenant-domain pair registered above - this is
// the actual Basic-plan entry point (no {school:slug}, disambiguated by the
// school_code field instead). Additive alongside the school-slug-scoped
// "portal." group above; Phase 3 removes that group and reclaims "/portal"
// for this route.
Route::get('/portal/sign-in', [PortalAuthenticatedSessionController::class, 'create'])->name('portal.show');
Route::post('/portal/sign-in', [PortalAuthenticatedSessionController::class, 'store'])->name('portal.attempt');

// Basic-plan portal - the school finder.
//
// Basic schools have no public website and no subdomain, so unlike Standard
// and Exclusive they cannot be reached directly. Everyone arrives here, at one
// shared token-gated URL, names their school, and is forwarded to it. This
// flow is entirely separate from the two portals above and shares no route,
// controller or view with them. See docs/BASIC-PLAN-PORTAL.md.
//
// The token is joined straight onto the word "portal" with no separator -
// edunest.com/portal6219db402a20f65b63358972bd5274cd - so the whole thing
// reads as one opaque path rather than advertising a token-shaped segment.
//
// Two things stop it colliding with the sibling paths above. The parameter
// matches ONLY [A-Za-z0-9], so it can never span the "/" in "/portal/sign-in"
// or "/portal/admin/...", and it is pinned to exactly 32 characters, so a bare
// "/portal" is too short to match either.
//
// Throttled because the POST is a lookup against school names, and an
// unthrottled one would let anyone enumerate EduNest's customer list.
Route::middleware(['basic_portal_token', 'throttle:20,1'])
    ->where(['token' => '[A-Za-z0-9]{32}'])
    ->name('basic-portal.')
    ->group(function () {
        Route::get('portal{token}', [BasicSchoolFinderController::class, 'show'])->name('finder');
        Route::post('portal{token}', [BasicSchoolFinderController::class, 'find'])->name('find');
    });

Route::middleware('throttle:5,1')->group(function () {
    Route::get(R::uri('reports.create'), [ReportController::class, 'create'])->name('reports.create');
    Route::post(R::uri('reports.create'), [ReportController::class, 'store'])->name('reports.store');
});
Route::get(R::uri('reports.confirmation'), [ReportController::class, 'confirmation'])->name('reports.confirmation');

Route::get(R::uri('dashboard'), function (Request $request) {
    if (auth()->user()->role === UserRole::SuperAdmin) {
        return redirect()->route('super-admin.dashboard');
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

    Route::middleware(['school_admin', 'school_activated'])->group(function () {
        Route::get(R::uri('communications.index'), [SchoolCommunicationController::class, 'index'])->name('communications.index');

        Route::name('subscription-top-up.')->group(function () {
            Route::get(R::uri('subscription-top-up.create'), [SubscriptionTopUpController::class, 'create'])->name('create');
            Route::post(R::uri('subscription-top-up.create'), [SubscriptionTopUpController::class, 'store'])->name('store');
        });

        Route::name('students.')->group(function () {
            Route::get(R::uri('students.index'), [StudentController::class, 'index'])->name('index');
            Route::post(R::uri('students.index'), [StudentController::class, 'store'])->name('store');
            Route::get(R::uri('students.show').'/{student}', [StudentController::class, 'show'])->name('show');
            Route::put(R::uri('students.update').'/{student}', [StudentController::class, 'update'])->name('update');
            Route::delete(R::uri('students.destroy').'/{student}', [StudentController::class, 'destroy'])->name('destroy');
            Route::post(R::uri('students.toggle-active').'/{student}', [StudentController::class, 'toggleActive'])->name('toggle-active');
            Route::put(R::uri('students.update-password').'/{student}', [StudentController::class, 'updatePassword'])->name('update-password');
            Route::post(R::uri('students.guardians.store').'/{student}/guardians', [StudentController::class, 'storeGuardian'])->name('guardians.store');
            Route::put(R::uri('students.guardians.update-password').'/guardians/{guardian}', [StudentController::class, 'updateGuardianPassword'])->name('guardians.update-password');
            Route::delete(R::uri('students.guardians.destroy').'/{student}/guardians/{guardian}', [StudentController::class, 'destroyGuardian'])->name('guardians.destroy');
        });

        Route::name('guardians.')->group(function () {
            Route::get(R::uri('guardians.index'), [GuardianController::class, 'index'])->name('index');
            Route::post(R::uri('guardians.index'), [GuardianController::class, 'store'])->name('store');
            Route::get(R::uri('guardians.show').'/{guardian}', [GuardianController::class, 'show'])->name('show');
            Route::put(R::uri('guardians.update').'/{guardian}', [GuardianController::class, 'update'])->name('update');
            Route::delete(R::uri('guardians.destroy').'/{guardian}', [GuardianController::class, 'destroy'])->name('destroy');
            Route::post(R::uri('guardians.toggle-active').'/{guardian}', [GuardianController::class, 'toggleActive'])->name('toggle-active');
            Route::put(R::uri('guardians.update-password').'/{guardian}/password', [GuardianController::class, 'updatePassword'])->name('update-password');
            Route::post(R::uri('guardians.children.store').'/{guardian}/children', [GuardianController::class, 'linkStudent'])->name('children.store');
            Route::delete(R::uri('guardians.children.destroy').'/{guardian}/children/{student}', [GuardianController::class, 'unlinkStudent'])->name('children.destroy');
        });

        Route::name('timetable.')->group(function () {
            Route::get(R::uri('timetable.index'), [TimetableController::class, 'index'])->name('index');
            Route::post(R::uri('timetable.index'), [TimetableController::class, 'store'])->name('store');
            Route::put(R::uri('timetable.update').'/{timetableEntry}', [TimetableController::class, 'update'])->name('update');
            Route::delete(R::uri('timetable.destroy').'/{timetableEntry}', [TimetableController::class, 'destroy'])->name('destroy');
        });

        Route::name('notices.')->group(function () {
            Route::get(R::uri('notices.index'), [NoticeController::class, 'index'])->name('index');
            Route::post(R::uri('notices.index'), [NoticeController::class, 'store'])->name('store');
        });

        Route::name('co-curricular.')->group(function () {
            Route::get(R::uri('co-curricular.index'), [CoCurricularController::class, 'index'])->name('index');
            Route::post(R::uri('co-curricular.index'), [CoCurricularController::class, 'store'])->name('store');
            Route::put(R::uri('co-curricular.update').'/{activity}', [CoCurricularController::class, 'update'])->name('update');
            Route::delete(R::uri('co-curricular.destroy').'/{activity}', [CoCurricularController::class, 'destroy'])->name('destroy');
        });

        Route::name('staff.')->group(function () {
            Route::get(R::uri('staff.index'), [StaffController::class, 'index'])->name('index');
            Route::post(R::uri('staff.index'), [StaffController::class, 'store'])->name('store');
            Route::get(R::uri('staff.show').'/{member}', [StaffController::class, 'show'])->name('show');
            Route::put(R::uri('staff.update').'/{member}', [StaffController::class, 'update'])->name('update');
            Route::delete(R::uri('staff.destroy').'/{member}', [StaffController::class, 'destroy'])->name('destroy');
            Route::post(R::uri('staff.toggle-active').'/{member}', [StaffController::class, 'toggleActive'])->name('toggle-active');
            Route::put(R::uri('staff.update-password').'/{member}', [StaffController::class, 'updatePassword'])->name('update-password');
        });

        Route::name('profile-change-requests.')->group(function () {
            Route::get(R::uri('profile-change-requests.index'), [ProfileChangeRequestController::class, 'index'])->name('index');
            Route::post(R::uri('profile-change-requests.approve').'/{changeRequest}', [ProfileChangeRequestController::class, 'approve'])->name('approve');
            Route::post(R::uri('profile-change-requests.reject').'/{changeRequest}', [ProfileChangeRequestController::class, 'reject'])->name('reject');
        });

        Route::name('teacher-assignments.')->group(function () {
            Route::get(R::uri('teacher-assignments.index'), [TeacherAssignmentController::class, 'index'])->name('index');
            Route::post(R::uri('teacher-assignments.store'), [TeacherAssignmentController::class, 'store'])->name('store');
            Route::delete(R::uri('teacher-assignments.destroy').'/{assignment}', [TeacherAssignmentController::class, 'destroy'])->name('destroy');
        });

        Route::name('class-subjects.')->group(function () {
            Route::get(R::uri('class-subjects.index'), [ClassSubjectController::class, 'index'])->name('index');
            Route::post(R::uri('class-subjects.store'), [ClassSubjectController::class, 'store'])->name('store');
        });

        Route::name('academics.')->group(function () {
            Route::get(R::uri('academics.index'), [AcademicController::class, 'index'])->name('index');

            Route::name('levels.')->group(function () {
                Route::post(R::uri('academics.levels.store'), [AcademicController::class, 'storeLevel'])->name('store');
                Route::put(R::uri('academics.levels.update').'/{level}', [AcademicController::class, 'updateLevel'])->name('update');
                Route::delete(R::uri('academics.levels.destroy').'/{level}', [AcademicController::class, 'destroyLevel'])->name('destroy');
            });

            Route::name('classes.')->group(function () {
                Route::post(R::uri('academics.classes.store').'/{level}', [AcademicController::class, 'storeClass'])->name('store');
                Route::put(R::uri('academics.classes.update').'/{class}', [AcademicController::class, 'updateClass'])->name('update');
                Route::delete(R::uri('academics.classes.destroy').'/{class}', [AcademicController::class, 'destroyClass'])->name('destroy');
            });

            Route::name('terms.')->group(function () {
                Route::post(R::uri('academics.terms.store'), [AcademicController::class, 'storeTerm'])->name('store');
                Route::delete(R::uri('academics.terms.destroy').'/{term}', [AcademicController::class, 'destroyTerm'])->name('destroy');
            });

            Route::name('grade-bands.')->group(function () {
                Route::post(R::uri('academics.grade-bands.store'), [AcademicController::class, 'storeGradeBand'])->name('store');
                Route::put(R::uri('academics.grade-bands.update').'/{gradeBand}', [AcademicController::class, 'updateGradeBand'])->name('update');
                Route::delete(R::uri('academics.grade-bands.destroy').'/{gradeBand}', [AcademicController::class, 'destroyGradeBand'])->name('destroy');
            });
        });

        Route::middleware('cbt_access')->group(function () {
            Route::name('cbt-practice.')->group(function () {
                Route::get(R::uri('cbt-practice.index'), [CbtPracticeController::class, 'index'])->name('index');
                Route::get(R::uri('cbt-practice.show').'/{examBody}', [CbtPracticeController::class, 'show'])->name('show');
                Route::post(R::uri('cbt-practice.grants.store').'/{examBody}/grants', [CbtPracticeController::class, 'storeGrant'])->name('grants.store');
                Route::delete(R::uri('cbt-practice.grants.destroy').'/grants/{grant}', [CbtPracticeController::class, 'destroyGrant'])->name('grants.destroy');
            });

            Route::name('cbt-tests.')->group(function () {
                Route::get(R::uri('cbt-tests.index'), [CbtTestOversightController::class, 'index'])->name('index');
                Route::get(R::uri('cbt-tests.show').'/{test}', [CbtTestOversightController::class, 'show'])->name('show');
                Route::put(R::uri('cbt-tests.update').'/{test}', [CbtTestOversightController::class, 'update'])->name('update');
                Route::post(R::uri('cbt-tests.status').'/{test}/status', [CbtTestOversightController::class, 'updateStatus'])->name('status');
            });
        });

        Route::name('attendance.')->group(function () {
            Route::get(R::uri('attendance.index'), [AttendanceController::class, 'index'])->name('index');
            Route::post(R::uri('attendance.index'), [AttendanceController::class, 'store'])->name('store');
            Route::get(R::uri('attendance.history'), [AttendanceController::class, 'history'])->name('history');
        });
        Route::name('examinations.')->group(function () {
            Route::get(R::uri('examinations.index'), [ExaminationController::class, 'index'])->name('index');
            Route::post(R::uri('examinations.index'), [ExaminationController::class, 'store'])->name('store');
            Route::get(R::uri('examinations.show').'/{examination}', [ExaminationController::class, 'show'])->name('show');
            Route::put(R::uri('examinations.update').'/{examination}', [ExaminationController::class, 'update'])->name('update');
            Route::delete(R::uri('examinations.destroy').'/{examination}', [ExaminationController::class, 'destroy'])->name('destroy');

            Route::name('subjects.')->group(function () {
                Route::post(R::uri('examinations.subjects.store').'/{examination}', [ExaminationController::class, 'storeSubject'])->name('store');
                Route::delete(R::uri('examinations.subjects.destroy').'/{subject}', [ExaminationController::class, 'destroySubject'])->name('destroy');
            });

            Route::get(R::uri('examinations.scores').'/{subject}', [ExaminationController::class, 'scores'])->name('scores');
            Route::post(R::uri('examinations.scores.store').'/{subject}', [ExaminationController::class, 'storeScores'])->name('scores.store');

            Route::name('report-cards.')->group(function () {
                Route::get(R::uri('examinations.report-cards').'/{examination}', [ExaminationController::class, 'reportCards'])->name('index');
                Route::get(R::uri('examinations.report-cards.show').'/{examination}/{student}', [ExaminationController::class, 'reportCard'])->name('show');
            });
        });
        Route::name('results.')->group(function () {
            Route::get(R::uri('results.index'), [ResultController::class, 'index'])->name('index');
            Route::get(R::uri('results.show').'/{examination}/{student}', [ResultController::class, 'show'])->name('show');
            Route::put(R::uri('results.remarks').'/{examination}/{student}', [ResultController::class, 'updateRemarks'])->name('remarks');
            Route::post(R::uri('results.send').'/{examination}/{student}', [ResultController::class, 'send'])->name('send');
            Route::get(R::uri('results.print').'/{examination}/{student}', [ResultController::class, 'print'])->name('print');
            Route::get(R::uri('results.pdf').'/{examination}/{student}', [ResultController::class, 'pdf'])->name('pdf');
        });
        Route::middleware('result_pin_access')->name('result-pins.')->group(function () {
            Route::get(R::uri('result-pins.index'), [ResultCheckingPinController::class, 'index'])->name('index');
            Route::post(R::uri('result-pins.assign').'/{pin}', [ResultCheckingPinController::class, 'assign'])->name('assign');
            Route::post(R::uri('result-pins.revoke').'/{pin}', [ResultCheckingPinController::class, 'revoke'])->name('revoke');
        });
        Route::name('assignments.')->group(function () {
            Route::get(R::uri('assignments.index'), [AssignmentController::class, 'index'])->name('index');
            Route::post(R::uri('assignments.index'), [AssignmentController::class, 'store'])->name('store');
            Route::get(R::uri('assignments.show').'/{assignment}', [AssignmentController::class, 'show'])->name('show');
            Route::put(R::uri('assignments.update').'/{assignment}', [AssignmentController::class, 'update'])->name('update');
            Route::delete(R::uri('assignments.destroy').'/{assignment}', [AssignmentController::class, 'destroy'])->name('destroy');
            Route::post(R::uri('assignments.submissions.store').'/{assignment}', [AssignmentController::class, 'storeSubmissions'])->name('submissions.store');
        });
        Route::name('events.')->group(function () {
            Route::get(R::uri('events.index'), [EventController::class, 'index'])->name('index');
            Route::post(R::uri('events.index'), [EventController::class, 'store'])->name('store');
            Route::put(R::uri('events.update').'/{event}', [EventController::class, 'update'])->name('update');
            Route::delete(R::uri('events.destroy').'/{event}', [EventController::class, 'destroy'])->name('destroy');
        });
        Route::name('library.')->group(function () {
            Route::get(R::uri('library.index'), [LibraryController::class, 'index'])->name('index');
            Route::post(R::uri('library.index'), [LibraryController::class, 'store'])->name('store');
            Route::put(R::uri('library.update').'/{book}', [LibraryController::class, 'update'])->name('update');
            Route::delete(R::uri('library.destroy').'/{book}', [LibraryController::class, 'destroy'])->name('destroy');

            Route::name('loans.')->group(function () {
                Route::get(R::uri('library.loans.index'), [LibraryController::class, 'loans'])->name('index');
                Route::post(R::uri('library.loans.store'), [LibraryController::class, 'storeLoan'])->name('store');
                Route::post(R::uri('library.loans.return').'/{loan}', [LibraryController::class, 'returnLoan'])->name('return');
            });
        });

        Route::name('transport.')->group(function () {
            Route::get(R::uri('transport.index'), [TransportController::class, 'index'])->name('index');
            Route::post(R::uri('transport.index'), [TransportController::class, 'store'])->name('store');
            Route::put(R::uri('transport.update').'/{vehicle}', [TransportController::class, 'update'])->name('update');
            Route::delete(R::uri('transport.destroy').'/{vehicle}', [TransportController::class, 'destroy'])->name('destroy');

            Route::name('routes.')->group(function () {
                Route::get(R::uri('transport.routes.index'), [TransportController::class, 'routes'])->name('index');
                Route::post(R::uri('transport.routes.store'), [TransportController::class, 'storeRoute'])->name('store');
                Route::put(R::uri('transport.routes.update').'/{route}', [TransportController::class, 'updateRoute'])->name('update');
                Route::delete(R::uri('transport.routes.destroy').'/{route}', [TransportController::class, 'destroyRoute'])->name('destroy');
                Route::get(R::uri('transport.routes.students').'/{route}', [TransportController::class, 'students'])->name('students');
                Route::post(R::uri('transport.routes.students.store').'/{route}', [TransportController::class, 'storeAssignment'])->name('students.store');
                Route::delete(R::uri('transport.assignments.destroy').'/{assignment}', [TransportController::class, 'destroyAssignment'])->name('assignments.destroy');
            });
        });

        Route::name('hostels.')->group(function () {
            Route::get(R::uri('hostels.index'), [HostelController::class, 'index'])->name('index');
            Route::post(R::uri('hostels.index'), [HostelController::class, 'store'])->name('store');
            Route::put(R::uri('hostels.update').'/{hostel}', [HostelController::class, 'update'])->name('update');
            Route::delete(R::uri('hostels.destroy').'/{hostel}', [HostelController::class, 'destroy'])->name('destroy');

            Route::get(R::uri('hostels.rooms').'/{hostel}', [HostelController::class, 'rooms'])->name('rooms');
            Route::post(R::uri('hostels.rooms.store').'/{hostel}', [HostelController::class, 'storeRoom'])->name('rooms.store');
            Route::put(R::uri('hostels.rooms.update').'/{room}', [HostelController::class, 'updateRoom'])->name('rooms.update');
            Route::delete(R::uri('hostels.rooms.destroy').'/{room}', [HostelController::class, 'destroyRoom'])->name('rooms.destroy');

            Route::get(R::uri('hostels.students').'/{room}', [HostelController::class, 'students'])->name('students');
            Route::post(R::uri('hostels.students.store').'/{room}', [HostelController::class, 'storeAllocation'])->name('students.store');
            Route::delete(R::uri('hostels.allocations.destroy').'/{allocation}', [HostelController::class, 'destroyAllocation'])->name('allocations.destroy');
        });

        Route::name('finance.')->group(function () {
            Route::get(R::uri('finance.index'), [FinanceController::class, 'index'])->name('index');
            Route::post(R::uri('finance.index'), [FinanceController::class, 'storeStructure'])->name('store');
            Route::delete(R::uri('finance.destroy').'/{structure}', [FinanceController::class, 'destroyStructure'])->name('destroy');
            Route::post(R::uri('finance.generate').'/{structure}', [FinanceController::class, 'generateInvoices'])->name('generate');

            Route::name('invoices.')->group(function () {
                Route::get(R::uri('finance.invoices.index'), [FinanceController::class, 'invoices'])->name('index');
                Route::post(R::uri('finance.invoices.store'), [FinanceController::class, 'storeInvoice'])->name('store');
                Route::delete(R::uri('finance.invoices.destroy').'/{invoice}', [FinanceController::class, 'destroyInvoice'])->name('destroy');
                Route::post(R::uri('finance.invoices.payments.store').'/{invoice}', [FinanceController::class, 'storePayment'])->name('payments.store');
            });
        });
        Route::middleware('website_access')->name('website.')->group(function () {
            Route::get(R::uri('website.index'), [WebsiteController::class, 'edit'])->name('index');
            Route::put(R::uri('website.blocks.update').'/{page}', [WebsiteController::class, 'updateBlocks'])->name('blocks.update');
            Route::put(R::uri('website.update-brand-color'), [WebsiteController::class, 'updateBrandColor'])->name('update-brand-color');
            Route::put(R::uri('website.update-header-hero-fields'), [WebsiteController::class, 'updateHeaderHeroFields'])->name('update-header-hero-fields');
            Route::put(R::uri('website.update-contact-fields'), [WebsiteController::class, 'updateContactFields'])->name('update-contact-fields');
            Route::post(R::uri('website.publish'), [WebsiteController::class, 'togglePublish'])->name('publish');
            Route::post(R::uri('website.gallery.store'), [WebsiteController::class, 'storeGalleryImage'])->name('gallery.store');
            Route::delete(R::uri('website.gallery.destroy').'/{image}', [WebsiteController::class, 'destroyGalleryImage'])->name('gallery.destroy');

            Route::name('hero-slides.')->group(function () {
                Route::post(R::uri('website.hero-slides.store'), [WebsiteController::class, 'storeHeroSlide'])->name('store');
                Route::delete(R::uri('website.hero-slides.destroy').'/{slide}', [WebsiteController::class, 'destroyHeroSlide'])->name('destroy');
                Route::post(R::uri('website.hero-slides.move').'/{slide}', [WebsiteController::class, 'moveHeroSlide'])->name('move');
            });

            Route::name('nav-links.')->group(function () {
                Route::post(R::uri('website.nav-links.store'), [WebsiteController::class, 'storeNavLink'])->name('store');
                Route::put(R::uri('website.nav-links.update').'/{navLink}', [WebsiteController::class, 'updateNavLink'])->name('update');
                Route::delete(R::uri('website.nav-links.destroy').'/{navLink}', [WebsiteController::class, 'destroyNavLink'])->name('destroy');
                Route::post(R::uri('website.nav-links.move').'/{navLink}', [WebsiteController::class, 'moveNavLink'])->name('move');
            });
        });
        Route::name('news.')->group(function () {
            Route::get(R::uri('news.index'), [NewsController::class, 'index'])->name('index');
            Route::post(R::uri('news.index'), [NewsController::class, 'store'])->name('store');
            Route::put(R::uri('news.update').'/{post}', [NewsController::class, 'update'])->name('update');
            Route::delete(R::uri('news.destroy').'/{post}', [NewsController::class, 'destroy'])->name('destroy');
        });
        Route::name('careers.')->group(function () {
            Route::get(R::uri('careers.index'), [JobPostingController::class, 'index'])->name('index');
            Route::post(R::uri('careers.index'), [JobPostingController::class, 'store'])->name('store');
            Route::put(R::uri('careers.update').'/{job}', [JobPostingController::class, 'update'])->name('update');
            Route::delete(R::uri('careers.destroy').'/{job}', [JobPostingController::class, 'destroy'])->name('destroy');
            Route::post(R::uri('careers.toggle-active').'/{job}', [JobPostingController::class, 'toggleActive'])->name('toggle-active');
        });
        Route::name('testimonials.')->group(function () {
            Route::get(R::uri('testimonials.index'), [TestimonialController::class, 'index'])->name('index');
            Route::post(R::uri('testimonials.index'), [TestimonialController::class, 'store'])->name('store');
            Route::put(R::uri('testimonials.update').'/{testimonial}', [TestimonialController::class, 'update'])->name('update');
            Route::delete(R::uri('testimonials.destroy').'/{testimonial}', [TestimonialController::class, 'destroy'])->name('destroy');
        });
        Route::name('facilities.')->group(function () {
            Route::get(R::uri('facilities.index'), [FacilityController::class, 'index'])->name('index');
            Route::post(R::uri('facilities.index'), [FacilityController::class, 'store'])->name('store');
            Route::put(R::uri('facilities.update').'/{facility}', [FacilityController::class, 'update'])->name('update');
            Route::delete(R::uri('facilities.destroy').'/{facility}', [FacilityController::class, 'destroy'])->name('destroy');
        });

        Route::middleware('id_card_access')->name('id-cards.')->group(function () {
            Route::get(R::uri('id-cards.index'), [IdCardController::class, 'index'])->name('index');
            Route::get(R::uri('id-cards.preview').'/{type}/{record}', [IdCardController::class, 'preview'])->name('preview');
            Route::post(R::uri('id-cards.print'), [IdCardController::class, 'print'])->name('print');
            Route::post(R::uri('id-cards.pdf'), [IdCardController::class, 'pdf'])->name('pdf');

            Route::name('templates.')->group(function () {
                Route::get(R::uri('id-cards.templates.index'), [IdCardTemplateController::class, 'index'])->name('index');
                Route::post(R::uri('id-cards.templates.index'), [IdCardTemplateController::class, 'store'])->name('store');
                Route::put(R::uri('id-cards.templates.update').'/{template}', [IdCardTemplateController::class, 'update'])->name('update');
                Route::delete(R::uri('id-cards.templates.destroy').'/{template}', [IdCardTemplateController::class, 'destroy'])->name('destroy');
            });

            Route::name('issued.')->group(function () {
                Route::get(R::uri('id-cards.issued.index'), [IssuedIdCardController::class, 'index'])->name('index');
                Route::post(R::uri('id-cards.issued.revoke').'/{card}', [IssuedIdCardController::class, 'revoke'])->name('revoke');
            });
        });

        Route::middleware('custom_domain_access')->name('custom-domain.')->group(function () {
            Route::get(R::uri('custom-domain.index'), [CustomDomainController::class, 'index'])->name('index');
            Route::post(R::uri('custom-domain.index'), [CustomDomainController::class, 'store'])->name('store');
            Route::get(R::uri('custom-domain.status').'/{domain}', [CustomDomainController::class, 'status'])->name('status');
            Route::post(R::uri('custom-domain.verify').'/{domain}', [CustomDomainController::class, 'verify'])->name('verify');
            Route::put(R::uri('custom-domain.update').'/{domain}', [CustomDomainController::class, 'update'])->name('update');
            Route::post(R::uri('custom-domain.set-primary').'/{domain}', [CustomDomainController::class, 'setPrimary'])->name('set-primary');
            Route::post(R::uri('custom-domain.toggle-redirect').'/{domain}', [CustomDomainController::class, 'toggleRedirect'])->name('toggle-redirect');
            Route::delete(R::uri('custom-domain.destroy').'/{domain}', [CustomDomainController::class, 'destroy'])->name('destroy');
        });

        Route::get(R::uri('settings.index'), [SchoolSettingsController::class, 'edit'])->name('settings.index');
        Route::put(R::uri('settings.update'), [SchoolSettingsController::class, 'update'])->name('settings.update');

        Route::get(R::uri('reports.summary'), [SchoolReportController::class, 'summary'])->name('reports.summary');
        Route::get(R::uri('overview.index'), [SchoolAdminDashboardController::class, 'overview'])->name('overview.index');

        Route::get(R::uri('search.query'), [SchoolSearchController::class, 'search'])->name('search.query');
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
            Route::post(R::uri('super-admin.subscriptions.top-ups.approve').'/{topUp}', [SubscriptionApprovalController::class, 'approveTopUp'])->name('top-ups.approve');
            Route::post(R::uri('super-admin.subscriptions.top-ups.reject').'/{topUp}', [SubscriptionApprovalController::class, 'rejectTopUp'])->name('top-ups.reject');
        });

        Route::name('result-pins.')->middleware('permission:manage_result_pins')->group(function () {
            Route::get(R::uri('super-admin.result-pins.index'), [ResultPinController::class, 'index'])->name('index');
            Route::get(R::uri('super-admin.result-pins.show').'/{school}', [ResultPinController::class, 'show'])->name('show');
            Route::post(R::uri('super-admin.result-pins.generate').'/{school}', [ResultPinController::class, 'generate'])->name('generate');
            Route::post(R::uri('super-admin.result-pins.revoke').'/{pin}', [ResultPinController::class, 'revoke'])->name('revoke');
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

        Route::get(R::uri('super-admin.audit-logs.index'), [AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('permission:manage_audit_logs');
    });
});

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Basic-plan school landing - MUST BE THE LAST ROUTE IN THE APPLICATION
|--------------------------------------------------------------------------
|
| edunest.com/greenfield-college
|
| Where the Basic-plan school finder above sends people. A Basic school has no
| public website, so this is its portal entry point rather than a home page.
|
| This route occupies the root namespace, so a school slug competes with every
| top-level path the application owns. Three things keep that safe:
|
|   1. It is registered LAST - after every route in this file AND after the
|      ones in auth.php, which is required above. Laravel matches the first
|      route that fits, so a real route always wins. Nothing may be registered
|      after this block.
|
|   2. The pattern excludes reserved words outright (config
|      basic_portal.reserved_slugs), so /login and /dashboard can never reach
|      this controller even if the ordering above were ever disturbed.
|
|   3. The same reserved list is enforced when a school picks its slug, so the
|      collision cannot be created in the first place.
|
| The slug pattern also requires lowercase letters, digits and hyphens only,
| which is exactly what Str::slug() produces - so the obfuscated hex URIs used
| elsewhere in this file cannot be mistaken for a school.
|
*/
Route::get('/{school:slug}', [BasicSchoolLandingController::class, 'show'])
    ->where('school', sprintf(
        // The alternation is wrapped in its own group before the "$" so that
        // the anchor applies to EVERY reserved word rather than only the last
        // one. Without the group, "(?!news|...|edunest$)" rejects any slug
        // merely STARTING with a reserved word, which would quietly 404 a
        // legitimate school called "Newspaper College".
        '(?!(?:%s)$)[a-z0-9]+(?:-[a-z0-9]+)*',
        implode('|', array_map(
            static fn (string $slug): string => preg_quote($slug, '/'),
            config('basic_portal.reserved_slugs'),
        )),
    ))
    ->name('basic-portal.school');
