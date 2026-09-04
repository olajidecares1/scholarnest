<?php

use App\Enums\PlanFeature;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;

/**
 * What a school on the wrong plan meets, and what it is not left with.
 *
 * The refusal itself was already right - a 403 - but it arrived as a bare
 * sentence on an empty page with nothing on it to click. These assert the two
 * halves that matter: the backend still refuses, and the person refused is
 * never stranded.
 */
function schoolOnPlan(PlanKey $planKey): School
{
    $school = School::factory()->create();
    // Named as the seeder names them, because the restriction page tells the
    // school which plan it is currently on.
    $plan = Plan::firstOrCreate(
        ['key' => $planKey],
        Plan::factory()->make(['key' => $planKey, 'name' => $planKey->label()])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    return $school->fresh();
}

function adminOnPlan(PlanKey $planKey): User
{
    return User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => schoolOnPlan($planKey)->id,
    ]);
}

// -----------------------------------------------------------------------------
// The features Basic does not buy are actually closed
// -----------------------------------------------------------------------------

test('a basic school admin is refused every standard-only feature', function (string $route) {
    $admin = adminOnPlan(PlanKey::Basic);

    // Server-side, so typing the URL is no way around it. Every one of these
    // was reachable before: a Basic school could open the Events manager for
    // a website it does not have, and create guardian accounts nobody could
    // ever sign in with.
    $this->actingAs($admin)->get(route($route))->assertForbidden();
})->with([
    'CBT practice' => 'cbt-practice.index',
    'CBT tests' => 'cbt-tests.index',
    'guardians' => 'guardians.index',
    'events' => 'events.index',
    'news' => 'news.index',
    'careers' => 'careers.index',
    'testimonials' => 'testimonials.index',
    'co-curricular' => 'co-curricular.index',
    'website' => 'website.index',
    'ID cards' => 'id-cards.index',
    'assignments' => 'assignments.index',
    'library' => 'library.index',
    'transport' => 'transport.index',
    'hostel' => 'hostels.index',
    'finance' => 'finance.index',
]);

test('a standard school admin reaches every one of them', function (string $route) {
    $admin = adminOnPlan(PlanKey::Standard);

    // The gate has to tell plans apart, not simply refuse everyone.
    $this->actingAs($admin)->get(route($route))->assertOk();
})->with([
    'CBT practice' => 'cbt-practice.index',
    'CBT tests' => 'cbt-tests.index',
    'guardians' => 'guardians.index',
    'events' => 'events.index',
    'news' => 'news.index',
    'careers' => 'careers.index',
    'testimonials' => 'testimonials.index',
    'facilities' => 'facilities.index',
    'co-curricular' => 'co-curricular.index',
    'website' => 'website.index',
    'ID cards' => 'id-cards.index',
    'assignments' => 'assignments.index',
    'library' => 'library.index',
    'transport' => 'transport.index',
    'hostel' => 'hostels.index',
    'finance' => 'finance.index',
]);

test('writing to a restricted feature is refused as well as reading it', function () {
    $admin = adminOnPlan(PlanKey::Basic);

    // A gate on the index page only would leave the store route open to
    // anything that could guess it.
    $this->actingAs($admin)->post(route('events.index'), [
        'title' => 'Speech Day',
        'starts_at' => now()->addWeek()->toDateTimeString(),
    ])->assertForbidden();

    $this->actingAs($admin)->post(route('guardians.index'), [
        'name' => 'A Parent',
        'email' => 'parent@example.test',
    ])->assertForbidden();
});

test('the custom domain stays on the exclusive plan alone', function () {
    $this->actingAs(adminOnPlan(PlanKey::Standard))
        ->get(route('custom-domain.index'))
        ->assertForbidden();

    $this->actingAs(adminOnPlan(PlanKey::Exclusive))
        ->get(route('custom-domain.index'))
        ->assertOk();
});

// -----------------------------------------------------------------------------
// And the refusal is a page, not a dead end
// -----------------------------------------------------------------------------

test('the restriction page names the feature and the plan that includes it', function () {
    $admin = adminOnPlan(PlanKey::Basic);

    $response = $this->actingAs($admin)->get(route('cbt-tests.index'))->assertForbidden();

    $response->assertSee('CBT Tests')
        ->assertSee('is not part of your plan')
        ->assertSee('Standard Plan')
        ->assertSee('Basic Plan');

    // The thing this replaced.
    $response->assertDontSee('CBT requires the Standard or Exclusive plan.');
});

test('the restriction page always offers the way back to the dashboard', function (string $route) {
    $admin = adminOnPlan(PlanKey::Basic);

    // The rule that matters most: whatever a school is refused, it is never
    // left on a page with nothing to click.
    $this->actingAs($admin)
        ->get(route($route))
        ->assertForbidden()
        ->assertSee('Return to Dashboard')
        ->assertSee(route('dashboard'));
})->with([
    'events' => 'events.index',
    'news' => 'news.index',
    'website' => 'website.index',
]);

