<?php

namespace App\Notifications;

use App\Models\SchoolNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewNoticePosted extends Notification
{
    use Queueable;

    public function __construct(public SchoolNotice $notice) {}

    /**
     * @return array<int, string>
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
            'title' => $this->notice->title,
            'body' => $this->notice->body,
        ];
    }
}
