<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ResultTokenIssuer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

/*
 * A test starts with an empty cache, the same way it starts with an empty
 * database.
 *
 * RefreshDatabase rolls the database back between tests but nothing rolled the
 * CACHE back, and CACHE_STORE is "array" - a PHP array living in the process,
 * which every test in that process shares. Login throttling counts attempts
 * there, so a file with several "wrong password" tests left five failures on
 * the counter and the NEXT test to post those credentials got a 429 for a
 * password that was correct.
 *
 * That is why the credential tests failed intermittently and passed when run
 * alone: nothing was wrong with them except which tests had run first.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Cache::flush())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Gives a school an Active subscription so it passes the school_activated
 * gate - most School Admin feature tests only care about the feature under
 * test, not the activation workflow itself, so they need this as a fixture
 * rather than re-deriving it per test.
 */
function activateSchool(School $school, PlanKey $plan = PlanKey::Standard): School
{
    // Plan::factory() burns one of Faker's only 3 unique PlanKey slots even
    // when "key" is overridden afterwards - checking for an existing row
    // first avoids exhausting that pool in a test that activates several
    // schools.
    $planModel = Plan::where('key', $plan)->first()
        ?? Plan::factory()->create(['key' => $plan]);

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $planModel->id,
        'status' => SubscriptionStatus::Active,
    ]);

    return $school;
}

/**
 * Enter the exam token for one portal result.
 *
 * A Standard or Exclusive portal keeps a result shut until its token has been
 * typed in, so any test about what a signed-in student or guardian may DO with
 * a result has to open it first. That the gate exists at all is asserted in
 * PortalResultTokenGateTest; everywhere else it is a fixture.
 *
 * @param  array<int, mixed>  $routeParams
 */
function enterExamToken(
    Authenticatable $actor,
    string $guard,
    School $school,
    Student $student,
    Examination $examination,
    string $unlockRoute,
    array $routeParams,
): void {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $token = app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin)['plain'];

    test()->actingAs($actor, $guard)
        ->post(route($unlockRoute, $routeParams), ['token' => $token])
        ->assertSessionHasNoErrors();
}

/**
 * Step one of Basic-plan result checking: name the pupil.
 *
 * Checking a result is two steps now - a School ID / Admission Number, then the
 * token bound to whoever that found. The token post on its own goes nowhere, by
 * design, so every test that redeems a token does this first.
 *
 * The session carries the identification between the two, which is why this
 * needs no return value: the verify post that follows picks it up.
 */
function identifyForResultCheck(School $school, Student $student, bool $viaSchoolLink = false): void
{
    test()->post(
        $viaSchoolLink
            ? route('school-result.identify', ['school' => $school->result_link_slug])
            : route('check-result.identify', $school),
        ['admission_number' => $student->admission_number],
    );
}
