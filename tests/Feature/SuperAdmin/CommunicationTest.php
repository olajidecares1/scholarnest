<?php

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\School;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('super admin can view the communications page', function () {
    Announcement::factory()->create(['title' => 'Scheduled Maintenance']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.communications.index'))
        ->assertStatus(200)
        ->assertSee('Scheduled Maintenance');
});

test('sending an announcement notifies every school admin', function () {
    Notification::fake();

    $school = School::factory()->create();
    $schoolAdminOne = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $schoolAdminTwo = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.communications.store'), [
        'title' => 'Scheduled Maintenance',
        'body' => 'EduNest will be briefly unavailable this weekend.',
    ]);

    $response->assertRedirect(route('super-admin.communications.index'));

    $announcement = Announcement::where('title', 'Scheduled Maintenance')->firstOrFail();
    expect($announcement->recipients_count)->toBe(2);

    Notification::assertSentTo($schoolAdminOne, AnnouncementNotification::class);
    Notification::assertSentTo($schoolAdminTwo, AnnouncementNotification::class);
});

test('an announcement is not sent to super admins', function () {
    Notification::fake();

    $this->actingAs($this->superAdmin)->post(route('super-admin.communications.store'), [
        'title' => 'Internal Update',
        'body' => 'Just for schools.',
    ]);

    Notification::assertNothingSentTo($this->superAdmin);
});
