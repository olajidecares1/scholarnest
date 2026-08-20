<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;

function planSchoolAdmin(PlanKey $planKey): User
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => $planKey], Plan::factory()->make(['key' => $planKey])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

test('a school admin on the basic plan cannot access CBT practice oversight', function () {
    $admin = planSchoolAdmin(PlanKey::Basic);

    $this->actingAs($admin)
        ->get(route('cbt-practice.index'))
        ->assertForbidden();
});

test('a school admin on the basic plan cannot access CBT test oversight', function () {
    $admin = planSchoolAdmin(PlanKey::Basic);

    $this->actingAs($admin)
        ->get(route('cbt-tests.index'))
        ->assertForbidden();
});

test('a school admin on the standard plan can access CBT oversight', function () {
    $admin = planSchoolAdmin(PlanKey::Standard);

    $this->actingAs($admin)
        ->get(route('cbt-practice.index'))
        ->assertStatus(200);
});

test('a school admin on the exclusive plan can access CBT oversight', function () {
    $admin = planSchoolAdmin(PlanKey::Exclusive);

    $this->actingAs($admin)
        ->get(route('cbt-tests.index'))
        ->assertStatus(200);
});

test('a school admin on the basic plan cannot access the website builder', function () {
    $admin = planSchoolAdmin(PlanKey::Basic);

    $this->actingAs($admin)
        ->get(route('website.index'))
        ->assertForbidden();
});

test('a school admin on the standard plan can access the website builder', function () {
    $admin = planSchoolAdmin(PlanKey::Standard);

    $this->actingAs($admin)
        ->get(route('website.index'))
        ->assertStatus(200);
});

test('the public website 404s for a school on the basic plan', function () {
    $admin = planSchoolAdmin(PlanKey::Basic);
    $school = $admin->school;
    $school->website()->create(['hero_title' => 'Welcome', 'is_published' => true]);

    $this->get(route('public.school-website', $school))->assertNotFound();
});

test('the public website 404s after a school downgrades from standard to basic', function () {
    $admin = planSchoolAdmin(PlanKey::Standard);
    $school = $admin->school;
    $school->website()->create(['hero_title' => 'Welcome', 'is_published' => true]);

    $this->get(route('public.school-website', $school))->assertOk();

    $school->activeSubscription->update([
        'plan_id' => Plan::firstOrCreate(['key' => PlanKey::Basic], Plan::factory()->make(['key' => PlanKey::Basic])->toArray())->id,
    ]);

    $this->get(route('public.school-website', $school))->assertNotFound();
});

test('a school admin on the basic plan still has access to attendance, finance, and library', function () {
    $admin = planSchoolAdmin(PlanKey::Basic);

    $this->actingAs($admin)->get(route('attendance.index'))->assertStatus(200);
    $this->actingAs($admin)->get(route('finance.index'))->assertStatus(200);
    $this->actingAs($admin)->get(route('library.index'))->assertStatus(200);
});
