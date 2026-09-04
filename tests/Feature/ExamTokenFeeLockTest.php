<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Invoice;
use App\Models\ResultCheckingPin;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ResultAccessPolicy;
use App\Services\ResultTokenIssuer;

/**
 * Exam tokens on every plan, and a result that stays shut while fees are owed.
 *
 * Two rules, and they are separate. A token says WHO may see a result. Fees
 * say WHETHER the school is ready to hand it over. A valid token held by the
 * right parent still opens nothing while there is a balance, and settling the
 * balance opens it without anyone reissuing anything.
 */
function feeLockSchool(PlanKey $planKey = PlanKey::Basic): array
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

    // A marked result. Without one the verifier refuses the token as
    // "result unavailable" before the fee rule is ever reached, which would
    // have made these tests pass for the wrong reason.
    $subject = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'max_score' => 100,
    ]);

    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'score' => 80,
    ]);

    return [$school->fresh(), $admin, $examination, $student];
}

function oweFees(Student $student, float $amount = 25000): void
{
    Invoice::factory()->create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'amount' => $amount,
    ]);
}

// -----------------------------------------------------------------------------
// The token itself
// -----------------------------------------------------------------------------

test('every exam token is exactly fifteen characters', function (string $session, ExamTerm $term) {
    $token = ResultCheckingPin::generatePlainToken($session, $term);

    expect($token)->toHaveLength(15)
        ->and(ResultCheckingPin::TOKEN_LENGTH)->toBe(15);
})->with([
    'standard session' => ['2025/2026', ExamTerm::First],
    'hyphenated' => ['2024-2025', ExamTerm::Second],
    'single year' => ['2026', ExamTerm::Third],
    // A session is free text on the examination, so it may be anything at all.
    // The length is a promise regardless.
    'nonsense' => ['not a session', ExamTerm::First],
    'empty' => ['', ExamTerm::Second],
]);

test('a token carries its own academic year and term', function () {
    expect(ResultCheckingPin::generatePlainToken('2025/2026', ExamTerm::First))->toStartWith('25261')
        ->and(ResultCheckingPin::generatePlainToken('2025/2026', ExamTerm::Second))->toStartWith('25262')
        ->and(ResultCheckingPin::generatePlainToken('2025/2026', ExamTerm::Third))->toStartWith('25263')
        ->and(ResultCheckingPin::generatePlainToken('2026/2027', ExamTerm::First))->toStartWith('26271');
});

test('the random half of a token is not guessable from the term it belongs to', function () {
    // Two tokens for the same term share their first five characters by
    // design. If they shared any more than that, the term would be the token.
    $tokens = collect(range(1, 40))->map(fn () => ResultCheckingPin::generatePlainToken('2025/2026', ExamTerm::First));

    expect($tokens->unique())->toHaveCount(40)
        ->and($tokens->map(fn (string $token) => substr($token, 5))->unique())->toHaveCount(40);
});

test('a school issues its own tokens on every plan', function (PlanKey $planKey) {
    [$school, $admin, $examination, $student] = feeLockSchool($planKey);

    // The token system is not a consolation prize for schools without portals.
    // A Standard school still has students whose results are withheld and
    // still needs a way to hand one of them a link.
    $this->actingAs($admin)
        ->post(route('result-pins.store'), [
            'student_id' => $student->id,
            'examination_id' => $examination->id,
        ])
        ->assertRedirect();

    expect($school->resultCheckingPins()->count())->toBe(1);
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);

test('the exam token module is offered on every plan, not only basic', function (PlanKey $planKey) {
    [, $admin] = feeLockSchool($planKey);

    // The backend has always allowed this on every plan. The dashboard offered
    // it to Basic alone, so a Standard school had the feature and no way to
    // reach it.
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('result-pins.index'));
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);

// -----------------------------------------------------------------------------
// The fee lock, through a token
// -----------------------------------------------------------------------------

test('a valid token opens nothing while fees are owed, and opens once they are not', function () {
    [$school, $admin, $examination, $student] = feeLockSchool();
    oweFees($student, 25000);

    $issued = app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin);

    $redeem = function () use ($school, $student, $issued) {
        identifyForResultCheck($school, $student);

        return $this->post(route('check-result.verify', $school), ['code' => $issued['plain']]);
    };

    // Withheld. The token is not at fault and the page says so, because a
    // parent told to "check your token" would retype a token that was never
    // wrong and then telephone the school about it.
    $redeem();
    identifyForResultCheck($school, $student);
    $this->followingRedirects()->post(route('check-result.verify', $school), ['code' => $issued['plain']])
        ->assertOk()
        ->assertSee('This result is on hold')
        ->assertSee('Your token is still valid')
        ->assertDontSee('Report Card');

    // The balance is settled. Nothing is reissued; the same token now works.
    $student->invoices()->delete();

    $this->followingRedirects()->post(route('check-result.verify', $school), ['code' => $issued['plain']])
        ->assertOk()
        ->assertDontSee('This result is on hold');
});

