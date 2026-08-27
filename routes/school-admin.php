<?php

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
use App\Http\Controllers\SchoolAdmin\DiaryController;
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
use App\Http\Controllers\SchoolAdmin\ResultRepositoryController;
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
use App\Support\SecureRoute as R;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| School Admin
|--------------------------------------------------------------------------
|
| Grouped from routes/authenticated.php behind ['auth', 'auth.session',
| 'school_admin', 'school_activated'] and the 'school-admin.' name prefix.
| Nothing in here restates that - the group is where it belongs.
|
*/

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

    // Login details: the username the student signs in with, and the
    // password. Both set by the School Admin, who is the authority on
    // each.
    Route::put(R::uri('students.credentials').'/{student}/credentials', [StudentController::class, 'updateCredentials'])->name('credentials');
    Route::post(R::uri('students.guardians.store').'/{student}/guardians', [StudentController::class, 'storeGuardian'])->name('guardians.store');

    // Linking a parent who already has an account, found by searching rather
    // than by retyping their email and hoping it matches. The candidates
    // endpoint is school-scoped; see the controller.
    Route::get(R::uri('students.guardian-candidates').'/{student}/guardian-candidates', [StudentController::class, 'guardianCandidates'])->name('guardian-candidates');
    Route::post(R::uri('students.guardians.link').'/{student}/guardians/link', [StudentController::class, 'linkGuardian'])->name('guardians.link');
    Route::put(R::uri('students.guardians.update-password').'/guardians/{guardian}', [StudentController::class, 'updateGuardianPassword'])->name('guardians.update-password');
    Route::delete(R::uri('students.guardians.destroy').'/{student}/guardians/{guardian}', [StudentController::class, 'destroyGuardian'])->name('guardians.destroy');
});

Route::middleware('plan_feature:guardians')->name('guardians.')->group(function () {
    Route::get(R::uri('guardians.index'), [GuardianController::class, 'index'])->name('index');
    Route::post(R::uri('guardians.index'), [GuardianController::class, 'store'])->name('store');
    Route::get(R::uri('guardians.show').'/{guardian}', [GuardianController::class, 'show'])->name('show');
    Route::put(R::uri('guardians.update').'/{guardian}', [GuardianController::class, 'update'])->name('update');
    Route::delete(R::uri('guardians.destroy').'/{guardian}', [GuardianController::class, 'destroy'])->name('destroy');
    Route::post(R::uri('guardians.toggle-active').'/{guardian}', [GuardianController::class, 'toggleActive'])->name('toggle-active');
    Route::put(R::uri('guardians.update-password').'/{guardian}/password', [GuardianController::class, 'updatePassword'])->name('update-password');
    Route::put(R::uri('guardians.credentials').'/{guardian}/credentials', [GuardianController::class, 'updateCredentials'])->name('credentials');
    // Class, then search, then select - the picker on the guardian's page.
    Route::get(R::uri('guardians.link-candidates').'/{guardian}/link-candidates', [GuardianController::class, 'linkCandidates'])->name('link-candidates');
    Route::post(R::uri('guardians.children.store').'/{guardian}/children', [GuardianController::class, 'linkStudent'])->name('children.store');
    Route::delete(R::uri('guardians.children.destroy').'/{guardian}/children/{student}', [GuardianController::class, 'unlinkStudent'])->name('children.destroy');
});

Route::middleware('plan_feature:timetable')->name('timetable.')->group(function () {
    Route::get(R::uri('timetable.index'), [TimetableController::class, 'index'])->name('index');
    Route::post(R::uri('timetable.index'), [TimetableController::class, 'store'])->name('store');
    Route::put(R::uri('timetable.update').'/{timetableEntry}', [TimetableController::class, 'update'])->name('update');
    Route::delete(R::uri('timetable.destroy').'/{timetableEntry}', [TimetableController::class, 'destroy'])->name('destroy');
});

Route::name('notices.')->group(function () {
    Route::get(R::uri('notices.index'), [NoticeController::class, 'index'])->name('index');
    Route::post(R::uri('notices.index'), [NoticeController::class, 'store'])->name('store');
});

