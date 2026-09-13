<?php

namespace App\Notifications;

use App\Enums\InterviewMode;
use App\Models\JobInterview;
use App\Support\SchoolContact;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites an applicant to an interview, with everything they need to attend:
 * when (in the school's own timezone), how, where or the meeting link, what to
 * prepare, and how to reach the school.
 */
class JobInterviewInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly JobInterview $interview) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $interview = $this->interview;
        $application = $interview->application;
        $job = $application->jobPosting;
        $school = $job->school;
        $when = $interview->localScheduledFor();
        $contact = SchoolContact::for($school);

        $message = (new MailMessage)
            ->subject('Interview invitation: '.$job->title.' at '.$school->name)
            ->greeting('Hello '.$application->full_name.',')
            ->line('**'.$school->name.'** would like to invite you to an interview for the position of **'.$job->title.'**.')
            ->line('**Date:** '.$when->format('l, j F Y'))
            ->line('**Time:** '.$when->format('g:i A').' ('.$when->getTimezone()->getName().')')
            ->line('**Interview type:** '.$interview->mode->label());

        if ($interview->mode === InterviewMode::Physical && filled($interview->location)) {
            $message->line('**Location:** '.$interview->location);
        }

        if ($interview->mode === InterviewMode::Online && filled($interview->meeting_link)) {
            $message->line('**Meeting link:** '.$interview->meeting_link);
        }

        if (filled($interview->instructions)) {
            $message->line('**Instructions:** '.$interview->instructions);
        }

        if (filled($interview->message)) {
            $message->line($interview->message);
        }

        if ($interview->mode === InterviewMode::Online && filled($interview->meeting_link)) {
            $message->action('Join the interview', $interview->meeting_link);
        }

        $reach = collect([$contact->phone, $contact->email])->filter()->implode(' or ');

        if ($reach !== '') {
            $message->line('If you need to reschedule or have any questions, please contact the school on '.$reach.'.');
        }

        return $message->salutation('— '.$school->name);
    }
}
