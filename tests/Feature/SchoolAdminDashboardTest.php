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

test('a school admin with an active subscription sees the full dashboard', function () {
    Subscription::factory()->create([
        'school_id' => $this->school->id,
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->addMonths(3),
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertStatus(200)
        ->assertSee('Total Students')
        ->assertSee('Total Staff')
        ->assertSee('Total Classes')
        ->assertSee('Outstanding Fees')
        ->assertSee('Student Attendance Overview')
        ->assertSee('Recent Announcements')
        ->assertSee('Upcoming Events')
        ->assertSee('Fee Collection Overview')
        ->assertSee($this->school->name);
});

test('recent announcements on the dashboard show real data', function () {
    Subscription::factory()->create(['school_id' => $this->school->id, 'status' => SubscriptionStatus::Active]);
    Announcement::factory()->create(['title' => 'Mid-Term Break', 'body' => 'School will be closed from May 20 to May 24.']);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertSee('Mid-Term Break')
        ->assertSee('School will be closed from May 20 to May 24.');
});

test('the sidebar lists every module and links to a real route', function () {
    Subscription::factory()->create(['school_id' => $this->school->id, 'status' => SubscriptionStatus::Active]);

    $response = $this->actingAs($this->admin)->get(route('dashboard'));

    foreach ([
        'students.index', 'staff.index', 'academics.index', 'attendance.index',
        'examinations.index', 'assignments.index', 'events.index', 'communications.index',
        'library.index', 'finance.index', 'website.index', 'settings.index',
    ] as $routeName) {
        $response->assertSee(route($routeName), false);
    }
});

test('a school admin can view the communications page with real announcements', function () {
    Announcement::factory()->create(['title' => 'Science Fair 2025', 'body' => 'The annual science fair will hold on June 5.']);

    $this->actingAs($this->admin)
        ->get(route('communications.index'))
        ->assertStatus(200)
        ->assertSee('Science Fair 2025')
        ->assertSee('The annual science fair will hold on June 5.');
});

test('unbuilt modules show a coming soon page instead of a 404', function () {
    foreach ([
        'students.index' => 'Students',
        'staff.index' => 'Staff',
        'academics.index' => 'Academics',
        'attendance.index' => 'Attendance',
        'examinations.index' => 'Examinations',
        'assignments.index' => 'Assignments',
        'events.index' => 'Events',
        'library.index' => 'Library',
        'finance.index' => 'Finance',
        'website.index' => 'Website',
        'settings.index' => 'Settings',
    ] as $routeName => $label) {
        $this->actingAs($this->admin)
            ->get(route($routeName))
            ->assertStatus(200)
            ->assertSee("{$label} is coming soon");
    }
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
