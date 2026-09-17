<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\CbtTestStatus;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\CbtAttempt;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtQuestion;
use App\Models\CbtQuestionOption;
use App\Models\CbtSubject;
use App\Models\CbtTest;
use App\Models\CbtTestAttempt;
use App\Models\CbtTestDocumentUpload;
use App\Models\CbtTestQuestion;
use App\Models\CbtTestQuestionOption;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Every page opens without throwing.
 *
 * THIS TEST EXISTS BECAUSE OF REAL 500s. A page is only as tested as the one
 * test that happens to open it, and the ones nobody opened have shipped broken
 * more than once: a class used but never imported, a variable a view expects
 * and a controller stopped passing, a column a query names before its migration
 * runs. Each is invisible until somebody clicks, and by then it is live.
 *
 * So this walks every named GET route, as each kind of account, and fails on a
 * 500. Anything else is fine: a redirect to sign in, a 403, a 404 for a record
 * that does not exist here, are all the application working.
 *
 * A route whose parameters cannot be filled from the fixtures below is skipped
 * rather than guessed at, and the test reports how many pages it actually
 * opened, so that a fixture that stops matching shows up as coverage lost
 * rather than as a silent pass.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create());

    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    $this->schoolAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    $this->staff = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'SSS 3']);
    $this->guardian = Guardian::factory()->create([
        'school_id' => $this->school->id,
        'guardian_number' => 'PAR-SWEEP-1',
    ]);

    // The CBT catalogue: an exam body, a paper, a question a student can sit,
    // and the upload it came from.
    $examBody = CbtExamBody::factory()->create();
    $subject = CbtSubject::factory()->create();
    $this->exam = CbtExam::factory()->create(['cbt_exam_body_id' => $examBody->id, 'cbt_subject_id' => $subject->id]);

    $this->upload = CbtDocumentUpload::factory()->create([
        'uploaded_by' => $this->superAdmin->id,
        'cbt_exam_body_id' => $examBody->id,
        'cbt_subject_id' => $subject->id,
        'status' => CbtDocumentUploadStatus::Completed,
        'questions_extracted_count' => 1,
    ]);

    $this->question = CbtQuestion::factory()->create([
        'cbt_exam_id' => $this->exam->id,
        'cbt_document_upload_id' => $this->upload->id,
        'question_number' => 1,
        'passage' => 'A passage the question belongs to.',
        'is_published' => true,
    ]);
    CbtQuestionOption::factory()->create(['cbt_question_id' => $this->question->id, 'label' => 'A', 'is_correct' => true]);

    // A question as the old reader saved it: the options and the answer welded
    // into the wording, and no options of its own. Real papers on the live site
    // look like this until they are read again, and a page that cannot draw one
    // is a page nobody can sit.
    CbtQuestion::factory()->create([
        'cbt_exam_id' => $this->exam->id,
        'cbt_document_upload_id' => $this->upload->id,
        'question_text' => 'Which paper type is given to you?A. Type AB. Type BC. Type CD. Type D ✓ Correct Answer: A',
        'question_number' => null,
        'is_published' => true,
    ]);

    $this->attempt = CbtAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_exam_id' => $this->exam->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 1,
    ]);

    // A teacher's own test, made from a document, with a student sitting it.
    $this->test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->staff->id,
        'class_name' => $this->student->class_name,
        'status' => CbtTestStatus::Published,
    ]);

    $this->testUpload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $this->test->id,
        'staff_id' => $this->staff->id,
        'status' => CbtDocumentUploadStatus::Completed,
        'questions_extracted_count' => 1,
    ]);

    $testQuestion = CbtTestQuestion::factory()->create([
        'cbt_test_id' => $this->test->id,
        'cbt_test_document_upload_id' => $this->testUpload->id,
        'question_number' => 1,
        'passage' => 'A passage the question belongs to.',
    ]);
    CbtTestQuestionOption::factory()->create(['cbt_test_question_id' => $testQuestion->id, 'label' => 'A', 'is_correct' => true]);

    // The same shape of damage on the teachers' side.
    CbtTestQuestion::factory()->create([
        'cbt_test_id' => $this->test->id,
        'cbt_test_document_upload_id' => $this->testUpload->id,
        'question_text' => 'Who wrote De Graft&#039;s Sons and Daughters?A. HimB. Her ✓ Correct Answer: A',
        'question_number' => null,
    ]);

    $this->testAttempt = CbtTestAttempt::factory()->create([
        'student_id' => $this->student->id,
        'cbt_test_id' => $this->test->id,
        'expires_at' => now()->addMinutes(30),
        'total_questions' => 1,
    ]);
});