test('a student with nothing owing is never locked', function () {
    [$school, $admin, $examination, $student] = feeLockSchool();

    // A school that has raised no fees owes nothing, which is what makes this
    // rule safe to apply on a plan with no Finance module at all.
    expect(app(ResultAccessPolicy::class)->isLocked($student, $examination))->toBeFalse();

    $issued = app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin);

    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->followingRedirects()
        ->post(route('check-result.verify', $school), ['code' => $issued['plain']])
        ->assertOk()
        ->assertDontSee('This result is on hold');
});

test('a part payment still leaves the result locked', function () {
    [, , $examination, $student] = feeLockSchool();

    $invoice = Invoice::factory()->create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'amount' => 25000,
    ]);
    $invoice->payments()->create(['amount' => 24000, 'paid_at' => now(), 'method' => 'cash']);

    // Nearly paid is not paid.
    expect(app(ResultAccessPolicy::class)->outstandingBalance($student->fresh()))->toEqual(1000.0)
        ->and(app(ResultAccessPolicy::class)->isLocked($student->fresh(), $examination))->toBeTrue();
});

// -----------------------------------------------------------------------------
// The school's own release
// -----------------------------------------------------------------------------

test('the school can release one student\'s results for one term despite the balance', function () {
    [$school, $admin, $examination, $student] = feeLockSchool();
    oweFees($student);

    $this->actingAs($admin)->post(route('result-pins.fee-release', $student), [
        'session' => $examination->session,
        'term' => $examination->term->value,
        'reason' => 'On a payment plan agreed with the bursar.',
    ])->assertRedirect();

    expect(app(ResultAccessPolicy::class)->isLocked($student->fresh(), $examination))->toBeFalse();

    $this->assertDatabaseHas('result_fee_clearances', [
        'student_id' => $student->id,
        'session' => $examination->session,
        'released_by' => $admin->id,
        'reason' => 'On a payment plan agreed with the bursar.',
    ]);
});

test('a release covers one term only', function () {
    [, $admin, $examination, $student] = feeLockSchool();
    oweFees($student);

    $thirdTerm = Examination::factory()->create([
        'school_id' => $student->school_id,
        'class_name' => 'JSS 1',
        'term' => ExamTerm::Third->value,
        'session' => '2025/2026',
    ]);

    $this->actingAs($admin)->post(route('result-pins.fee-release', $student), [
        'session' => '2025/2026',
        'term' => ExamTerm::Second->value,
    ]);

    // Clearing a student for Second Term is not clearing them for the year.
    expect(app(ResultAccessPolicy::class)->isLocked($student->fresh(), $examination))->toBeFalse()
        ->and(app(ResultAccessPolicy::class)->isLocked($student->fresh(), $thirdTerm))->toBeTrue();
});

test('a release can be withdrawn', function () {
    [, $admin, $examination, $student] = feeLockSchool();
    oweFees($student);

    $payload = ['session' => $examination->session, 'term' => $examination->term->value];

    $this->actingAs($admin)->post(route('result-pins.fee-release', $student), $payload);
    expect(app(ResultAccessPolicy::class)->isLocked($student->fresh(), $examination))->toBeFalse();

    $this->actingAs($admin)->post(route('result-pins.fee-release', $student), $payload);
    expect(app(ResultAccessPolicy::class)->isLocked($student->fresh(), $examination))->toBeTrue();
});

test('a school cannot release a student who is not theirs', function () {
    [, $admin] = feeLockSchool();
    [, , $otherExamination, $otherStudent] = feeLockSchool();

    $this->actingAs($admin)->post(route('result-pins.fee-release', $otherStudent), [
        'session' => $otherExamination->session,
        'term' => $otherExamination->term->value,
    ])->assertNotFound();

    $this->assertDatabaseCount('result_fee_clearances', 0);
});

// -----------------------------------------------------------------------------
// The portals, for the plans that have them
// -----------------------------------------------------------------------------

