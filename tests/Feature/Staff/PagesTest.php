<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Notifications\PortalProfileUpdatedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->staff = Staff::factory()->create(['school_id' => $this->school->id]);
});

test('a staff member sees their dashboard with a module grid and today\'s classes', function () {
    TimetableEntry::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->staff->id,
        'day_of_week' => now()->dayOfWeekIso,
        'subject' => 'Mathematics',
    ]);

    $this->actingAs($this->staff, 'staff')
        ->get(route('staff.dashboard', $this->school))
        ->assertStatus(200)
        ->assertSee($this->staff->first_name)
        ->assertSee('Mathematics');
});

test('a staff member can view their own profile', function () {
    $this->actingAs($this->staff, 'staff')
        ->get(route('staff.profile', $this->school))
        ->assertStatus(200)
        ->assertSee($this->staff->fullName());
});

test('a staff member sees only their own timetable entries', function () {
    TimetableEntry::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->staff->id,
        'day_of_week' => 1,
        'subject' => 'My Class',
    ]);

    $otherStaff = Staff::factory()->create(['school_id' => $this->school->id]);
    TimetableEntry::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $otherStaff->id,
        'day_of_week' => 1,
        'subject' => 'Not My Class',
    ]);

    $this->actingAs($this->staff, 'staff')
        ->get(route('staff.timetable', $this->school))
        ->assertStatus(200)
        ->assertSee('My Class')
        ->assertDontSee('Not My Class');
});

test('a staff member can view the settings page and update their contact details', function () {
    $this->actingAs($this->staff, 'staff')
        ->get(route('staff.settings.index', $this->school))
        ->assertStatus(200);

    $this->actingAs($this->staff, 'staff')
        ->put(route('staff.settings.update-profile', $this->school), [
            'phone' => '08012345678',
        ])
        ->assertRedirect();

    expect($this->staff->fresh()->phone)->toBe('08012345678');
});

test('a staff member cannot self-upload a profile photo', function () {
    $originalPhotoPath = $this->staff->photo_path;

    $this->actingAs($this->staff, 'staff')
        ->put(route('staff.settings.update-profile', $this->school), [
            'phone' => '08012345678',
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ])
        ->assertRedirect();

    expect($this->staff->fresh()->photo_path)->toBe($originalPhotoPath);
});

test('a staff member can view their own id card read-only but has no print or download route', function () {
    $this->actingAs($this->staff, 'staff')
        ->get(route('staff.id-card.show', $this->school))
        ->assertStatus(200);

    $this->actingAs($this->staff, 'staff')
        ->getJson(route('staff.id-card.preview', $this->school))
        ->assertStatus(200)
        ->assertJsonStructure(['has_template', 'card_number', 'front', 'back']);

    expect(Route::has('staff.id-card.print'))->toBeFalse();
    expect(Route::has('staff.id-card.pdf'))->toBeFalse();
});

test('a staff member can request a change to a protected field but it is not applied until approved', function () {
    $this->actingAs($this->staff, 'staff')
        ->post(route('staff.profile-change-requests.store', $this->school), [
            'field_key' => 'staff_number',
            'requested_value' => 'STF-9999',
        ])
        ->assertRedirect();

    expect($this->staff->fresh()->staff_number)->not->toBe('STF-9999');
    $this->assertDatabaseHas('profile_change_requests', [
        'requester_type' => 'staff',
        'requester_uuid' => $this->staff->uuid,
        'field_key' => 'staff_number',
        'requested_value' => 'STF-9999',
        'status' => 'pending',
    ]);
});

test('a staff member cannot request a change to a field outside the protected registry', function () {
    $this->actingAs($this->staff, 'staff')
        ->post(route('staff.profile-change-requests.store', $this->school), [
            'field_key' => 'photo_path',
            'requested_value' => 'staff/hacked.jpg',
        ])
        ->assertSessionHasErrors('field_key');
});

test('a staff member cannot change their own password', function () {
    expect(Route::has('staff.settings.update-password'))->toBeFalse();

    $this->actingAs($this->staff, 'staff')
        ->get(route('staff.settings.index', $this->school))
        ->assertStatus(200)
        ->assertSee('set by your school office')
        ->assertDontSee('Change Password');
});

test('a staff member keeps their own contact details current, and the school hears about it', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->actingAs($this->staff, 'staff')
        ->put(route('staff.settings.update-profile', $this->school), [
            'phone' => '08099998888',
            'email' => 'new@example.test',
            'address' => '4 New Road',
        ])
        ->assertRedirect();

    $this->staff->refresh();

    expect($this->staff->phone)->toBe('08099998888')
        ->and($this->staff->email)->toBe('new@example.test')
        ->and($this->staff->address)->toBe('4 New Road');

    // The record is the school's, so a change made in a portal cannot happen
    // silently - a number that moves without the office knowing is a call that
    // bounces with no explanation.
    Notification::assertSentTo($admin, PortalProfileUpdatedNotification::class);
});

test('pressing update without changing anything does not pester the school', function () {
    Notification::fake();

    User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->actingAs($this->staff, 'staff')
        ->put(route('staff.settings.update-profile', $this->school), [
            'phone' => $this->staff->phone,
            'email' => $this->staff->email,
            'address' => $this->staff->address,
            'emergency_contact_name' => $this->staff->emergency_contact_name,
            'emergency_contact_phone' => $this->staff->emergency_contact_phone,
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('a staff member can view the help page', function () {
    $this->actingAs($this->staff, 'staff')
        ->get(route('staff.help.index', $this->school))
        ->assertStatus(200)
        ->assertSee($this->school->name);
});

test('a staff member cannot browse another school\'s portal by visiting its slug', function () {
    $otherSchool = School::factory()->create();

    $this->actingAs($this->staff, 'staff')
        ->get(route('staff.dashboard', $otherSchool))
        ->assertStatus(404);
});
