<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreSupportTicketReplyRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\NewSupportTicketNotification;
use App\Notifications\SupportTicketRepliedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(): View
    {
        $tickets = auth()->user()->school->supportTickets()->latest()->paginate(10);

        return view('support-tickets.index', [
            'tickets' => $tickets,
        ]);
    }

    public function create(): View
    {
        return view('support-tickets.create');
    }

    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        $ticket = auth()->user()->school->supportTickets()->create([
            ...$request->validated(),
            'opened_by' => auth()->id(),
        ]);

        User::where('role', UserRole::SuperAdmin)->each(
            fn (User $superAdmin) => $superAdmin->notify(new NewSupportTicketNotification($ticket))
        );

        return redirect()->route('support-tickets.show', $ticket)
            ->with('status', 'Your support ticket has been submitted.');
    }

    public function show(SupportTicket $ticket): View
    {
        abort_unless($ticket->school_id === auth()->user()->school_id, 403);

        $ticket->load(['replies.user', 'assignedTo']);

        return view('support-tickets.show', [
            'ticket' => $ticket,
        ]);
    }

    public function reply(StoreSupportTicketReplyRequest $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->school_id === auth()->user()->school_id, 403);

        $reply = $ticket->replies()->create([
            'user_id' => auth()->id(),
            'message' => $request->validated('message'),
        ]);

        $notifyUsers = $ticket->assignedTo ? collect([$ticket->assignedTo]) : User::where('role', UserRole::SuperAdmin)->get();
        $notifyUsers->each(fn (User $user) => $user->notify(new SupportTicketRepliedNotification($reply)));

        return back()->with('status', 'Reply sent.');
    }
}
