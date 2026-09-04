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
 * A school on the given plan, with an admin and a teacher.
 *
 * @return array{0: School, 1: User, 2: Staff}
 */
function timetableSchool(PlanKey $planKey): array
{
    $school = School::factory()->create();

    $plan = Plan::firstOrCreate(
        ['key' => $planKey],
        Plan::factory()->make(['key' => $planKey])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    return [
        $school,
        User::factory()->create(['school_id' => $school->id, 'role' => UserRole::SchoolAdmin]),
        Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher]),
    ];
}

test('the timetable is a Standard and Exclusive feature', function () {
    expect(PlanFeature::Timetable->requiredPlans())
        ->toBe([PlanKey::Standard, PlanKey::Exclusive]);
});

test('a Basic school admin cannot reach the timetable', function () {
    [$school, $admin] = timetableSchool(PlanKey::Basic);

    expect($school->canAccessRoute('timetable.index'))->toBeFalse();

    $this->actingAs($admin)
        ->get(route('timetable.index'))
        ->assertForbidden();
});

test('a Basic teacher cannot reach their timetable', function () {
    [$school, , $teacher] = timetableSchool(PlanKey::Basic);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.timetable', $school))
        ->assertForbidden();
});

test('a Basic school is not shown a timetable link it cannot follow', function () {
    // A nav item the backend would refuse is worse than no nav item.
    [$school, $admin, $teacher] = timetableSchool(PlanKey::Basic);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('timetable.index'), false);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertOk()
        ->assertDontSee(route('staff.timetable', $school), false);
});

test('Standard and Exclusive keep the timetable', function (PlanKey $planKey) {
    [$school, $admin, $teacher] = timetableSchool($planKey);

    expect($school->canAccessRoute('timetable.index'))->toBeTrue();

    $this->actingAs($admin)->get(route('timetable.index'))->assertOk();
    $this->actingAs($teacher, 'staff')->get(route('staff.timetable', $school))->assertOk();
})->with([
    [PlanKey::Standard],
    [PlanKey::Exclusive],
]);

test('the role is described to people as the ScholarNest Team', function () {
    expect(UserRole::SuperAdmin->label())->toBe('ScholarNest Team');
});
