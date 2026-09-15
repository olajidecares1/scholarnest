<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionTopUp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your top-up has been approved."
 *
 * Its own email, not the welcome email with the figures swapped: a school
 * that has been using AkademicNest for a term does not need welcoming, it needs
 * to see what its capacity was, what it bought, and what it has now.
 *
 * The paid invoice for the top-up follows as its own email with the PDF
 * attached, as it does for a new subscription.
 */
class SubscriptionTopUpApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SubscriptionTopUp $topUp,
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
        $topUp = $this->topUp;
        $subscription = $topUp->subscription;
        $school = $subscription->school;
        $currency = $topUp->currency ?: ($subscription->currency ?: 'NGN');
        $added = (int) ($topUp->approved_students_count ?? $topUp->additional_students_count);

        $message = (new MailMessage)
            ->subject('Top-Up Approved: '.$school->name.' Now Has '.number_format((int) $topUp->new_students_count).' Student Places')
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line('Your subscription top-up for **'.$school->name.'** has been approved and processed. The extra student places are available now.')
            ->line('**Capacity**')
            ->line('Previous capacity: '.number_format((int) $topUp->previous_students_count).' students')
            ->line('Additional capacity purchased: '.number_format($added).' students')
            ->line('New total capacity: '.number_format((int) $topUp->new_students_count).' students');

        if ($added !== (int) $topUp->additional_students_count) {
            $message->line('You requested '.number_format((int) $topUp->additional_students_count).' places; '
                .number_format($added).' were allocated after the payment was checked.');
        }

        $message
            ->line('**Subscription**')
            ->line('Plan: '.$subscription->plan->name);

        if ($subscription->billing_cycle) {
            $message->line('Billing cycle: '.$subscription->billing_cycle->label());
        }

        if ($subscription->ends_at) {
            $message->line('Subscription runs until: '.$subscription->ends_at->format('j F Y'));
        }

        $message
            ->line('**Payment**')
            ->line('Amount paid: '.$currency.' '.number_format((float) $topUp->additional_amount, 2));

        if ($topUp->payment_method) {
            $message->line('Payment method: '.$topUp->payment_method->label());
        }

        $message
            ->line('Payment reference: '.$topUp->reference)
            ->line('Payment date: '.$topUp->created_at->format('j F Y'));

        if ($topUp->verified_at) {
            $message->line('Approved on: '.$topUp->verified_at->format('j F Y'));
        }

        if ($this->invoice) {
            $message->line('Invoice: '.$this->invoice->number.' (sent to you in a separate email with the PDF attached)');
        }

        return $message
            ->action('Go to Your Dashboard', rtrim((string) config('app.url'), '/').route('dashboard', absolute: false))
            ->salutation('Regards, AkademicNest Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Student top-up approved',
            'body' => 'Your top-up was approved. You can now admit up to '.number_format((int) $this->topUp->new_students_count).' students.',
            'url' => route('dashboard'),
        ];
    }
}
