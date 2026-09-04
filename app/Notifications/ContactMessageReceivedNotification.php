<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells a school that somebody has written to it through its website.
 */
class ContactMessageReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ContactMessage $message) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New website message',
            'body' => $this->message->name.': '.Str::limit($this->message->subject ?: $this->message->message, 80),
            'message_uuid' => $this->message->uuid,
            'url' => route('inbox.index'),
        ];
    }
}
