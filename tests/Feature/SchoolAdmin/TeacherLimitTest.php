<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;

function schoolAdminOnPlan(PlanKey $planKey, ?int $maxTeachers = null): User
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(
        ['key' => $planKey],
        Plan::factory()->make(['key' => $planKey, 'max_teachers' => $maxTeachers])->toArray(),
    );

    if ($plan->max_teachers !== $maxTeachers) {
        $plan->update(['max_teachers' => $maxTeachers]);
    }

    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

function staffPayload(string $role): array
{
    return [
        'first_name' => 'Test',
        'last_name' => 'Member',
        'staff_number' => 'ST-'.Str::random(8),
        'email' => Str::random(8).'@example.com',
        'phone' => '08012345678',
        'role' => $role,
        'gender' => 'male',
    ];
}

test('the 5th active teacher can be added on the basic plan', function () {
    $admin = schoolAdminOnPlan(PlanKey::Basic, 5);
    Staff::factory()->count(4)->create(['school_id' => $admin->school_id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('staff.store'), staffPayload(StaffRole::Teacher->value))
        ->assertRedirect();

    expect($admin->school->staff()->where('role', StaffRole::Teacher)->count())->toBe(5);
});

test('the 6th active teacher cannot be added on the basic plan', function () {
    $admin = schoolAdminOnPlan(PlanKey::Basic, 5);
    Staff::factory()->count(5)->create(['school_id' => $admin->school_id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('staff.store'), staffPayload(StaffRole::Teacher->value))
        ->assertSessionHasErrors('role');

    expect($admin->school->staff()->where('role', StaffRole::Teacher)->count())->toBe(5);
});

test('non-teacher roles are unaffected by the teacher limit', function () {
    $admin = schoolAdminOnPlan(PlanKey::Basic, 5);
    Staff::factory()->count(5)->create(['school_id' => $admin->school_id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('staff.store'), staffPayload(StaffRole::SupportStaff->value))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
});

test('inactive teachers do not count against the teacher limit', function () {
    $admin = schoolAdminOnPlan(PlanKey::Basic, 5);
    Staff::factory()->count(5)->create(['school_id' => $admin->school_id, 'role' => StaffRole::Teacher, 'is_active' => false]);

    $this->actingAs($admin)
        ->post(route('staff.store'), staffPayload(StaffRole::Teacher->value))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
});

test('standard and exclusive plans have no teacher limit', function () {
    $admin = schoolAdminOnPlan(PlanKey::Standard, null);
    Staff::factory()->count(25)->create(['school_id' => $admin->school_id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('staff.store'), staffPayload(StaffRole::Teacher->value))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
});
