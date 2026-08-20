<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewAssignmentPosted extends Notification
{
    use Queueable;

    public function __construct(public Assignment $assignment) {}

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
            'title' => 'New Assignment: '.$this->assignment->title,
            'body' => "A new {$this->assignment->subject} assignment is due ".$this->assignment->due_date->format('M j, Y').'.',
        ];
    }
}
