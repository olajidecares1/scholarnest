<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use App\Services\SubscriptionInvoiceDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * "Here is your invoice."
 *
 * Sent when the invoice is raised, which is when the school submits its
 * subscription, before any Super Admin has looked at it. So the email says
 * plainly that the account is not active yet and what has to happen next. A
 * billing email that reads like a receipt, for something still awaiting
 * approval, is how a school ends up believing it has an account it does not
 * have.
 *
 * The PDF is BOTH attached and linked. Attached because an invoice is a
 * document a bursar files, and linked because attachments are stripped by
 * plenty of mail systems and a link still works when the attachment is gone.
 */
class SubscriptionInvoiceIssuedNotification extends Notification
{
    use Queueable;

    /**
     * How long the link in this email keeps working.
     *
     * Long, because an invoice is a durable record and a bursar may come back
     * to it weeks later, and not forever, because the link carries billing
     * details and needs an end. A school admin can always fetch it again from
     * inside the platform.
     */
    private const LINK_DAYS = 90;

    public function __construct(public SubscriptionInvoice $invoice) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice;

        $message = (new MailMessage)
            ->subject('Your AkademicNest Invoice '.$invoice->number)
            ->greeting('Hello '.$invoice->billed_to_name.',')
            ->line('Thank you for choosing AkademicNest. Your invoice is below, and a PDF copy is attached.')
            ->line('**Invoice number:** '.$invoice->number)
            ->line('**Invoice date:** '.$invoice->issued_at->format('j F Y'))
            ->line('**Plan:** '.$invoice->plan_name.($invoice->billing_cycle ? ' ('.$invoice->billing_cycle.')' : ''));

        if ($invoice->licences) {
            $message->line('**Student licences:** '.number_format($invoice->licences));
        }

        if ($invoice->unit_price !== null) {
            $message->line('**Price per licence:** '.$invoice->currency.' '.number_format((float) $invoice->unit_price, 2));
        }

        $message
            ->line('**Amount:** '.$invoice->formattedTotal())
            ->line('**Status:** '.$invoice->paymentStatus());

        if (! $invoice->isPaid()) {
            $message->line(
                'Your account is **awaiting approval**. A AkademicNest administrator '
                .'reviews every payment before an account is activated, and we will '
                .'email you the moment yours is approved.'
            );
        }

        $document = app(SubscriptionInvoiceDocument::class);

        return $message
            ->action('View Invoice', $this->url())
            ->salutation('Regards, AkademicNest Team')
            ->attachData(
                $document->render($invoice),
                $document->filename($invoice),
                ['mime' => 'application/pdf'],
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Invoice '.$this->invoice->number,
            'body' => 'Your invoice for '.$this->invoice->formattedTotal().' has been issued.',
            'url' => $this->url(),
        ];
    }

    /**
     * A signed link, built from the configured application URL rather than
     * from the request, so a forged Host header on the request that triggered
     * this email cannot decide where the school is sent to view its billing.
     */
    private function url(): string
    {
        return rtrim((string) config('app.url'), '/').URL::temporarySignedRoute(
            'invoices.view',
            now()->addDays(self::LINK_DAYS),
            $this->invoice,
            absolute: false,
        );
    }
}
