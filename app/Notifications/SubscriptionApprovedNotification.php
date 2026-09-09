<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The welcome email: "your school is live".
 *
 * SENT ONLY WHEN A SUPER ADMIN HAS APPROVED THE PAYMENT, never on
 * registration and never when the school uploads its receipt. At both of
 * those moments the account still does not work, and telling a school
 * "welcome, you're all set" while their portal would turn their staff away is
 * the one version of this email that damages trust.
 *
 * What the school receives at submission is the invoice
 * (SubscriptionInvoiceIssuedNotification), which says plainly that approval is
 * still to come. This is the second half of that conversation, and it carries
 * the full summary of what they bought.
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

        $message = (new MailMessage)
            ->subject('Welcome to AkademicNest - '.$school->name.' Is Now Active')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your payment has been reviewed and approved, and your AkademicNest account is now active. Welcome aboard.')
            ->line('**A summary of your subscription**')
            ->line('**School:** '.$school->name)
            ->line('**Registered email:** '.($school->billing_email ?: $notifiable->email))
            ->line('**Plan:** '.$subscription->plan->name);

        if ($subscription->billing_cycle) {
            $message->line('**Billing cycle:** '.$subscription->billing_cycle->label());
        }

        // Only where it means something. The per-student plans sell licences;
        // on a flat-rate plan a "0 pupils" line reads like a mistake.
        if ($subscription->students_count) {
            $message->line('**Student licences:** '.number_format($subscription->students_count));

            $message->line('**Price per licence:** '.($subscription->currency ?: 'NGN').' '
                .number_format((float) $subscription->amount / $subscription->students_count, 2));
        }

        $message
            ->line('**Amount:** '.($subscription->currency ?: 'NGN').' '.number_format((float) $subscription->amount, 2))
            ->line('**Payment status:** Approved')
            ->line('**Subscription status:** Active');

        if ($subscription->ends_at) {
            $message->line('**Runs until:** '.$subscription->ends_at->format('j F Y'));
        }

        if ($this->invoice) {
            $message->line('**Invoice:** '.$this->invoice->number);
        }

        return $message
            ->line('Your reference is '.$subscription->reference.'. Keep it for your records.')
            ->action('Go to Your Dashboard', route('dashboard'))
            ->salutation('— AkademicNest Team');
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
}
