<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirms to an applicant that their application arrived.
 *
 * Sent to the address they applied with, from the school's name. It links back
 * to the vacancy on the school's own Job Portal, never to anything in the
 * dashboard, which an applicant has no business opening.
 */
class JobApplicationReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly JobApplication $application) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $application = $this->application;
        $job = $application->jobPosting;
        $school = $job->school;

        return (new MailMessage)
            ->subject('Application received: '.$job->title.' at '.$school->name)
            ->greeting('Hello '.$application->full_name.',')
            ->line('Thank you for applying for **'.$job->title.'** at **'.$school->name.'**. Your application has been received.')
            ->line('The school will review it and contact you at this email address or on '.$application->phone.' if you are shortlisted.')
            ->action('View the vacancy', $job->publicUrl())
            ->salutation('Regards, '.$school->name);
    }
}