Route::middleware('plan_feature:co-curricular')->name('co-curricular.')->group(function () {
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
    Route::put(R::uri('staff.credentials').'/{member}/credentials', [StaffController::class, 'updateCredentials'])->name('credentials');
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

Route::middleware('plan_feature:cbt')->group(function () {
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

    Route::get(R::uri('examinations.score-entry'), [ExaminationController::class, 'scoreEntry'])->name('score-entry');
    Route::post(R::uri('examinations.score-entry'), [ExaminationController::class, 'storeExaminationForEntry'])->name('score-entry.create');
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

    // Push to Repository. The same action a Class Teacher has on their own
    // class, from the page where the School Admin is already looking at the
    // finished card.
    Route::post(R::uri('results.push').'/{examination}/{student}', [ResultController::class, 'push'])->name('push');
    Route::post(R::uri('results.push-class').'/{examination}', [ResultController::class, 'pushClass'])->name('push-class');
});

/*
 * The Result Repository.
 *
 * Inside the School Admin group and nowhere else, which is the whole access
 * rule: teachers push into the repository from their own results page and have
 * no route to this one, and pupils and parents reach published cards only
 * through the result-checking link.
 */
Route::name('result-repository.')->group(function () {
    Route::get(R::uri('result-repository.index'), [ResultRepositoryController::class, 'index'])->name('index');
    Route::get(R::uri('result-repository.show').'/{result}', [ResultRepositoryController::class, 'show'])->name('show');
});
// Result tokens. Available on every plan - see
// EnsureSchoolHasResultPinAccess - because a token is how a result
// reaches a parent safely, not a feature a school upgrades to.
Route::middleware('result_pin_access')->name('result-pins.')->group(function () {
    Route::get(R::uri('result-pins.index'), [ResultCheckingPinController::class, 'index'])->name('index');

    // Issuing binds the token to its student and examination, so there
    // is no separate "assign" step any more: an unbound token never
    // exists in the first place.
    Route::post(R::uri('result-pins.store'), [ResultCheckingPinController::class, 'store'])->name('store');
    Route::post(R::uri('result-pins.store-bulk'), [ResultCheckingPinController::class, 'storeBulk'])->name('store-bulk');

    Route::post(R::uri('result-pins.reissue').'/{pin}', [ResultCheckingPinController::class, 'reissue'])->name('reissue');
    Route::post(R::uri('result-pins.reveal').'/{pin}', [ResultCheckingPinController::class, 'reveal'])->name('reveal');
    Route::post(R::uri('result-pins.revoke').'/{pin}', [ResultCheckingPinController::class, 'revoke'])->name('revoke');
    Route::post(R::uri('result-pins.suspend').'/{pin}', [ResultCheckingPinController::class, 'toggleSuspension'])->name('suspend');

    // The school's own result-checking address, which the School Admin
    // hands to parents. Regenerating retires the old one for good.
    Route::post(R::uri('result-pins.link-regenerate'), [ResultCheckingPinController::class, 'regenerateLink'])->name('link.regenerate');
    Route::post(R::uri('result-pins.link-toggle'), [ResultCheckingPinController::class, 'toggleLink'])->name('link.toggle');

    // Releasing a withheld result is the school's decision, taken per
    // student and per term, and recorded as a decision rather than
    // applied as a setting.
    Route::post(R::uri('result-pins.fee-release').'/{student}', [ResultCheckingPinController::class, 'toggleFeeRelease'])->name('fee-release');
});
// The route prefix is "diary", which is the PlanFeature's own value -
// so the sidebar, the module card and this gate all reach the same
// answer without anyone keeping a second list.
Route::middleware('plan_feature:diary')->name('diary.')->group(function () {
    Route::get(R::uri('diary.index'), [DiaryController::class, 'index'])->name('index');
    Route::post(R::uri('diary.seen').'/{entry}', [DiaryController::class, 'markSeen'])->name('seen');
});

Route::middleware('plan_feature:assignments')->name('assignments.')->group(function () {
    Route::get(R::uri('assignments.index'), [AssignmentController::class, 'index'])->name('index');
    Route::post(R::uri('assignments.index'), [AssignmentController::class, 'store'])->name('store');
    Route::get(R::uri('assignments.show').'/{assignment}', [AssignmentController::class, 'show'])->name('show');
    Route::put(R::uri('assignments.update').'/{assignment}', [AssignmentController::class, 'update'])->name('update');
    Route::delete(R::uri('assignments.destroy').'/{assignment}', [AssignmentController::class, 'destroy'])->name('destroy');
    Route::post(R::uri('assignments.submissions.store').'/{assignment}', [AssignmentController::class, 'storeSubmissions'])->name('submissions.store');
});
Route::middleware('plan_feature:events')->name('events.')->group(function () {
    Route::get(R::uri('events.index'), [EventController::class, 'index'])->name('index');
    Route::post(R::uri('events.index'), [EventController::class, 'store'])->name('store');
    Route::put(R::uri('events.update').'/{event}', [EventController::class, 'update'])->name('update');
    Route::delete(R::uri('events.destroy').'/{event}', [EventController::class, 'destroy'])->name('destroy');
});
Route::middleware('plan_feature:library')->name('library.')->group(function () {
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

Route::middleware('plan_feature:transport')->name('transport.')->group(function () {
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

Route::middleware('plan_feature:hostels')->name('hostels.')->group(function () {
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

Route::middleware('plan_feature:finance')->name('finance.')->group(function () {
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
Route::middleware('plan_feature:website')->name('website.')->group(function () {
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
Route::middleware('plan_feature:news')->name('news.')->group(function () {
    Route::get(R::uri('news.index'), [NewsController::class, 'index'])->name('index');
    Route::post(R::uri('news.index'), [NewsController::class, 'store'])->name('store');
    Route::put(R::uri('news.update').'/{post}', [NewsController::class, 'update'])->name('update');
    Route::delete(R::uri('news.destroy').'/{post}', [NewsController::class, 'destroy'])->name('destroy');
});
Route::middleware('plan_feature:careers')->name('careers.')->group(function () {
    Route::get(R::uri('careers.index'), [JobPostingController::class, 'index'])->name('index');
    Route::post(R::uri('careers.index'), [JobPostingController::class, 'store'])->name('store');
    Route::put(R::uri('careers.update').'/{job}', [JobPostingController::class, 'update'])->name('update');
    Route::delete(R::uri('careers.destroy').'/{job}', [JobPostingController::class, 'destroy'])->name('destroy');
    Route::post(R::uri('careers.toggle-active').'/{job}', [JobPostingController::class, 'toggleActive'])->name('toggle-active');
});
Route::middleware('plan_feature:testimonials')->name('testimonials.')->group(function () {
    Route::get(R::uri('testimonials.index'), [TestimonialController::class, 'index'])->name('index');
    Route::post(R::uri('testimonials.index'), [TestimonialController::class, 'store'])->name('store');
    Route::put(R::uri('testimonials.update').'/{testimonial}', [TestimonialController::class, 'update'])->name('update');
    Route::delete(R::uri('testimonials.destroy').'/{testimonial}', [TestimonialController::class, 'destroy'])->name('destroy');
});
Route::middleware('plan_feature:facilities')->name('facilities.')->group(function () {
    Route::get(R::uri('facilities.index'), [FacilityController::class, 'index'])->name('index');
    Route::post(R::uri('facilities.index'), [FacilityController::class, 'store'])->name('store');
    Route::put(R::uri('facilities.update').'/{facility}', [FacilityController::class, 'update'])->name('update');
    Route::delete(R::uri('facilities.destroy').'/{facility}', [FacilityController::class, 'destroy'])->name('destroy');
});

Route::middleware('plan_feature:id-cards')->name('id-cards.')->group(function () {
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

Route::middleware('plan_feature:custom-domain')->name('custom-domain.')->group(function () {
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
