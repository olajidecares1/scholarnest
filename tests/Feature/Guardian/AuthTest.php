<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Guardian;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;

function guardianPortalSchool(PlanKey $planKey = PlanKey::Standard, SubscriptionStatus $status = SubscriptionStatus::Active): School
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => $planKey], Plan::factory()->make(['key' => $planKey])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => $status]);

    return $school;
}

test('a guardian can log in with their email and password', function () {
    $school = guardianPortalSchool();
    $guardian = Guardian::factory()->create(['school_id' => $school->id, 'email' => 'parent@example.com']);

    $this->post(route('guardian.login', ['school' => $school, 'token' => $school->portal_guardian_token]), [
        'email' => 'parent@example.com',
        'password' => 'password',
    ])->assertRedirect(route('guardian.dashboard', $school));

    $this->assertAuthenticatedAs($guardian, 'guardian');
});

test('a wrong password is rejected', function () {
    $school = guardianPortalSchool();
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);

    $this->post(route('guardian.login', ['school' => $school, 'token' => $school->portal_guardian_token]), [
        'email' => $guardian->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('guardian');
});

test('two schools can each have a guardian with the same email without collision', function () {
    $schoolA = guardianPortalSchool();
    $schoolB = guardianPortalSchool();

    $guardianA = Guardian::factory()->create(['school_id' => $schoolA->id, 'email' => 'shared@example.com']);
    Guardian::factory()->create(['school_id' => $schoolB->id, 'email' => 'shared@example.com']);

    $this->post(route('guardian.login', ['school' => $schoolA, 'token' => $schoolA->portal_guardian_token]), [
        'email' => 'shared@example.com',
        'password' => 'password',
    ])->assertRedirect(route('guardian.dashboard', $schoolA));

    $this->assertAuthenticatedAs($guardianA, 'guardian');
});

test('failed attempts against a guardian at one school do not lock out an identically-emailed guardian at another school', function () {
    $schoolA = guardianPortalSchool();
    $schoolB = guardianPortalSchool();

    Guardian::factory()->create(['school_id' => $schoolA->id, 'email' => 'shared@example.com']);
    $guardianB = Guardian::factory()->create(['school_id' => $schoolB->id, 'email' => 'shared@example.com']);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('guardian.login', ['school' => $schoolA, 'token' => $schoolA->portal_guardian_token]), [
            'email' => 'shared@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $this->post(route('guardian.login', ['school' => $schoolA, 'token' => $schoolA->portal_guardian_token]), [
        'email' => 'shared@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->post(route('guardian.login', ['school' => $schoolB, 'token' => $schoolB->portal_guardian_token]), [
        'email' => 'shared@example.com',
        'password' => 'password',
    ])->assertRedirect(route('guardian.dashboard', $schoolB));

    $this->assertAuthenticatedAs($guardianB, 'guardian');
});

test('an inactive guardian cannot access the portal', function () {
    $school = guardianPortalSchool();
    $guardian = Guardian::factory()->create(['school_id' => $school->id, 'is_active' => false]);

    $this->post(route('guardian.login', ['school' => $school, 'token' => $school->portal_guardian_token]), [
        'email' => $guardian->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('guardian');
});

test('a guardian from a school without a qualifying plan is redirected to the locked page', function () {
    $school = guardianPortalSchool(PlanKey::Basic);
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard', $school))
        ->assertRedirect(route('guardian.locked', $school));
});

test('a guardian from a school with no active subscription is redirected to the locked page', function () {
    $school = School::factory()->create();
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard', $school))
        ->assertRedirect(route('guardian.locked', $school));
});

test('a guardian from a school on the exclusive plan can access the portal', function () {
    $school = guardianPortalSchool(PlanKey::Exclusive);
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);
    $guardian->students()->attach(Student::factory()->create(['school_id' => $school->id])->id);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard', $school))
        ->assertStatus(200);
});

test('a guardian can log out', function () {
    $school = guardianPortalSchool();
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);

    $this->actingAs($guardian, 'guardian')
        ->post(route('guardian.logout', $school))
        ->assertRedirect(route('guardian.login', ['school' => $school, 'token' => $school->portal_guardian_token]));

    $this->assertGuest('guardian');
});