test('a school admin is offered the upgrade, and it goes to the plans', function () {
    $admin = adminOnPlan(PlanKey::Basic);

    $this->actingAs($admin)
        ->get(route('news.index'))
        ->assertForbidden()
        ->assertSee('View Plans')
        ->assertSee(route('subscriptions.choose-plan'));
});

test('the restriction page explains what the feature would have done', function () {
    $admin = adminOnPlan(PlanKey::Basic);

    // A refusal that names nothing but the plan tells a paying customer
    // nothing about what they are being turned away from.
    $this->actingAs($admin)
        ->get(route('guardians.index'))
        ->assertForbidden()
        ->assertSee('Parent &amp; Guardian Accounts', false)
        ->assertSee('follow attendance, results and fees');
});

test('an api caller gets the refusal as data, not as a page', function () {
    $admin = adminOnPlan(PlanKey::Basic);

    $this->actingAs($admin)
        ->getJson(route('cbt-tests.index'))
        ->assertForbidden()
        ->assertJsonPath('feature', 'cbt')
        ->assertJsonPath('required_plans', ['standard', 'exclusive']);
});

// -----------------------------------------------------------------------------
// Teachers are held to their school's plan too
// -----------------------------------------------------------------------------

test('a teacher at a basic school is refused CBT, and sent to their own dashboard', function () {
    $school = schoolOnPlan(PlanKey::Basic);
    $teacher = Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
    ]);

    $response = $this->actingAs($teacher, 'staff')
        ->get(route('staff.cbt.tests.index', $school))
        ->assertForbidden();

    // The way back is their portal, not the School Admin dashboard they have
    // no account for - and they are told who can change the plan rather than
    // handed a buy button they cannot act on.
    $response->assertSee('Return to Dashboard')
        ->assertSee(route('staff.dashboard', $school))
        ->assertSee('Only your School Administrator')
        ->assertDontSee('View Plans');
});

// -----------------------------------------------------------------------------
// And the interface never offers what the gate will refuse
// -----------------------------------------------------------------------------

test('the basic dashboard does not link to a single restricted feature', function () {
    $admin = adminOnPlan(PlanKey::Basic);

    $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    // A link that 403s is worse than no link, however good the page behind the
    // 403 looks.
    foreach (['cbt-practice.index', 'cbt-tests.index', 'events.index', 'news.index',
        'careers.index', 'testimonials.index', 'website.index',
        'id-cards.index', 'guardians.index', 'co-curricular.index',
        'assignments.index', 'library.index', 'transport.index', 'hostels.index',
        'finance.index'] as $route) {
        $response->assertDontSee(route($route));
    }
});

test('but facilities is on every plan, so a Basic school reaches it', function () {
    // The exception to everything above. A school's buildings are a plain fact
    // about the school rather than a premium extra, so Facilities is open to
    // Basic while News, Events and the website itself stay behind the gate.
    $admin = adminOnPlan(PlanKey::Basic);

    $this->actingAs($admin)->get(route('facilities.index'))->assertOk();
});

test('and the basic dashboard offers it', function () {
    // The interface asks canAccessRoute before it draws a link and the
    // middleware asks the same question before serving the page, so the two
    // cannot disagree - but that cuts both ways, and a feature opened up in
    // one place has to appear in the other.
    $admin = adminOnPlan(PlanKey::Basic);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('facilities.index'));
});

test('the standard dashboard does link to them', function () {
    $admin = adminOnPlan(PlanKey::Standard);

    $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    foreach (['cbt-tests.index', 'events.index', 'news.index', 'website.index', 'guardians.index',
        'assignments.index', 'library.index', 'transport.index', 'hostels.index', 'finance.index'] as $route) {
        $response->assertSee(route($route));
    }
});

test('every restricted feature knows its own name, blurb and icon', function (PlanFeature $feature) {
    // The page is generated from these, so a case added without them would
    // render a restriction screen with blanks on it.
    expect($feature->label())->not->toBe('')
        ->and($feature->blurb())->not->toBe('')
        ->and($feature->icon())->toStartWith('fa-')
        ->and($feature->requiredPlans())->not->toBeEmpty()
        ->and($feature->requiredPlanLabel())->toStartWith('the ');
})->with(PlanFeature::cases());

test('teacher assignments are not caught by the assignments gate', function () {
    $admin = adminOnPlan(PlanKey::Basic);

    // "assignments" is homework, and is now a Standard feature. Assigning a
    // TEACHER to a class is a different thing with a similar route name, and
    // it is part of running a school on any plan.
    $this->actingAs($admin)->get(route('teacher-assignments.index'))->assertOk();
    $this->actingAs($admin)->get(route('assignments.index'))->assertForbidden();
});
