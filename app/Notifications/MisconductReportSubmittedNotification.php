<?php

namespace App\Notifications;

use App\Models\MisconductReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells a school that somebody outside it has reported a pupil's conduct.
 *
 * The notification carries who reported it and a first line of what they said,
 * and nothing else. The description may name a child, describe an injury or
 * repeat an accusation, and a notification is the least private place in the
 * application - it sits in a bell menu, on a phone's lock screen. Whoever
 * needs the detail opens the report.
 */
class MisconductReportSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly MisconductReport $report) {}

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
            'title' => 'New conduct report received',
            'body' => $this->report->reporter_name.' reported: '.Str::limit($this->report->description, 90),
            'report_uuid' => $this->report->uuid,

            // Where to go when it is clicked. Without this the reader is
            // sent to a generic dashboard instead of the thing they asked
            // to see - see NotificationController.
            'url' => route('inbox.index', ['tab' => 'reports']),
            'attachments' => $this->report->attachments()->count(),
        ];
    }
}
