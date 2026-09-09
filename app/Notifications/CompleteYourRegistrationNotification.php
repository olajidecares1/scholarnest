<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * "You started signing up and did not finish."
 *
 * One email, once. The school is told what is outstanding and given a link
 * straight to the step they stopped at - and that link does not sign them in,
 * for the reasons in App\Http\Controllers\RegistrationResumeController.
 */
class CompleteYourRegistrationNotification extends Notification
{
    use Queueable;

    public function __construct(public School $school) {}

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
            ->subject('Finish Setting Up '.$this->school->name.' On AkademicNest')
            ->greeting('Welcome back, '.$this->school->name.'!')
            ->line('We noticed your AkademicNest registration was never completed, so your school is not live yet.')
            ->line('Your account exists and everything you entered has been kept. What is still outstanding is choosing a plan and submitting your payment for approval.')
            ->line('It takes a few minutes, and you can pick up exactly where you stopped.')
            ->action('Complete My Registration', $this->url())
            ->line('If you have already completed this, or you no longer wish to continue, you can ignore this email - we will not send another.')
            ->salutation('— AkademicNest Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Complete your registration',
            'body' => 'Your school is not live yet - choose a plan to finish signing up.',
            'url' => $this->url(),
        ];
    }

    /**
     * Built from the configured application URL rather than from the request,
     * so a forged Host header can never decide where this link points.
     */
    private function url(): string
    {
        return rtrim((string) config('app.url'), '/').URL::temporarySignedRoute(
            'registration.resume',
            now()->addDays((int) config('registration.resume_link_days', 14)),
            $this->school,
            absolute: false,
        );
    }
}
