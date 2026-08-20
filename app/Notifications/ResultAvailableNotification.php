<?php

namespace App\Notifications;

use App\Models\Examination;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResultAvailableNotification extends Notification
{
    use Queueable;

    public function __construct(public Examination $examination, public Student $student) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = "{$this->student->fullName()}'s {$this->examination->name} result is ready";

        return (new MailMessage)
            ->subject($title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line("{$this->student->fullName()}'s result for {$this->examination->name} ({$this->examination->term->label()}, {$this->examination->session}) is now available.")
            ->line('Log in to your portal to view the full result and report card.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->student->fullName()}'s result is ready",
            'body' => "{$this->examination->name} ({$this->examination->term->label()}, {$this->examination->session}) has been shared with you.",
        ];
    }
}
