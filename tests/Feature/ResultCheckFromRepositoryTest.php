<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\RepositoryResult;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ResultRepository;
use App\Services\ResultTokenIssuer;
use Illuminate\Testing\TestResponse;

/**
 * A school, a pupil, one marked subject, and a token bound to the two.
 *
 * @return array{school: School, student: Student, examination: Examination, admin: User, subject: ExaminationSubject}
 */
function resultCheckFixture(PlanKey $plan): array
{
    $school = activateSchool(School::factory()->create(), $plan);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'admission_number' => 'GRN/001',
    ]);

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'session' => '2025/2026',
        'term' => ExamTerm::First,
    ]);

    $subject = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'name' => 'Mathematics',
        'max_score' => 100,
    ]);

    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'test_score' => 30,
        'exam_score' => 42,
        'score' => 72,
    ]);

    return compact('school', 'student', 'examination', 'admin', 'subject');
}

/**
 * Walk the whole checking flow: name the pupil, then redeem the token.
 */
function checkResult(array $fixture): TestResponse
{
    ['school' => $school, 'student' => $student, 'examination' => $examination, 'admin' => $admin] = $fixture;

    $token = app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin)['plain'];

    // Step one names the pupil; the token post is only checked against a pupil
    // already identified, so it goes nowhere on its own.
    identifyForResultCheck($school, $student);

    return test()->followingRedirects()
        ->post(route('check-result.verify', $school), ['code' => $token]);
}

test('a Basic school\'s result comes from the Repository, not the live marks', function () {
    $fixture = resultCheckFixture(PlanKey::Basic);

    test()->actingAs($fixture['admin'])
        ->post(route('results.push', [$fixture['examination'], $fixture['student']]));

    auth()->logout();

    checkResult($fixture)
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertSee('72');
});

test('a Basic school\'s unpublished result is not shown at all', function () {
    // The marks exist and are complete. The school has not pushed them, so
    // they are not the school's answer yet.
    $fixture = resultCheckFixture(PlanKey::Basic);

    expect(RepositoryResult::count())->toBe(0);

    checkResult($fixture)
        ->assertOk()
        ->assertSee('This result is not ready')
        ->assertSee('Your token is still valid')
        ->assertDontSee('Mathematics');
});

test('a correction made after publishing does not reach the parent until it is pushed again', function () {
    // The reason the repository stores the card instead of pointing at the
    // marks. A parent reads what the school approved.
    $fixture = resultCheckFixture(PlanKey::Basic);

    test()->actingAs($fixture['admin'])
        ->post(route('results.push', [$fixture['examination'], $fixture['student']]));

    auth()->logout();

    ExaminationScore::where('examination_subject_id', $fixture['subject']->id)
        ->update(['test_score' => 40, 'exam_score' => 57, 'score' => 97]);

    // Three facts together, rather than one fragile "does not see 97" - two
    // digits turn up inside a date or an id sooner or later, and a test that
    // fails on that is a test nobody trusts.
    expect(ExaminationScore::where('examination_subject_id', $fixture['subject']->id)->value('score'))
        ->toEqual(97)
        ->and(RepositoryResult::sole()->payload['subjects'][0]['score'])
        ->toEqual(72);

    // And the school is told there is a newer version worth pushing.
    $state = app(ResultRepository::class)->stateFor($fixture['examination']);
    expect($state['staleStudentIds'])->toBe([$fixture['student']->id]);

    checkResult($fixture)
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertSee('72');
});

test('re-pushing the correction is what releases it', function () {
    $fixture = resultCheckFixture(PlanKey::Basic);

    test()->actingAs($fixture['admin'])
        ->post(route('results.push', [$fixture['examination'], $fixture['student']]));

    ExaminationScore::where('examination_subject_id', $fixture['subject']->id)
        ->update(['test_score' => 40, 'exam_score' => 45, 'score' => 85]);

    test()->actingAs($fixture['admin'])
        ->post(route('results.push', [$fixture['examination'], $fixture['student']]));

    auth()->logout();

    checkResult($fixture)->assertOk()->assertSee('85');
});

test('a Standard school\'s result checking is unchanged', function () {
    // The brief leaves Standard and Exclusive alone: their families read
    // results in a portal, and the checking link keeps reading the marks live.
    $fixture = resultCheckFixture(PlanKey::Standard);

    expect(RepositoryResult::count())->toBe(0);

    checkResult($fixture)
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertSee('72');
});
