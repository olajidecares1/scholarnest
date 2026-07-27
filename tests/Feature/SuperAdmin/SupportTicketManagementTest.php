<?php

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketRepliedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('super admin can view the support tickets list', function () {
    $ticket = SupportTicket::factory()->create(['subject' => 'Login not working']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.support-tickets.index'))
        ->assertStatus(200)
        ->assertSee('Login not working');
});

test('super admin can reply to a ticket and the school is notified', function () {
    Notification::fake();

    $school = School::factory()->create();
    $schoolAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $ticket = SupportTicket::factory()->create(['school_id' => $school->id]);

    $this->actingAs($this->superAdmin)->post(route('super-admin.support-tickets.reply', $ticket), [
        'message' => 'We are looking into this.',
    ]);

    expect($ticket->replies()->where('message', 'We are looking into this.')->exists())->toBeTrue();
    Notification::assertSentTo($schoolAdmin, SupportTicketRepliedNotification::class);
});

test('super admin can update ticket status and assignment', function () {
    $ticket = SupportTicket::factory()->create(['status' => TicketStatus::Open]);
    $assignee = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($this->superAdmin)->put(route('super-admin.support-tickets.update', $ticket), [
        'status' => 'in_progress',
        'assigned_to' => $assignee->id,
    ]);

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::InProgress);
    expect($ticket->assigned_to)->toBe($assignee->id);
});
