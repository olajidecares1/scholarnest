<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;

/**
 * Regression coverage for the "schools got full access before payment
 * confirmation" security fix: routes/web.php now gates the entire School
 * Admin content route group behind the school_activated middleware, which
 * requires School::hasActiveSubscription() - not merely a submitted
 * subscription.
 */
function subscribedSchoolAdmin(?SubscriptionStatus $status, bool $isActive = true): User
{
    $school = School::factory()->create(['is_active' => $isActive]);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    if ($status !== null) {
        // Plan::factory() still burns one of Faker's only 3 unique PlanKey
        // slots even when "key" is overridden afterwards - checking for an
        // existing row first avoids ever touching the factory more than once
        // across a test that creates several schools (e.g. the loop test below).
        $plan = Plan::where('key', PlanKey::Standard)->first()
            ?? Plan::factory()->create(['key' => PlanKey::Standard]);

        Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => $status]);
    }

    return $admin;
}

test('a school admin with no subscription at all cannot reach content routes', function () {
    $admin = subscribedSchoolAdmin(null);

    $this->actingAs($admin)
        ->get(route('students.index'))
        ->assertRedirect(route('dashboard'));
});

test('a school admin with a subscription pending verification cannot reach content routes', function () {
    $admin = subscribedSchoolAdmin(SubscriptionStatus::PendingVerification);

    $this->actingAs($admin)
        ->get(route('students.index'))
        ->assertRedirect(route('dashboard'));
});

test('a school admin with a rejected subscription cannot reach content routes', function () {
    $admin = subscribedSchoolAdmin(SubscriptionStatus::Rejected);

    $this->actingAs($admin)
        ->get(route('students.index'))
        ->assertRedirect(route('dashboard'));
});

test('a school admin with an expired subscription cannot reach content routes', function () {
    $admin = subscribedSchoolAdmin(SubscriptionStatus::Expired);

    $this->actingAs($admin)
        ->get(route('students.index'))
        ->assertRedirect(route('dashboard'));
});

test('a suspended school cannot reach content routes even with an active subscription', function () {
    $admin = subscribedSchoolAdmin(SubscriptionStatus::Active, isActive: false);

    $this->actingAs($admin)
        ->get(route('students.index'))
        ->assertRedirect(route('dashboard'));
});

test('a school admin with a genuinely active subscription can reach content routes', function () {
    $admin = subscribedSchoolAdmin(SubscriptionStatus::Active);

    $this->actingAs($admin)
        ->get(route('students.index'))
        ->assertStatus(200);
});

test('the dashboard and subscription wizard stay reachable while blocked, in every state', function () {
    foreach ([null, SubscriptionStatus::PendingVerification, SubscriptionStatus::Rejected, SubscriptionStatus::Expired] as $status) {
        $admin = subscribedSchoolAdmin($status);

        $this->actingAs($admin)->get(route('dashboard'))->assertStatus(200);
        $this->actingAs($admin)->get(route('subscriptions.choose-plan'))->assertStatus(200);
    }

    $suspendedAdmin = subscribedSchoolAdmin(SubscriptionStatus::Active, isActive: false);
    $this->actingAs($suspendedAdmin)->get(route('dashboard'))->assertStatus(200);
});

test('end-to-end: a freshly registered school cannot access content routes immediately after signing up', function () {
    $response = $this->post(route('register'), [
        'school_name' => 'Brand New Academy',
        'email' => 'admin@brandnew.example',
        'phone' => '+234 801 234 5678',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $this->get(route('students.index'))->assertRedirect(route('dashboard'));
    $this->get(route('staff.index'))->assertRedirect(route('dashboard'));
    $this->get(route('finance.index'))->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertStatus(200)
        ->assertSee('Choose a Plan');
});
