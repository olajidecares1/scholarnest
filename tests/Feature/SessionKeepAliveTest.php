<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\PageView;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;

/**
 * Using a page counts as activity for the portal idle timeout.
 *
 * A teacher choosing a CBT document on a phone makes no request for minutes,
 * was signed out by the three-minute idle limit, and the upload that followed
 * came back "error 401". The page now pings this endpoint when the person
 * actually interacts, and checks it right before an upload.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    $this->teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
});

test('an active session is kept alive, and the idle clock restarts', function () {
    $this->actingAs($this->teacher, 'staff')
        ->withSession(['idle_last_activity_staff' => now()->subMinutes(2)])
        ->getJson(route('session.keep-alive'))
        ->assertNoContent();

    expect(session('idle_last_activity_staff')->diffInSeconds(now(), absolute: true))->toBeLessThan(5);
});

test('a session already past the idle limit answers 401 with where to sign in', function () {
    $this->actingAs($this->teacher, 'staff')
        ->withSession(['idle_last_activity_staff' => now()->subMinutes(4)])
        ->getJson(route('session.keep-alive'))
        ->assertUnauthorized()
        ->assertJsonPath('redirect', $this->school->portalLoginUrl('staff'));

    $this->assertGuest('staff');
});

test('every portal guard is covered', function () {
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->actingAs($admin)->getJson(route('session.keep-alive'))->assertNoContent();
});

test('somebody not signed in gets 401, not a page', function () {
    $this->getJson(route('session.keep-alive'))->assertUnauthorized();
});

test('it is not counted as a page view', function () {
    $this->actingAs($this->teacher, 'staff')->get(route('session.keep-alive'))->assertNoContent();

    expect(PageView::count())->toBe(0);
});

test('the signed-in portal layouts carry the endpoint, so interaction keeps them alive', function () {
    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.dashboard', $this->school))
        ->assertOk()
        ->assertSee('name="session-keep-alive" content="/session/keep-alive"', false);
});
