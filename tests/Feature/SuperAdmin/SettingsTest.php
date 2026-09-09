<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('super admin can view the settings page', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.settings.index'))
        ->assertStatus(200)
        ->assertSee('System Settings');
});

test('super admin can update general settings', function () {
    $response = $this->actingAs($this->superAdmin)->put(route('super-admin.settings.update'), [
        'site_name' => 'AkademicNest Platform',
        'support_email' => 'help@akademicnest.test',
        'support_phone' => '+2348012345678',
        'notification_from_name' => 'AkademicNest Notifications',
        'notification_from_email' => 'notify@akademicnest.test',
        'maintenance_mode' => '0',
        'maintenance_message' => null,
    ]);

    $response->assertRedirect();

    $settings = Setting::current()->fresh();
    expect($settings->site_name)->toBe('AkademicNest Platform');
    expect($settings->support_email)->toBe('help@akademicnest.test');
    expect($settings->maintenance_mode)->toBeFalse();
});

test('enabling maintenance mode blocks school admins but not super admins', function () {
    Setting::current()->update(['maintenance_mode' => true, 'maintenance_message' => 'Back soon.']);

    $school = School::factory()->create();
    $schoolAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($schoolAdmin)
        ->get(route('dashboard'))
        ->assertStatus(503)
        ->assertSee('Back soon.');

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.dashboard'))
        ->assertStatus(200);
});

test('maintenance mode does not block the way back in', function () {
    Setting::current()->update(['maintenance_mode' => true]);

    // The registration page carries the hidden Super Admin sign-in, so it has
    // to stay reachable - otherwise turning maintenance on locks the Super
    // Admin out of the switch that turns it off again.
    $this->get(route('register'))->assertStatus(200);

    // And "login" still redirects there rather than into the maintenance screen.
    $this->get(route('login'))->assertRedirect(route('register'));
});
