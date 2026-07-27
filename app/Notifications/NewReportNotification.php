<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewReportNotification extends Notification
{
    use Queueable;

    public function __construct(public Report $report) {}

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
            ->subject('New Report Submitted: '.$this->report->reference)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A new report has been submitted for review.')
            ->line('Reference: '.$this->report->reference)
            ->action('Review Report', route('super-admin.reports.show', $this->report));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Report Submitted',
            'body' => 'Reference '.$this->report->reference.' is awaiting review.',
            'url' => route('super-admin.reports.show', $this->report),
        ];
    }
}
