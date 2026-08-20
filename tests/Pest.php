<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
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
