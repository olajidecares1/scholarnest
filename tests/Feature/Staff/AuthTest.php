<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Subscription;

function staffPortalSchool(PlanKey $planKey = PlanKey::Standard, SubscriptionStatus $status = SubscriptionStatus::Active): School
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => $planKey], Plan::factory()->make(['key' => $planKey])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => $status]);

    return $school;
}

test('a staff member can log in with their staff number and password', function () {
    $school = staffPortalSchool();
    $staff = Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'STF-1001']);

    $this->post(route('staff.login', ['school' => $school, 'token' => $school->portal_staff_token]), [
        'login' => 'STF-1001',
        'password' => 'password',
    ])->assertRedirect(route('staff.dashboard', $school));

    $this->assertAuthenticatedAs($staff, 'staff');
});

test('a staff member can log in with their email and password', function () {
    $school = staffPortalSchool();
    $staff = Staff::factory()->create(['school_id' => $school->id, 'email' => 'teacher@example.com']);

    $this->post(route('staff.login', ['school' => $school, 'token' => $school->portal_staff_token]), [
        'login' => 'teacher@example.com',
        'password' => 'password',
    ])->assertRedirect(route('staff.dashboard', $school));

    $this->assertAuthenticatedAs($staff, 'staff');
});

test('a wrong password is rejected', function () {
    $school = staffPortalSchool();
    $staff = Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'STF-1002']);

    $this->post(route('staff.login', ['school' => $school, 'token' => $school->portal_staff_token]), [
        'login' => 'STF-1002',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest('staff');
});

test('two schools can each have a staff member with the same staff number without collision', function () {
    $schoolA = staffPortalSchool();
    $schoolB = staffPortalSchool();

    $staffA = Staff::factory()->create(['school_id' => $schoolA->id, 'staff_number' => 'SHARED-1']);
    Staff::factory()->create(['school_id' => $schoolB->id, 'staff_number' => 'SHARED-1']);

    $this->post(route('staff.login', ['school' => $schoolA, 'token' => $schoolA->portal_staff_token]), [
        'login' => 'SHARED-1',
        'password' => 'password',
    ])->assertRedirect(route('staff.dashboard', $schoolA));

    $this->assertAuthenticatedAs($staffA, 'staff');
});

test('failed attempts against a staff member at one school do not lock out an identically-numbered staff member at another school', function () {
    $schoolA = staffPortalSchool();
    $schoolB = staffPortalSchool();

    Staff::factory()->create(['school_id' => $schoolA->id, 'staff_number' => 'SHARED-1']);
    $staffB = Staff::factory()->create(['school_id' => $schoolB->id, 'staff_number' => 'SHARED-1']);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('staff.login', ['school' => $schoolA, 'token' => $schoolA->portal_staff_token]), [
            'login' => 'SHARED-1',
            'password' => 'wrong-password',
        ]);
    }

    $this->post(route('staff.login', ['school' => $schoolA, 'token' => $schoolA->portal_staff_token]), [
        'login' => 'SHARED-1',
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->post(route('staff.login', ['school' => $schoolB, 'token' => $schoolB->portal_staff_token]), [
        'login' => 'SHARED-1',
        'password' => 'password',
    ])->assertRedirect(route('staff.dashboard', $schoolB));

    $this->assertAuthenticatedAs($staffB, 'staff');
});

test('an inactive staff member cannot access the portal', function () {
    $school = staffPortalSchool();
    $staff = Staff::factory()->create(['school_id' => $school->id, 'staff_number' => 'STF-1003', 'is_active' => false]);

    $this->post(route('staff.login', ['school' => $school, 'token' => $school->portal_staff_token]), [
        'login' => 'STF-1003',
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest('staff');
});

test('a staff member from a school without a qualifying plan is redirected to the locked page', function () {
    $school = staffPortalSchool(PlanKey::Basic);
    $staff = Staff::factory()->create(['school_id' => $school->id]);

    $this->actingAs($staff, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertRedirect(route('staff.locked', $school));
});

test('a staff member from a school with no active subscription is redirected to the locked page', function () {
    $school = School::factory()->create();
    $staff = Staff::factory()->create(['school_id' => $school->id]);

    $this->actingAs($staff, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertRedirect(route('staff.locked', $school));
});

test('a staff member from a school on the exclusive plan can access the portal', function () {
    $school = staffPortalSchool(PlanKey::Exclusive);
    $staff = Staff::factory()->create(['school_id' => $school->id]);

    $this->actingAs($staff, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertStatus(200);
});

test('a staff member can log out', function () {
    $school = staffPortalSchool();
    $staff = Staff::factory()->create(['school_id' => $school->id]);

    $this->actingAs($staff, 'staff')
        ->post(route('staff.logout', $school))
        ->assertRedirect(route('staff.login', ['school' => $school, 'token' => $school->portal_staff_token]));

    $this->assertGuest('staff');
});
