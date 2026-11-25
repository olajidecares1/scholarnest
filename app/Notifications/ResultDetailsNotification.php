<?php

namespace App\Notifications;

use App\Models\Examination;
use App\Models\Student;
use App\Support\WhatsAppNumber;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A student's result details, emailed to one of their parents/guardians at
 * the School Admin's request: where to check the result, the token that
 * opens it, and the student's admission number.
 *
 * Unlike ResultTokenIssuedNotification, which only points to the portal, this
 * carries the token itself, because it is sent to parents who may have no
 * portal account at all. What keeps that safe is everything around it:
 *   - it is sent only to an address on THIS student's own record or a parent
 *     account linked to them (GuardianContacts);
 *   - the token opens one result of one student and nothing else, has a
 *     limited number of views, and can be revoked and reissued at any time;
 *   - every message is recorded in the sending history, and the token never
 *     reaches a log (see __debugInfo).
 *
 * Mail only, sent through TransactionalMailer, so a failure is reported as a
 * failure and never as sent.
 */
class ResultDetailsNotification extends Notification
{
    public function __construct(
        public Student $student,
        public Examination $examination,
        public string $token,
        public string $resultLink,
        public string $recipientName,
        // The school's verified WhatsApp number (international digits), so
        // parents can reach the school there; null to leave it out.
        public ?string $schoolWhatsApp = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->student->school;
        $period = "{$this->examination->term->label()}, {$this->examination->session}";

        return (new MailMessage)
            ->subject("{$this->student->fullName()}'s {$period} result")
            ->greeting("Dear {$this->recipientName},")
            ->line("{$school->name} has released {$this->student->fullName()}'s result for {$period} ({$this->examination->class_name}).")
            ->line("**Student:** {$this->student->fullName()}")
            ->line("**Admission number:** {$this->student->admission_number}")
            ->line("**Result token:** `{$this->token}`")
            ->action('Check the result', $this->resultLink)
            ->line('Open the link above and enter the result token to view the result.')
            ->line('This token opens this one result only. Please keep it private and do not share it.')
            ->when($this->schoolWhatsApp !== null, fn (MailMessage $mail) => $mail->line(
                'Questions? Chat with the school on WhatsApp: ['.WhatsAppNumber::display((string) $this->schoolWhatsApp).']('.WhatsAppNumber::link((string) $this->schoolWhatsApp).')'
            ))
            ->salutation("Regards,\n{$school->name}");
    }

    /**
     * The WhatsApp version: plain text, and the template's values.
     *
     * @return array{text: string, values: list<string>}
     */
    public function forWhatsApp(): array
    {
        $school = $this->student->school;
        $period = "{$this->examination->term->label()} {$this->examination->session}";

        // The template has no blank of its own for the school's WhatsApp, so
        // it rides with the name: "Glory Academy (WhatsApp +234 803 123 4567)".
        $schoolName = $this->schoolWhatsApp !== null
            ? "{$school->name} (WhatsApp ".WhatsAppNumber::display($this->schoolWhatsApp).')'
            : (string) $school->name;

        return [
            'text' => "Dear {$this->recipientName},\n\n"
                ."{$schoolName} has released {$this->student->fullName()}'s {$period} result.\n\n"
                ."Admission number: {$this->student->admission_number}\n"
                ."Result token: {$this->token}\n"
                ."Check the result: {$this->resultLink}\n\n"
                .'This token opens this one result only. Please keep it private.'
                .($this->schoolWhatsApp !== null ? "\n\nChat with the school: ".WhatsAppNumber::link($this->schoolWhatsApp) : ''),

            // The order WHATSAPP_RESULT_TEMPLATE's {{1}}..{{7}} expect.
            'values' => [
                $this->recipientName,
                $schoolName,
                $this->student->fullName(),
                $period,
                (string) $this->student->admission_number,
                $this->token,
                $this->resultLink,
            ],
        ];
    }

    /**
     * Never let the token reach a log or an exception report.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'student' => $this->student->id,
            'examination' => $this->examination->id,
            'token' => '[redacted]',
        ];
    }
}