test('a locked result is refused in the student portal on every route that produces it', function (string $route) {
    [$school, , $examination, $student] = feeLockSchool(PlanKey::Standard);
    oweFees($student);

    // Server-side, on the JSON view, the printable page and the PDF alike.
    // Editing the address is not a way round a balance.
    $this->actingAs($student, 'student')
        ->get(route($route, [$school, $examination]))
        ->assertForbidden();
})->with([
    'view' => 'student.results.show',
    'print' => 'student.results.print',
    'download' => 'student.results.pdf',
]);

test('the student portal shows the result as locked rather than as a broken button', function () {
    [$school, , $examination, $student] = feeLockSchool(PlanKey::Standard);
    oweFees($student, 25000);

    $response = $this->actingAs($student, 'student')
        ->get(route('student.results.index', $school))
        ->assertOk();

    // A button that opens an error is worse than no button: it says something
    // is broken when the truth is the school is waiting to be paid.
    $response->assertSee('Locked')
        ->assertSee('outstanding school-fee balance')
        ->assertDontSee(route('student.results.pdf', [$school, $examination]));
});

test('the fee hold and the token gate are two separate locks', function () {
    [$school, $admin, $examination, $student] = feeLockSchool(PlanKey::Standard);
    oweFees($student);

    // Owing money. Refused, and no token will help - the unlock route turns
    // the token away before it is even read, so it is not spent on a result
    // that stays shut anyway.
    $this->actingAs($student, 'student')
        ->get(route('student.results.show', [$school, $examination]))
        ->assertForbidden();

    $this->actingAs($student, 'student')
        ->post(route('student.results.unlock', [$school, $examination]), [
            'token' => app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin)['plain'],
        ])
        ->assertForbidden();

    // Balance settled. Still refused, because the second lock is untouched by
    // the first: paying does not hand over the result, entering the token does.
    $student->invoices()->delete();

    $this->actingAs($student, 'student')
        ->get(route('student.results.show', [$school, $examination]))
        ->assertForbidden();

    // Both open now.
    enterExamToken($student, 'student', $school, $student, $examination, 'student.results.unlock', [$school, $examination]);

    $this->actingAs($student, 'student')
        ->get(route('student.results.show', [$school, $examination]))
        ->assertOk();
});

// -----------------------------------------------------------------------------
// The school's own badge, on the school's own result page
// -----------------------------------------------------------------------------

test('the result checker wears the school\'s logo, not the platform\'s', function () {
    [$school, $admin, $examination, $student] = feeLockSchool();
    $school->update(['logo_path' => 'logos/marvel.png']);

    // A parent typing a token into their child's school's address was shown
    // ScholarNest's badge, and the same badge whichever school the address
    // belonged to - nothing on the page said whose result this was.
    $this->get($school->resultLinkUrl())
        ->assertOk()
        ->assertSee($school->fresh()->logoUrl())
        ->assertSee($school->name)
        ->assertDontSee('Edu<span class="text-primary-500">Nest</span>', false);
});

test('a school with no logo gets its own initial, never another school\'s badge', function () {
    [$school] = feeLockSchool();

    // A stand-in from somewhere else would be a different school's mark on
    // this school's result.
    expect($school->fresh()->logoUrl())->toBeNull();

    $this->get($school->resultLinkUrl())
        ->assertOk()
        ->assertSee($school->name);
});

test('one school\'s branding never appears on another school\'s result page', function () {
    [$schoolA] = feeLockSchool();
    [$schoolB] = feeLockSchool();

    $schoolA->update(['logo_path' => 'logos/a.png']);
    $schoolB->update(['logo_path' => 'logos/b.png']);

    $this->get($schoolA->fresh()->resultLinkUrl())
        ->assertOk()
        ->assertSee($schoolA->name)
        ->assertDontSee($schoolB->name)
        ->assertDontSee($schoolB->fresh()->logoUrl());
});

test('the result itself carries the school it belongs to', function () {
    [$school, $admin, $examination, $student] = feeLockSchool();
    $school->update(['logo_path' => 'logos/marvel.png']);

    $issued = app(ResultTokenIssuer::class)->issue($school->fresh(), $student, $examination, $admin);

    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->followingRedirects()
        ->post(route('check-result.verify', $school), ['code' => $issued['plain']])
        ->assertOk()
        ->assertSee($school->name)
        ->assertSee($school->fresh()->logoUrl())
        ->assertSee($student->fullName());
});
