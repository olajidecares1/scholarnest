<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ResultTokenIssuer;

/**
 * The exam token, entered inside a Standard or Exclusive portal.
 *
 * Signing in proves you are entitled to an account. It does not follow that
 * you may open a particular result: the same 15-character token a Basic school
 * hands out on a slip of paper has to be typed in here first, and it has to be
 * the token for THIS student and THIS term.
 */
function portalSchool(PlanKey $planKey = PlanKey::Standard): array
{
    $school = School::factory()->create();
    activateSchool($school, $planKey);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'term' => ExamTerm::Second->value,
        'session' => '2025/2026',
    ]);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'is_active' => true,
    ]);

    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);
    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'score' => 80,
    ]);

    return [$school->fresh(), $admin, $examination, $student];
}

function tokenFor(School $school, Student $student, Examination $examination, User $admin): string
{
    return app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin)['plain'];
}

// -----------------------------------------------------------------------------
// The student portal
// -----------------------------------------------------------------------------

test('a student portal result is shut until its token is entered', function (string $route) {
    [$school, , $examination, $student] = portalSchool();

    // Signed in, no fees owing, and still refused. Being the right student is
    // not the same as having been given the result.
    $this->actingAs($student, 'student')
        ->get(route($route, [$school, $examination]))
        ->assertForbidden();
})->with([
    'view' => 'student.results.show',
    'print' => 'student.results.print',
    'download' => 'student.results.pdf',
]);

test('the right token opens the result, and it stays open', function () {
    [$school, $admin, $examination, $student] = portalSchool();
    $token = tokenFor($school, $student, $examination, $admin);

    $this->actingAs($student, 'student')
        ->post(route('student.results.unlock', [$school, $examination]), ['token' => $token])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // Viewing and downloading, which the brief asks for by name.
    $this->actingAs($student, 'student')->get(route('student.results.show', [$school, $examination]))->assertOk();
    $this->actingAs($student, 'student')->get(route('student.results.pdf', [$school, $examination]))->assertOk();
    $this->actingAs($student, 'student')->get(route('student.results.print', [$school, $examination]))->assertOk();
});

test('the portal asks for the token before it is entered and stops asking after', function () {
    [$school, $admin, $examination, $student] = portalSchool();
    $token = tokenFor($school, $student, $examination, $admin);

    $this->actingAs($student, 'student')
        ->get(route('student.results.index', $school))
        ->assertOk()
        ->assertSee('Enter Exam Token')
        ->assertDontSee(route('student.results.pdf', [$school, $examination]));

    $this->actingAs($student, 'student')
        ->post(route('student.results.unlock', [$school, $examination]), ['token' => $token]);

    $this->actingAs($student, 'student')
        ->get(route('student.results.index', $school))
        ->assertOk()
        ->assertSee('View Report Card')
        ->assertSee(route('student.results.pdf', [$school, $examination]));
});

test('a wrong token opens nothing and says nothing about why', function () {
    [$school, , $examination, $student] = portalSchool();

    $this->actingAs($student, 'student')
        ->post(route('student.results.unlock', [$school, $examination]), ['token' => '25262AAAAAAAAAA'])
        ->assertSessionHasErrors('token');

    $this->actingAs($student, 'student')
        ->get(route('student.results.show', [$school, $examination]))
        ->assertForbidden();
});

test('one term\'s token does not open another term', function () {
    [$school, $admin, $secondTerm, $student] = portalSchool();

    $thirdTerm = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'term' => ExamTerm::Third->value,
        'session' => '2025/2026',
    ]);

    $token = tokenFor($school, $student, $secondTerm, $admin);

    $this->actingAs($student, 'student')
        ->post(route('student.results.unlock', [$school, $thirdTerm]), ['token' => $token])
        ->assertSessionHasErrors('token');

    $this->actingAs($student, 'student')
        ->get(route('student.results.show', [$school, $thirdTerm]))
        ->assertForbidden();
});

test('another student\'s token is refused, and is not spent trying', function () {
    [$school, $admin, $examination, $student] = portalSchool();

    $classmate = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'is_active' => true,
    ]);

    // Marked, so this test turns on the token being for the wrong student
    // rather than on their result simply not existing yet.
    ExaminationScore::factory()->create([
        'examination_subject_id' => $examination->subjects()->firstOrFail()->id,
        'student_id' => $classmate->id,
        'score' => 65,
    ]);

    $theirToken = tokenFor($school, $classmate, $examination, $admin);
    $theirPin = $school->resultCheckingPins()->where('bound_student_id', $classmate->id)->firstOrFail();

    $this->actingAs($student, 'student')
        ->post(route('student.results.unlock', [$school, $examination]), ['token' => $theirToken])
        ->assertSessionHasErrors('token');

    $this->actingAs($student, 'student')
        ->get(route('student.results.show', [$school, $examination]))
        ->assertForbidden();

    // The binding is checked before the use is counted, so trying somebody
    // else's token has not burned one of their views.
    expect($theirPin->fresh()->uses_count)->toBe(0);

    // And it is recorded for the school to see, as something other than a
    // token that does not exist.
    $this->assertDatabaseHas('result_token_access_logs', [
        'result_checking_pin_id' => $theirPin->id,
        'outcome' => 'bound_elsewhere',
    ]);
});

