<?php

namespace App\Services\Mail;

use App\Enums\UserRole;
use App\Models\EmailDelivery;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionTopUp;
use App\Models\User;
use App\Notifications\SubscriptionApprovedNotification;
use App\Notifications\SubscriptionInvoiceIssuedNotification;
use App\Notifications\SubscriptionTopUpApprovedNotification;
use App\Services\SubscriptionInvoiceIssuer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The emails a school receives when a Super Admin approves its payment.
 *
 * A NEW SUBSCRIPTION gets two: the welcome email, and the paid invoice with
 * the PDF attached.
 *
 * A TOP-UP gets two of its own: a top-up confirmation with the capacity before
 * and after, and the paid invoice for the top-up. It does not borrow the
 * welcome email, whose wording is for a school that has just joined.
 *
 * WHO RECEIVES THEM. The school's active School Admin accounts, which is the
 * address the school registered with. The invoice also goes to the school's
 * billing email when that is a different address, since that is who files it.
 * Nobody outside the school.
 *
 * RESENDING. Each email is recorded per recipient. Resending sends only the
 * ones that have not been delivered, so pressing "Resend" after fixing the mail
 * settings does not give a school a second copy of what it already has.
 */
class SubscriptionEmails
{
    public const WELCOME = 'welcome';

    public const INVOICE = 'invoice-receipt';

    public const TOP_UP = 'top-up-confirmation';

    public const TOP_UP_INVOICE = 'top-up-invoice-receipt';

    public function __construct(
        private readonly TransactionalMailer $mailer,
        private readonly SubscriptionInvoiceIssuer $invoices,
    ) {}

    /**
     * @return Collection<int, EmailDelivery>
     */
    public function approved(Subscription $subscription, bool $onlyUndelivered = false): Collection
    {
        $subscription = $subscription->fresh(['school', 'plan', 'latestPayment']);
        $school = $subscription->school;
        $invoice = $this->invoices->forSubscription($subscription);
        $admins = $this->adminsOf($school);

        if ($admins->isEmpty()) {
            return collect([$this->nobodyToEmail($school, $subscription, self::WELCOME)]);
        }

        return $this->deliver($admins, fn () => new SubscriptionApprovedNotification($subscription, $invoice), self::WELCOME, $subscription, $school, $onlyUndelivered)
            ->merge($this->deliver(
                $this->withBillingEmail($admins, $school),
                fn () => new SubscriptionInvoiceIssuedNotification($invoice->fresh()),
                self::INVOICE,
                $subscription,
                $school,
                $onlyUndelivered,
            ));
    }

    /**
     * @return Collection<int, EmailDelivery>
     */
    public function topUpApproved(SubscriptionTopUp $topUp, bool $onlyUndelivered = false): Collection
    {
        $topUp = $topUp->fresh(['subscription.school', 'subscription.plan']);
        $school = $topUp->subscription->school;
        $invoice = $this->invoices->forTopUp($topUp);
        $admins = $this->adminsOf($school);

        if ($admins->isEmpty()) {
            return collect([$this->nobodyToEmail($school, $topUp, self::TOP_UP)]);
        }

        return $this->deliver($admins, fn () => new SubscriptionTopUpApprovedNotification($topUp, $invoice), self::TOP_UP, $topUp, $school, $onlyUndelivered)
            ->merge($this->deliver(
                $this->withBillingEmail($admins, $school),
                fn () => new SubscriptionInvoiceIssuedNotification($invoice->fresh()),
                self::TOP_UP_INVOICE,
                $topUp,
                $school,
                $onlyUndelivered,
            ));
    }

    /**
     * The latest attempt for each email about this subscription or top-up.
     *
     * @return Collection<int, EmailDelivery>
     */
    public static function latestFor(Model $about): Collection
    {
        return EmailDelivery::query()
            ->where('about_type', $about->getMorphClass())
            ->where('about_id', $about->getKey())
            ->latest('id')
            ->get()
            ->unique(fn (EmailDelivery $delivery) => $delivery->kind.'|'.$delivery->recipient)
            ->values();
    }

    /**
     * @param  Collection<int, User|string>  $recipients
     * @return Collection<int, EmailDelivery>
     */
    private function deliver(Collection $recipients, callable $make, string $kind, Model $about, School $school, bool $onlyUndelivered): Collection
    {
        if ($onlyUndelivered) {
            $delivered = self::latestFor($about)
                ->filter(fn (EmailDelivery $delivery) => $delivery->kind === $kind && $delivery->wasSent())
                ->pluck('recipient')
                ->map(fn (string $email) => mb_strtolower($email));

            $recipients = $recipients->reject(fn (User|string $recipient) => $delivered->contains(
                mb_strtolower($recipient instanceof User ? (string) $recipient->email : $recipient)
            ));
        }

        return $this->mailer->sendToEach($recipients, $make, $kind, $about, $school->id);
    }

    /**
     * @return Collection<int, User>
     */
    private function adminsOf(School $school): Collection
    {
        return $school->users()
            ->where('role', UserRole::SchoolAdmin)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, User>  $admins
     * @return Collection<int, User|string>
     */
    private function withBillingEmail(Collection $admins, School $school): Collection
    {
        $billing = trim((string) $school->billing_email);

        if ($billing === '' || $admins->contains(fn (User $admin) => mb_strtolower((string) $admin->email) === mb_strtolower($billing))) {
            return $admins;
        }

        return $admins->toBase()->push($billing);
    }

    private function nobodyToEmail(School $school, Model $about, string $kind): EmailDelivery
    {
        return EmailDelivery::create([
            'school_id' => $school->id,
            'kind' => $kind,
            'about_type' => $about->getMorphClass(),
            'about_id' => $about->getKey(),
            'recipient' => '(no school admin)',
            'status' => EmailDelivery::FAILED,
            'error' => 'This school has no active School Admin account with an email address, so there was nobody to email.',
        ]);
    }
}
