<?php

use App\Enums\SubscriptionStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create(['name' => 'Greenfield International School']);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('a school admin without a subscription sees the choose-a-plan prompt', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertStatus(200)
        ->assertSee('You don&rsquo;t have an active subscription yet', false)
        ->assertSee(route('subscriptions.choose-plan'), false);
});

test('a school admin with an active subscription sees the module grid dashboard', function () {
    Subscription::factory()->create([
        'school_id' => $this->school->id,
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->addMonths(3),
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertStatus(200)
        ->assertSee('Students')
        ->assertSee('Teachers & Staff')
        ->assertSee('Overview')
        ->assertSee('Reports')
        ->assertSee($this->school->name);
});

test('a school admin with an active subscription sees the full analytics overview', function () {
    Subscription::factory()->create([
        'school_id' => $this->school->id,
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->addMonths(3),
    ]);

    $this->actingAs($this->admin)
        ->get(route('overview.index'))
        ->assertStatus(200)
        ->assertSee('Total Students')
        ->assertSee('Teachers & Staff')
        ->assertSee('Term Average Score')
        ->assertSee('Outstanding Fees')
        ->assertSee('Attendance Overview')
        ->assertSee('Recent Notifications')
        ->assertSee('Students Performance')
        ->assertSee('Recent Announcements')
        ->assertSee('Upcoming Events')
        ->assertSee('Recent Activities')
        ->assertSee('Quick Actions')
        ->assertSee($this->school->name);
});

test('recent announcements on the overview page show real data', function () {
    Subscription::factory()->create(['school_id' => $this->school->id, 'status' => SubscriptionStatus::Active]);
    Announcement::factory()->create(['title' => 'Mid-Term Break', 'body' => 'School will be closed from May 20 to May 24.']);

    $this->actingAs($this->admin)
        ->get(route('overview.index'))
        ->assertSee('Mid-Term Break')
        ->assertSee('School will be closed from May 20 to May 24.');
});

test('the sidebar lists every module and links to a real route', function () {
    Subscription::factory()->create(['school_id' => $this->school->id, 'status' => SubscriptionStatus::Active]);

    $response = $this->actingAs($this->admin)->get(route('dashboard'));

    foreach ([
        'students.index', 'staff.index', 'academics.index', 'attendance.index',
        'examinations.index', 'assignments.index', 'cbt-practice.index', 'events.index', 'communications.index',
        'library.index', 'transport.index', 'hostels.index', 'finance.index', 'website.index', 'settings.index',
    ] as $routeName) {
        $response->assertSee(route($routeName), false);
    }
});

test('a school admin can view the communications page with real announcements', function () {
    activateSchool($this->school);
    Announcement::factory()->create(['title' => 'Science Fair 2025', 'body' => 'The annual science fair will hold on June 5.']);

    $this->actingAs($this->admin)
        ->get(route('communications.index'))
        ->assertStatus(200)
        ->assertSee('Science Fair 2025')
        ->assertSee('The annual science fair will hold on June 5.');
});

test('a super admin cannot access school-admin-only dashboard routes', function () {
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($superAdmin)
        ->get(route('students.index'))
        ->assertForbidden();
});

test('the header shows an unread badge for open support tickets', function () {
    Subscription::factory()->create(['school_id' => $this->school->id, 'status' => SubscriptionStatus::Active]);

    SupportTicket::factory()->create([
        'school_id' => $this->school->id,
        'status' => TicketStatus::Open,
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertStatus(200)
        ->assertSee('title="Support Tickets"', false);
});
