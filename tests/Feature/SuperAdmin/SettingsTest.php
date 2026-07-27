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
        'site_name' => 'EduNest Platform',
        'support_email' => 'help@edunest.test',
        'support_phone' => '+2348012345678',
        'notification_from_name' => 'EduNest Notifications',
        'notification_from_email' => 'notify@edunest.test',
        'maintenance_mode' => '0',
        'maintenance_message' => null,
    ]);

    $response->assertRedirect();

    $settings = Setting::current()->fresh();
    expect($settings->site_name)->toBe('EduNest Platform');
    expect($settings->support_email)->toBe('help@edunest.test');
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

test('maintenance mode does not block the login page', function () {
    Setting::current()->update(['maintenance_mode' => true]);

    $this->get(route('login'))->assertStatus(200);
});
