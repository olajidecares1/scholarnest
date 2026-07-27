<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;

function makeSuperAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
}

function makeSchoolAdmin(): User
{
    $school = School::factory()->create();

    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

test('guests are redirected to login', function () {
    $this->get(route('super-admin.dashboard'))->assertRedirect(route('login'));
});

test('a school admin cannot access the super admin dashboard', function () {
    $this->actingAs(makeSchoolAdmin())
        ->get(route('super-admin.dashboard'))
        ->assertForbidden();
});

test('a super admin can access the dashboard', function () {
    $this->actingAs(makeSuperAdmin())
        ->get(route('super-admin.dashboard'))
        ->assertStatus(200)
        ->assertSee('Total Schools');
});

test('a super admin cannot access the school admin subscription wizard', function () {
    $this->actingAs(makeSuperAdmin())
        ->get(route('subscriptions.choose-plan'))
        ->assertForbidden();
});

test('every super admin section is reachable', function () {
    $admin = makeSuperAdmin();

    $routes = [
        'super-admin.dashboard',
        'super-admin.schools.index',
        'super-admin.subscriptions.index',
        'super-admin.payments.index',
        'super-admin.users.index',
        'super-admin.roles.index',
        'super-admin.reports.index',
        'super-admin.analytics.index',
        'super-admin.communications.index',
        'super-admin.support-tickets.index',
        'super-admin.cms.index',
        'super-admin.themes.index',
        'super-admin.settings.index',
        'super-admin.audit-logs.index',
    ];

    foreach ($routes as $route) {
        $this->actingAs($admin)->get(route($route))->assertStatus(200);
    }
});