/**
 * What each route parameter stands for here.
 *
 * @return array<string, string>|null null when this route names something the
 *                                    fixtures do not have
 */
function pageParameters(Illuminate\Routing\Route $route, object $fixtures): ?array
{
    $models = [
        'school' => $fixtures->school,
        'exam' => $fixtures->exam,
        'examBody' => $fixtures->exam->examBody,
        'subject' => $fixtures->exam->subject,
        'upload' => $fixtures->upload,
        'question' => $fixtures->question,
        'attempt' => $fixtures->attempt,
        'test' => $fixtures->test,
        'student' => $fixtures->student,
        'staff' => $fixtures->staff,
        'guardian' => $fixtures->guardian,
        'user' => $fixtures->schoolAdmin,
    ];

    $values = [];

    foreach ($route->parameterNames() as $name) {
        if (! isset($models[$name])) {
            return null;
        }

        $values[$name] = $models[$name]->getRouteKey();
    }

    return $values;
}

/**
 * Sign in the way this kind of account really signs in.
 *
 * Deliberately not acting-as with a portal guard: that makes the portal guard
 * the default one for the whole request, which no browser does, and the
 * difference invents failures on pages a real session is only redirected away
 * from.
 */
function signInAs(?string $who, object $fixtures): void
{
    match ($who) {
        null => null,
        'superAdmin', 'schoolAdmin' => test()->actingAs($fixtures->{$who}, 'web'),
        'staff' => test()->post(route('portal.attempt'), [
            'role' => 'staff',
            'login' => $fixtures->staff->staff_number,
            'password' => 'password',
        ])->assertSessionHasNoErrors(),
        'student' => test()->post(route('portal.attempt'), [
            'role' => 'student',
            'login' => $fixtures->student->admission_number,
            'password' => 'password',
        ])->assertSessionHasNoErrors(),
        'guardian' => test()->post(route('portal.attempt'), [
            'role' => 'guardian',
            'login' => $fixtures->guardian->guardian_number,
            'password' => 'password',
        ])->assertSessionHasNoErrors(),
    };
}

dataset('accounts', [
    'the AkademicNest Team' => ['superAdmin'],
    'a School Admin' => ['schoolAdmin'],
    'a teacher' => ['staff'],
    'a student' => ['student'],
    'a parent' => ['guardian'],
    'a visitor' => [null],
]);

test('no page throws', function (?string $who) {
    // Read before anything signs in: the auth middleware writes the guard it
    // matched back into this same config value, so it has to be remembered
    // rather than looked up again later.
    $defaultGuard = config('auth.defaults.guard');

    signInAs($who, $this);

    $opened = 0;
    $broken = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if ($name === null || ! in_array('GET', $route->methods(), true)) {
            continue;
        }

        // Signing out would end the session the rest of the sweep needs, and a
        // file response is not a page.
        if (preg_match('/logout|download|\.pdf|preview$/i', $name)) {
            continue;
        }

        $parameters = pageParameters($route, $this);

        if ($parameters === null) {
            continue;
        }

        try {
            $url = route($name, $parameters);
        } catch (Throwable) {
            continue;
        }

        // Laravel's auth middleware makes whichever guard matched the default
        // one. A browser starts each request fresh, but this whole sweep runs
        // in one process, so without resetting it a page from the student
        // portal would leave the student as the default account for pages that
        // are not theirs, and invent failures nobody can reach.
        auth()->shouldUse($defaultGuard);

        $response = $this->get($url);

        if ($response->getStatusCode() >= 500) {
            $why = $response->exception;
            $signedIn = collect(array_keys(config('auth.guards')))
                ->filter(fn (string $guard) => auth($guard)->check())
                ->implode(',');
            $broken[] = $name.' [guards: '.($signedIn ?: 'none').', default: '.auth()->getDefaultDriver().'] -> '
                .($why === null ? $response->getStatusCode() : get_class($why).': '.$why->getMessage());

            continue;
        }

        $opened++;
    }

    expect($broken)->toBe([], 'These pages returned a server error: '.implode(', ', $broken));

    // Enough pages actually opened for the sweep to mean something.
    expect($opened)->toBeGreaterThan(100);
})->with('accounts');
