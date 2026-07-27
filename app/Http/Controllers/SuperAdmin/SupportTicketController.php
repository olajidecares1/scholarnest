<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportTicketReplyRequest;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketRepliedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $query = SupportTicket::query()->with(['school', 'assignedTo']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('subject', 'like', "%{$search}%")
                ->orWhereHas('school', fn ($sq) => $sq->where('name', 'like', "%{$search}%")));
        }

        return view('super-admin.support-tickets.index', [
            'tickets' => $query->latest()->paginate(10)->withQueryString(),
            'stats' => [
                'open' => SupportTicket::where('status', TicketStatus::Open)->count(),
                'inProgress' => SupportTicket::where('status', TicketStatus::InProgress)->count(),
                'resolved' => SupportTicket::where('status', TicketStatus::Resolved)->count(),
            ],
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load(['school', 'replies.user', 'assignedTo']);

        return view('super-admin.support-tickets.show', [
            'ticket' => $ticket,
            'superAdmins' => User::where('role', UserRole::SuperAdmin)->orderBy('name')->get(),
        ]);
    }

    public function reply(StoreSupportTicketReplyRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $reply = $ticket->replies()->create([
            'user_id' => auth()->id(),
            'message' => $request->validated('message'),
        ]);

        $ticket->school->users()->each(fn (User $user) => $user->notify(new SupportTicketRepliedNotification($reply)));

        AuditLog::record('support_ticket.replied', "Replied to ticket \"{$ticket->subject}\".", $ticket);

        return back()->with('status', 'Reply sent.');
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_column(TicketStatus::cases(), 'value'))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket->update($validated);

        AuditLog::record('support_ticket.updated', "Updated ticket \"{$ticket->subject}\" (status: {$ticket->status->label()}).", $ticket);

        return back()->with('status', 'Ticket updated.');
    }
}
