<?php

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
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student portal
|--------------------------------------------------------------------------
|
| Signed in on the `student` guard, scoped to one school by slug.
|
*/

// Student Portal — school-slug-scoped, readable by design (same convention as the
// public marketing site above), separate from the obfuscated staff dashboard URLs.
/*
 * THE SCHOOL IS IDENTIFIED BY AN OPAQUE KEY, NOT ITS SLUG.
 *
 * This prefix used to read schools/{school:slug}/portal, which put the
 * school's public name into every portal link a pupil ever received - and into
 * every bookmark, browser history and referrer header those links produced.
 * The slug is not a secret, but a private portal address has no reason to
 * announce whose portal it is.
 *
 * The parameter is still called "school" and still resolves to a School, so
 * every controller signature and every route() call is unchanged; only the
 * column it binds on has moved. See the add_portal_key_to_schools_table
 * migration.
 *
 * "p" is a literal prefix rather than nothing at all, deliberately: a bare
 * {school:portal_key} would be another wildcard in the root namespace, which
 * this application works hard to keep clear (see routes/school-links.php).
 */
Route::prefix('p/{school:portal_key}/portal')->name('student.')->group(function () {
    Route::middleware(['guest:student', 'portal_token'])->group(function () {
        Route::get('{token}/login', [StudentAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('{token}/login', [StudentAuthenticatedSessionController::class, 'store']);
    });

    Route::middleware(['auth:student', 'student_active'])->group(function () {
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

                // The exam token, entered in the portal. Nothing above this
                // line opens until it has been.
                Route::post('/{examination}/unlock', [StudentResultController::class, 'unlock'])->name('unlock');
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
