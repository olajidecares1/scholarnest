<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSupportTicketNotification extends Notification
{
    use Queueable;

    public function __construct(public SupportTicket $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Support Ticket: '.$this->ticket->subject)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->ticket->school->name.' opened a new support ticket.')
            ->line('Subject: '.$this->ticket->subject)
            ->action('View Ticket', route('super-admin.support-tickets.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Support Ticket',
            'body' => $this->ticket->school->name.' opened: '.$this->ticket->subject,
            'url' => route('super-admin.support-tickets.show', $this->ticket),
        ];
    }
}
