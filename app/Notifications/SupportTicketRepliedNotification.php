<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\SupportTicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketRepliedNotification extends Notification
{
    use Queueable;

    public function __construct(public SupportTicketReply $reply) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->reply->ticket;

        return (new MailMessage)
            ->subject('New Reply on Ticket: '.$ticket->subject)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->reply->user->name.' replied to the ticket "'.$ticket->subject.'".')
            ->line($this->reply->message)
            ->action('View Ticket', $this->ticketUrl($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $ticket = $this->reply->ticket;

        return [
            'title' => 'New Reply: '.$ticket->subject,
            'body' => $this->reply->user->name.' replied to your ticket.',
            'url' => $this->ticketUrl($notifiable),
        ];
    }

    private function ticketUrl(object $notifiable): string
    {
        $ticket = $this->reply->ticket;

        return $notifiable->role === UserRole::SuperAdmin
            ? route('super-admin.support-tickets.show', $ticket)
            : route('support-tickets.show', $ticket);
    }
}
