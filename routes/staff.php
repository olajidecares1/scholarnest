<?php

use App\Http\Controllers\Staff\AttendanceController as StaffAttendanceController;
use App\Http\Controllers\Staff\Auth\AuthenticatedSessionController as StaffAuthenticatedSessionController;
use App\Http\Controllers\Staff\Cbt\DocumentUploadController as StaffCbtDocumentUploadController;
use App\Http\Controllers\Staff\Cbt\QuestionController as StaffCbtQuestionController;
use App\Http\Controllers\Staff\Cbt\TestController as StaffCbtTestController;
use App\Http\Controllers\Staff\ClassNoteController as StaffClassNoteController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\DiaryController as StaffDiaryController;
use App\Http\Controllers\Staff\ExaminationController as StaffExaminationController;
use App\Http\Controllers\Staff\HelpController as StaffHelpController;
use App\Http\Controllers\Staff\IdCardController as StaffIdCardController;
use App\Http\Controllers\Staff\PortalLockedController as StaffPortalLockedController;
use App\Http\Controllers\Staff\ProfileChangeRequestController as StaffProfileChangeRequestController;
use App\Http\Controllers\Staff\ProfileController as StaffProfileController;
use App\Http\Controllers\Staff\ResultController as StaffResultController;
use App\Http\Controllers\Staff\SettingsController as StaffSettingsController;
use App\Http\Controllers\Staff\SignatureController as StaffSignatureController;
use App\Http\Controllers\Staff\TimetableController as StaffTimetableController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff portal
|--------------------------------------------------------------------------
|
| Signed in on the `staff` guard, scoped to one school by slug.
|
*/

// Staff Portal — same school-slug-scoped, readable convention as the Student/Parent Portals above.
// The school is identified by an opaque key, not its slug - see
// routes/student.php for why, and the add_portal_key_to_schools_table
// migration for what the key is. The parameter is still "school" and still
// resolves to a School, so no controller or route() call changed.
Route::prefix('p/{school:portal_key}/staff-portal')->name('staff.')->group(function () {
    Route::middleware(['guest:staff', 'portal_token'])->group(function () {
        Route::get('{token}/login', [StaffAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('{token}/login', [StaffAuthenticatedSessionController::class, 'store']);
    });

    Route::middleware(['auth:staff', 'staff_active'])->group(function () {
        Route::post('logout', [StaffAuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('locked', [StaffPortalLockedController::class, 'show'])->name('locked');

        Route::middleware('portal_access')->group(function () {
            Route::get('dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');
            Route::get('profile', [StaffProfileController::class, 'show'])->name('profile');
            Route::middleware('plan_feature:timetable')->get('timetable', [StaffTimetableController::class, 'index'])->name('timetable');

            Route::name('settings.')->prefix('settings')->group(function () {
                Route::get('/', [StaffSettingsController::class, 'index'])->name('index');
                Route::put('profile', [StaffSettingsController::class, 'updateProfile'])->name('update-profile');

                // On every plan: a Basic school's results are signed too.
                // The upload form and the drawn signature both end up in the
                // same place; a teacher with a scan already made should not
                // have to redraw it.
                Route::post('signature', [StaffSettingsController::class, 'updateSignature'])->name('update-signature');

                // Drawn on a canvas and posted with fetch(), so the modal can
                // stay open and report back without a page load.
                Route::post('signature/register', [StaffSignatureController::class, 'store'])->name('signature.store');
                Route::delete('signature/register', [StaffSignatureController::class, 'destroy'])->name('signature.destroy');
            });

            Route::post('profile-change-requests', [StaffProfileChangeRequestController::class, 'store'])->name('profile-change-requests.store');

            // ID cards are premium: Basic reaches the rest of this portal but
            // not this corner of it.
            Route::middleware('plan_feature:id-cards')->name('id-card.')->prefix('id-card')->group(function () {
                Route::get('/', [StaffIdCardController::class, 'show'])->name('show');
                Route::get('/preview', [StaffIdCardController::class, 'preview'])->name('preview');
            });

            Route::name('help.')->prefix('help')->group(function () {
                Route::get('/', [StaffHelpController::class, 'index'])->name('index');
            });

            // The diary is a Standard and Exclusive feature, and only a
            // teacher keeps one. Gated by plan here as well as in the School
            // Admin panel, because the staff portal is open on every plan -
            // without this a Basic school's teachers would reach it.
            Route::middleware(['staff_is_teacher', 'plan_feature:diary'])->name('diary.')->prefix('diary')->group(function () {
                Route::get('/', [StaffDiaryController::class, 'index'])->name('index');
                Route::post('/', [StaffDiaryController::class, 'store'])->name('store');
            });

            // Class notes: a Word document sent to one or several classes.
            //
            // EVERY MEMBER OF STAFF, ON EVERY PLAN. No staff_is_teacher and no
            // plan_feature here, both deliberately: the staff portal is open
            // on all three plans and this is the school's own teaching work.
            // What Basic lacks is a STUDENT portal for the note to arrive in,
            // which is a fact about that plan rather than a rule about this
            // route - see routes/student.php, where the pupil's side sits
            // behind portal_access and is therefore absent on Basic.
            Route::name('class-notes.')->prefix('class-notes')->group(function () {
                Route::get('/', [StaffClassNoteController::class, 'index'])->name('index');
                Route::post('/', [StaffClassNoteController::class, 'store'])->name('store');
                Route::get('/{note}/document', [StaffClassNoteController::class, 'download'])->name('download');
                Route::delete('/{note}', [StaffClassNoteController::class, 'destroy'])->name('destroy');
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
                // A Class Teacher may READ their class's cards and write the
                // teacher's remark. That is the whole of it.
                //
                // There is deliberately no print route and no pdf route here.
                // Taking the buttons off the page would have left the URLs
                // answering to anyone who typed them, and a report card is a
                // document the school issues - the teacher who marks it is not
                // the one who hands it out. Removed rather than gated, so
                // there is no endpoint left to reach: /results/{exam}/students/
                // {student}/print and .../pdf now 404 for the staff guard.
                //
                // The School Admin keeps both, on their own routes.
                Route::get('/', [StaffResultController::class, 'index'])->name('index');
                Route::get('/{examination}/students/{student}', [StaffResultController::class, 'show'])->name('show');
                Route::put('/{examination}/students/{student}/remarks', [StaffResultController::class, 'updateRemarks'])->name('remarks');

                // Push to Repository. A Class Teacher publishes for their own
                // class; the Repository itself is the School Admin's, and
                // there is no route to it from here.
                Route::post('/{examination}/students/{student}/push', [StaffResultController::class, 'push'])->name('push');
                Route::post('/{examination}/push', [StaffResultController::class, 'pushClass'])->name('push-class');
            });

            // CBT is premium, for the same reason as ID cards above.
            Route::middleware(['staff_is_teacher', 'plan_feature:cbt'])->name('cbt.')->prefix('cbt')->group(function () {
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
                        Route::get('/{upload}/status', [StaffCbtDocumentUploadController::class, 'status'])->name('status');
                        Route::post('/{upload}/retry', [StaffCbtDocumentUploadController::class, 'retry'])->name('retry');
                        Route::delete('/{upload}', [StaffCbtDocumentUploadController::class, 'destroy'])->name('destroy');
                    });
                });
            });
        });
    });
});