test('unlocking one result does not unlock the others', function () {
    [$school, $admin, $secondTerm, $student] = portalSchool();

    $thirdTerm = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'term' => ExamTerm::Third->value,
        'session' => '2025/2026',
    ]);

    $this->actingAs($student, 'student')->post(
        route('student.results.unlock', [$school, $secondTerm]),
        ['token' => tokenFor($school, $student, $secondTerm, $admin)],
    );

    $this->actingAs($student, 'student')->get(route('student.results.show', [$school, $secondTerm]))->assertOk();
    $this->actingAs($student, 'student')->get(route('student.results.show', [$school, $thirdTerm]))->assertForbidden();
});

test('unpaid fees are not something a token can buy past', function () {
    [$school, $admin, $examination, $student] = portalSchool();
    $token = tokenFor($school, $student, $examination, $admin);

    Invoice::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'amount' => 25000,
    ]);

    // A token decides WHO may see a result, not WHETHER the school is ready to
    // hand it over. Refused before the token is even looked at, so it is not
    // spent on a result that stays shut anyway.
    $this->actingAs($student, 'student')
        ->post(route('student.results.unlock', [$school, $examination]), ['token' => $token])
        ->assertForbidden();

    expect($school->resultCheckingPins()->first()->uses_count)->toBe(0);
});

test('the portal shows the fee hold rather than an unanswerable token prompt', function () {
    [$school, , $examination, $student] = portalSchool();

    Invoice::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'amount' => 25000,
    ]);

    // No token would open this, so none is asked for.
    $this->actingAs($student, 'student')
        ->get(route('student.results.index', $school))
        ->assertOk()
        ->assertSee('Locked')
        ->assertSee('outstanding school-fee balance')
        ->assertDontSee('Enter Exam Token');
});

// -----------------------------------------------------------------------------
// The guardian portal
// -----------------------------------------------------------------------------

test('a guardian must enter the token for the child whose result they are opening', function () {
    [$school, $admin, $examination, $student] = portalSchool();

    $sibling = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'is_active' => true,
    ]);

    $guardian = Guardian::factory()->create(['school_id' => $school->id, 'is_active' => true]);
    $guardian->students()->attach([$student->id, $sibling->id]);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.children.results.show', [$school, $student, $examination]))
        ->assertForbidden();

    // A guardian with two children at the school holds two tokens. The one for
    // the other child must not open this one.
    $this->actingAs($guardian, 'guardian')
        ->post(route('guardian.children.results.unlock', [$school, $student, $examination]), [
            'token' => tokenFor($school, $sibling, $examination, $admin),
        ])
        ->assertSessionHasErrors('token');

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.children.results.show', [$school, $student, $examination]))
        ->assertForbidden();

    // The right one does open it, and the download with it.
    $this->actingAs($guardian, 'guardian')
        ->post(route('guardian.children.results.unlock', [$school, $student, $examination]), [
            'token' => tokenFor($school, $student, $examination, $admin),
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.children.results.show', [$school, $student, $examination]))
        ->assertOk();

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.children.results.pdf', [$school, $student, $examination]))
        ->assertOk();
});

test('a guardian cannot unlock a child who is not theirs', function () {
    [$school, $admin, $examination, $student] = portalSchool();

    $stranger = Guardian::factory()->create(['school_id' => $school->id, 'is_active' => true]);

    $this->actingAs($stranger, 'guardian')
        ->post(route('guardian.children.results.unlock', [$school, $student, $examination]), [
            'token' => tokenFor($school, $student, $examination, $admin),
        ])
        ->assertForbidden();
});

// -----------------------------------------------------------------------------
// Basic is unchanged
// -----------------------------------------------------------------------------

test('the basic plan keeps the link-and-token route, with no portal in the way', function () {
    [$school, $admin, $examination, $student] = portalSchool(PlanKey::Basic);
    $token = tokenFor($school, $student, $examination, $admin);

    // No account, no modal, no portal, the token is typed into the school's
    // own result address and the result comes back.
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->followingRedirects()
        ->post(route('check-result.verify', $school), ['code' => $token])
        ->assertOk()
        ->assertSee($student->fullName());
});
