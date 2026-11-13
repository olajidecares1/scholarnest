<?php

namespace App\Http\Controllers\Subscriptions;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Notifications\NewSubscriptionSubmittedNotification;
use App\Notifications\SubscriptionInvoiceIssuedNotification;
use App\Services\Mail\TransactionalMailer;
use App\Services\SubscriptionInvoiceIssuer;
use App\Services\SubscriptionWizardService;
use App\Services\TeamNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ReviewController extends Controller
{
    public function __construct(
        private readonly SubscriptionWizardService $wizard,
        private readonly TeamNotifier $team,
        private readonly SubscriptionInvoiceIssuer $invoices,
    ) {}

    public function create(): View|RedirectResponse
    {
        if (! $this->wizard->hasPaymentMethod()) {
            return redirect()->route('subscriptions.payment-method');
        }

        $school = auth()->user()->school;

        if ($pending = $this->wizard->pendingSubscription($school)) {
            return $this->alreadySubmitted($pending);
        }

        return view('subscriptions.review', [
            'plan' => Plan::findOrFail($this->wizard->get('plan_id')),
            'billingCycle' => BillingCycle::from($this->wizard->get('billing_cycle')),
            'studentsCount' => $this->wizard->get('students_count'),
            'amount' => (float) $this->wizard->get('amount'),
            'billingContactName' => $this->wizard->get('billing_contact_name'),
            'billingEmail' => $this->wizard->get('billing_email'),
            'billingPhone' => $this->wizard->get('billing_phone'),
            'billingAddress' => $this->wizard->get('billing_address'),
            'paymentMethod' => PaymentMethod::from($this->wizard->get('payment_method')),
            'receiptOriginalName' => $this->wizard->get('receipt_original_name'),
            'reference' => $this->wizard->reference($school),
        ]);
    }

    /**
     * The final step: the subscription, its payment and its invoice, saved
     * together or not at all, then everybody told.
     *
     * The order is the point. Everything the school's application consists of
     * is written in one transaction, and the wizard is only cleared once that
     * transaction has committed, so a failure leaves the school on this page
     * with everything it entered, able to press the button again. Emails and
     * notifications come after, and none of them can turn a saved application
     * into an error page: that is exactly what used to happen when the mail
     * server refused the login, the school saw a server error, went through
     * the wizard again, and the AkademicNest Team received the same payment
     * twice.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! $this->wizard->hasPaymentMethod()) {
            return redirect()->route('subscriptions.payment-method');
        }

        $school = auth()->user()->school;

        if (! $this->wizard->isComplete()) {
            return redirect()->route('subscriptions.choose-plan')
                ->withErrors(['subscription' => 'Some of your subscription details were lost, usually because the page was open for a long time. Please go through the steps again; nothing has been submitted.']);
        }

        $this->wizard->reference($school);
        $data = $this->wizard->data();

        // The receipt is the proof of payment the whole application rests on.
        // Checked before anything is saved, so an application can never be
        // submitted pointing at a file that is not there.
        if (! Storage::disk('local')->exists($data['receipt_path'])) {
            $this->wizard->put(['receipt_path' => null, 'receipt_original_name' => null, 'payment_method' => null]);

            return redirect()->route('subscriptions.payment-method')
                ->withErrors(['receipt' => 'We could not find the receipt you uploaded. Please upload it again; nothing has been submitted yet.']);
        }

        try {
            [$subscription, $invoice, $created] = DB::transaction(function () use ($school, $data) {
                // One application at a time per school. The lock makes two
                // presses that arrive together (a double click, two tabs) take
                // turns, so the second finds the first and stops there.
                School::whereKey($school->id)->lockForUpdate()->first();

                $existing = Subscription::where('school_id', $school->id)->where('reference', $data['reference'])->first()
                    ?? $this->wizard->pendingSubscription($school);

                if ($existing !== null) {
                    return [$existing, null, false];
                }

                $subscription = Subscription::create([
                    'school_id' => $school->id,
                    'plan_id' => $data['plan_id'],
                    'billing_cycle' => $data['billing_cycle'],
                    'students_count' => $data['students_count'] ?? null,
                    'amount' => $data['amount'],
                    'currency' => 'NGN',
                    'status' => SubscriptionStatus::PendingVerification,
                    'reference' => $data['reference'],
                ]);

                Payment::create([
                    'subscription_id' => $subscription->id,
                    'method' => $data['payment_method'],
                    'amount' => $data['amount'],
                    'currency' => 'NGN',
                    'status' => PaymentStatus::Pending,
                    'reference' => strtoupper((string) Str::random(10)),
                    'receipt_path' => $data['receipt_path'],
                    'receipt_original_name' => $data['receipt_original_name'],

                    // What screening could read off the receipt, for whoever
                    // reviews it. Notes, not a verdict, the wording deliberately
                    // ends "not verified".
                    'notes' => $data['receipt_screening_notes'] ?? null,
                ]);

                // The invoice is raised the moment the subscription is
                // submitted, not when it is approved: a school that has just
                // paid needs the document now, for its own books. Inside the
                // transaction, so an application is never saved without one.
                // Issuing is idempotent, so a replay cannot bill twice.
                $invoice = $this->invoices->issueForSubscription($subscription);

                return [$subscription, $invoice, true];
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('subscriptions.review')
                ->withErrors(['subscription' => 'We could not submit your subscription just now. Nothing was saved and your receipt is still attached, so please press "Confirm & Submit" again. If this keeps happening, contact the AkademicNest Team.']);
        }

        $this->wizard->clear();

        if (! $created) {
            // Already submitted. The receipt uploaded for this attempt belongs
            // to nothing, so it is not kept.
            if (! Payment::where('receipt_path', $data['receipt_path'])->exists()) {
                Storage::disk('local')->delete($data['receipt_path']);
            }

            return $this->alreadySubmitted($subscription);
        }

        $this->afterSubmitting($school, $subscription, $invoice);

        return redirect()->route('subscriptions.confirmation', $subscription);
    }

    /**
     * Emails, the audit trail and the team's notification, for an application
     * that is already saved. Each one is attempted on its own and none can
     * fail the request: the school's payment is recorded whatever happens
     * here, and an email that did not go is in email_deliveries with the
     * reason, for the AkademicNest Team to see and send again.
     */
    private function afterSubmitting(School $school, Subscription $subscription, SubscriptionInvoice $invoice): void
    {
        $steps = [
            fn () => app(TransactionalMailer::class)->sendToEach(
                $school->users()->whereNotNull('email')->get(),
                fn () => new SubscriptionInvoiceIssuedNotification($invoice),
                'invoice-issued',
                $subscription,
                $school->id,
            ),
            fn () => AuditLog::record('subscription.submitted', "Submitted a {$subscription->plan->name} subscription for review.", $subscription),
            fn () => AuditLog::record('invoice.issued', "Issued invoice {$invoice->number} to {$school->name}.", $invoice),

            // One submission, one notification, claimed against the
            // subscription so a retried or replayed request announces nothing
            // twice. See App\Services\TeamNotifier.
            fn () => $this->team->once(
                'subscription.submitted:'.$subscription->uuid,
                new NewSubscriptionSubmittedNotification($subscription),
            ),
        ];

        foreach ($steps as $step) {
            try {
                $step();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function alreadySubmitted(Subscription $subscription): RedirectResponse
    {
        return redirect()->route('subscriptions.confirmation', $subscription)
            ->with('status', 'Your payment has already been submitted and is awaiting review by the AkademicNest Team. There is no need to submit it again.');
    }
}
