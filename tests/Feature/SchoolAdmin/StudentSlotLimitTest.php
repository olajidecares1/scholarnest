<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;

function basicSchoolAdminWithSlots(int $studentsCount): User
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Basic], Plan::factory()->make(['key' => PlanKey::Basic])->toArray());
    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'students_count' => $studentsCount,
    ]);

    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

function studentPayload(): array
{
    return [
        'first_name' => 'Test',
        'last_name' => 'Student',
        'admission_number' => 'ADM-'.Str::random(8),
        'gender' => 'male',
        'class_name' => 'JSS 1',
    ];
}

test('a school can admit students up to its paid slot limit', function () {
    $admin = basicSchoolAdminWithSlots(2);
    Student::factory()->create(['school_id' => $admin->school_id, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('students.store'), studentPayload())
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($admin->school->students()->where('is_active', true)->count())->toBe(2);
});

test('a school cannot admit a student beyond its paid slot limit', function () {
    $admin = basicSchoolAdminWithSlots(2);
    Student::factory()->count(2)->create(['school_id' => $admin->school_id, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('students.store'), studentPayload())
        ->assertSessionHasErrors('admission_number');

    expect($admin->school->students()->count())->toBe(2);
});

test('inactive students do not count against the slot limit', function () {
    $admin = basicSchoolAdminWithSlots(2);
    Student::factory()->count(2)->create(['school_id' => $admin->school_id, 'is_active' => false]);

    $this->actingAs($admin)
        ->post(route('students.store'), studentPayload())
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
});

test('standard and exclusive plans have no student slot limit', function () {
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    Student::factory()->count(50)->create(['school_id' => $school->id, 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('students.store'), studentPayload())
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
});
