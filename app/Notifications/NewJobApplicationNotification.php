<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells a School Admin that somebody has applied for one of the school's
 * vacancies - who, for what, how to reach them, and where the application is.
 *
 * Only contact details and the position travel in the notification. The cover
 * letter and CV stay behind the application page, which checks the school.
 */
class NewJobApplicationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly JobApplication $application) {}

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
        $application = $this->application;
        $job = $application->jobPosting;

        return [
            'title' => 'New application: '.$job->title,
            'body' => $application->full_name.' applied for '.$job->title.'.',
            'applicant_name' => $application->full_name,
            'applicant_email' => $application->email,
            'applicant_phone' => $application->phone,
            'position' => $job->title,
            'status' => $application->status->label(),
            'submitted_at' => $application->created_at?->toIso8601String(),
            'application_uuid' => $application->uuid,
            'url' => route('careers.applications.show', $application),
        ];
    }
}
