<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * The welcome email: "your school is approved and live".
 *
 * SENT ONLY WHEN A SUPER ADMIN HAS APPROVED THE PAYMENT, never on
 * registration and never when the school uploads its receipt. At both of
 * those moments the account still does not work, and telling a school
 * "welcome, you're all set" while their portal would turn their staff away is
 * the one version of this email that damages trust.
 *
 * The paid invoice follows as its own email (SubscriptionInvoiceIssuedNotification),
 * so a bursar can file it without the welcome text around it. This email names
 * the invoice number so the two are easy to match.
 *
 * It never contains a password. It tells the admin which email and username
 * to sign in with, and where.
 */
class SubscriptionApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public ?SubscriptionInvoice $invoice = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->subscription;
        $school = $subscription->school;
        $currency = $subscription->currency ?: 'NGN';

        $message = (new MailMessage)
            ->subject('Welcome to AkademicNest: '.$school->name.' Is Now Active')
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line('Good news: **'.$school->name.'** has been approved. Your payment has been confirmed and your AkademicNest account is now active, so you can sign in and start using it right away.')
            ->line('**Your school**')
            ->line('School: '.$school->name);

        if ($school->billing_email) {
            $message->line('Registered email: '.$school->billing_email);
        }

        if ($school->school_code) {
            $message->line('School code: '.$school->school_code);
        }

        $message->line('Plan: '.$subscription->plan->name);

        if ($subscription->billing_cycle) {
            $message->line('Billing cycle: '.$subscription->billing_cycle->label());
        }

        // Only where it means something. The per-student plans sell licences;
        // on a flat-rate plan a "0 students" line reads like a mistake.
        if ($subscription->students_count) {
            $message->line('Student capacity: '.number_format($subscription->students_count).' students');
        }

        $message
            ->line('Amount paid: '.$currency.' '.number_format((float) $subscription->amount, 2))
            ->line('Payment reference: '.$subscription->reference)
            ->line('Payment status: Approved')
            ->line('Subscription status: Active');

        if ($subscription->starts_at && $subscription->ends_at) {
            $message->line('Active from '.$subscription->starts_at->format('j F Y').' to '.$subscription->ends_at->format('j F Y'));
        }

        if ($this->invoice) {
            $message->line('Invoice: '.$this->invoice->number.' (sent to you in a separate email with the PDF attached)');
        }

        $message->line('**How to sign in**');

        if (isset($notifiable->email)) {
            $message->line('Email: '.$notifiable->email);
        }

        if (filled($notifiable->username ?? null)) {
            $message->line('Username: '.$notifiable->username);
        }

        return $message
            ->line('Use the password you created when you registered. If you have forgotten it, use "Forgot password?" on the sign-in page to reset it with a code sent to this email address.')
            ->action('Sign In to AkademicNest', $this->signInUrl())
            ->line('Keep your password private. AkademicNest staff will never ask you for it.')
            ->salutation('Regards, AkademicNest Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your school is active',
            'body' => 'Your '.$this->subscription->plan->name.' subscription has been approved and activated.',
            'url' => route('dashboard'),
        ];
    }

    /**
     * The school's own admin sign-in page, built from configuration rather
     * than from the request that approved the school.
     */
    private function signInUrl(): string
    {
        try {
            return $this->subscription->school->portalLoginUrl('web');
        } catch (Throwable) {
            return rtrim((string) config('app.url'), '/').route('portal.show', absolute: false);
        }
    }
}
