<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\NewSupportTicketNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('a school admin can submit a support ticket', function () {
    Notification::fake();

    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $response = $this->actingAs($this->schoolAdmin)->post(route('support-tickets.store'), [
        'subject' => 'Cannot upload receipt',
        'message' => 'The upload button does not respond.',
    ]);

    $ticket = SupportTicket::where('subject', 'Cannot upload receipt')->firstOrFail();

    $response->assertRedirect(route('support-tickets.show', $ticket));
    expect($ticket->school_id)->toBe($this->school->id);
    expect($ticket->opened_by)->toBe($this->schoolAdmin->id);

    Notification::assertSentTo($superAdmin, NewSupportTicketNotification::class);
});

test('a school admin can only view their own school tickets', function () {
    $otherSchool = School::factory()->create();
    $ticket = SupportTicket::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->schoolAdmin)
        ->get(route('support-tickets.show', $ticket))
        ->assertForbidden();
});

test('a school admin can reply to their own ticket', function () {
    Notification::fake();

    $ticket = SupportTicket::factory()->create([
        'school_id' => $this->school->id,
        'opened_by' => $this->schoolAdmin->id,
    ]);

    $response = $this->actingAs($this->schoolAdmin)->post(route('support-tickets.reply', $ticket), [
        'message' => 'Any update on this?',
    ]);

    $response->assertRedirect();
    expect($ticket->replies()->where('message', 'Any update on this?')->exists())->toBeTrue();
});
