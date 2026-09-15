<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\BillingCycle;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTopUpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\ApproveTopUpRequest;
use App\Http\Requests\SuperAdmin\BulkSubscriptionRequest;
use App\Http\Requests\SuperAdmin\RejectSubscriptionRequest;
use App\Models\AuditLog;
use App\Models\EmailDelivery;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionTopUp;
use App\Notifications\SubscriptionRejectedNotification;
use App\Notifications\SubscriptionTopUpRejectedNotification;
use App\Services\Mail\SubscriptionEmails;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'pending');

        $subscriptions = $tab === 'top-ups' ? null : $this->filteredQuery($request)->paginate(7)->withQueryString();
        $topUps = $tab === 'top-ups' ? $this->filteredTopUpQuery($request)->paginate(7)->withQueryString() : null;

        $autoActivateEligibleIds = $this->autoActivateEligibleQuery()->pluck('id');

        return view('super-admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'topUps' => $topUps,
            'tab' => $tab,
            'plans' => Plan::orderBy('sort_order')->get(),
            'autoActivateEligibleIds' => $autoActivateEligibleIds,
            'stats' => [
                'pending' => Subscription::where('status', SubscriptionStatus::PendingVerification)->count(),
                'autoActivate' => $autoActivateEligibleIds->count(),
                'active' => Subscription::where('status', SubscriptionStatus::Active)->count(),
                'expiringSoon' => Subscription::where('status', SubscriptionStatus::Active)
                    ->whereNotNull('ends_at')
                    ->whereBetween('ends_at', [now(), now()->addDays(30)])
                    ->count(),
                'pendingTopUps' => SubscriptionTopUp::where('status', SubscriptionTopUpStatus::PendingVerification)->count(),
            ],
        ]);
    }

    /**
     * One subscription, reviewed from inside the Super Admin panel.
     *
     * This exists because the only "view" for a subscription used to be
     * subscriptions.confirmation, the last step of the SCHOOL's signup
     * wizard. Sending a Super Admin there to review a payment showed them a
     * progress bar and "Thank You! Your Payment Has Been Received", as though
     * they were the school that had just paid, and dropped them out of their
     * own workflow entirely.
     */
    public function show(Subscription $subscription): View
    {
        $subscription->load(['plan', 'school', 'latestPayment.verifiedBy']);

        return view('super-admin.subscriptions.show', [
            'subscription' => $subscription,
            'emailDeliveries' => SubscriptionEmails::latestFor($subscription),
        ]);
    }

    public function approve(Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->status === SubscriptionStatus::PendingVerification, 409, 'This subscription has already been reviewed.');

        $deliveries = $this->activate($subscription);

        return $this->withEmailOutcome(
            back(),
            "Subscription for {$subscription->school->name} has been approved and activated.",
            $deliveries,
        );
    }

    /**
     * Send the welcome email and invoice again, to whoever has not received
     * them. For after the mail settings have been fixed.
     */
    public function resendEmails(Subscription $subscription, SubscriptionEmails $emails): RedirectResponse
    {
        abort_unless($subscription->status === SubscriptionStatus::Active, 409, 'Only an approved subscription has emails to send.');

        return $this->withEmailOutcome(back(), "Emails for {$subscription->school->name}:", $emails->approved($subscription, onlyUndelivered: true), resend: true);
    }

    /**
     * The same, for an approved top-up.
     */
    public function resendTopUpEmails(SubscriptionTopUp $topUp, SubscriptionEmails $emails): RedirectResponse
    {
        abort_unless($topUp->status === SubscriptionTopUpStatus::Approved, 409, 'Only an approved top-up has emails to send.');

        return $this->withEmailOutcome(back(), "Top-up emails for {$topUp->subscription->school->name}:", $emails->topUpApproved($topUp, onlyUndelivered: true), resend: true);
    }

    public function reject(RejectSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $this->rejectOne($subscription, $request->validated('reason'));

        return back()->with('status', "Subscription for {$subscription->school->name} has been rejected.");
    }

    public function bulkApprove(BulkSubscriptionRequest $request): RedirectResponse
    {
        $subscriptions = Subscription::whereIn('id', $request->validated('subscription_ids'))
            ->where('status', SubscriptionStatus::PendingVerification)
            ->get();

        $deliveries = $subscriptions->flatMap(fn (Subscription $subscription) => $this->activate($subscription));

        return $this->withEmailOutcome(back(), "{$subscriptions->count()} subscription(s) approved and activated.", $deliveries);
    }

    public function bulkReject(BulkSubscriptionRequest $request): RedirectResponse
    {
        $subscriptions = Subscription::whereIn('id', $request->validated('subscription_ids'))
            ->where('status', SubscriptionStatus::PendingVerification)
            ->get();

        $subscriptions->each(fn (Subscription $subscription) => $this->rejectOne($subscription, $request->validated('reason')));

        return back()->with('status', "{$subscriptions->count()} subscription(s) rejected.");
    }

    /**
     * Approve a student-licence top-up, allocating the number of licences the
     * Super Admin has entered after checking the receipt.
     *
     * The figure comes from the request, never from the top-up row. What the
     * school asked for is a request; what is allocated is a decision, and only
     * a Super Admin makes it.
     */
    public function approveTopUp(ApproveTopUpRequest $request, SubscriptionTopUp $topUp): RedirectResponse
    {
        // Approving twice would add the licences twice. Route model binding
        // happily re-resolves an already-approved top-up, so a double-click or
        // a replayed request has to be refused here.
        abort_unless($topUp->status === SubscriptionTopUpStatus::PendingVerification, 409, 'This top-up has already been reviewed.');

        $approved = (int) $request->validated('approved_students_count');

        $deliveries = $this->activateTopUp($topUp, $approved, $request->validated('notes'));

        return $this->withEmailOutcome(
            back(),
            "Allocated {$approved} student licence(s) to {$topUp->subscription->school->name}.",
            $deliveries,
        );
    }

    public function rejectTopUp(RejectSubscriptionRequest $request, SubscriptionTopUp $topUp): RedirectResponse
    {
        $this->rejectTopUpOne($topUp, $request->validated('reason'));

        return back()->with('status', "Top-up for {$topUp->subscription->school->name} has been rejected.");
    }

    public function export(Request $request): StreamedResponse
    {
        $subscriptions = $this->filteredQuery($request)->get();

        $filename = 'subscriptions-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($subscriptions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['School', 'Plan', 'Billing Cycle', 'Amount', 'Payment Method', 'Status', 'Reference', 'Requested On']);

            foreach ($subscriptions as $subscription) {
                fputcsv($handle, [
                    $subscription->school->name,
                    $subscription->plan->name,
                    $subscription->billing_cycle->label(),
                    $subscription->amount,
                    $subscription->latestPayment?->method?->label() ?? 'N/A',
                    $subscription->status->label(),
                    $subscription->reference,
                    $subscription->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filteredQuery(Request $request): Builder
    {
        $tab = $request->query('tab', 'pending');

        $query = Subscription::query()->with(['school', 'plan', 'latestPayment']);

        match ($tab) {
            'active' => $query->where('status', SubscriptionStatus::Active),
            'expired' => $query->where('status', SubscriptionStatus::Expired),
            'all' => null,
            default => $query->where('status', SubscriptionStatus::PendingVerification),
        };

        if ($search = $request->query('search')) {
            $query->whereHas('school', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ($planId = $request->query('plan_id')) {
            $query->where('plan_id', $planId);
        }

        if ($billingCycle = $request->query('billing_cycle')) {
            $query->where('billing_cycle', $billingCycle);
        }

        if ($paymentMethod = $request->query('payment_method')) {
            $query->whereHas('latestPayment', fn ($q) => $q->where('method', $paymentMethod));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $query->latest();
    }

    private function filteredTopUpQuery(Request $request): Builder
    {
        $query = SubscriptionTopUp::query()->with(['subscription.school', 'subscription.plan']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        } else {
            $query->where('status', SubscriptionTopUpStatus::PendingVerification);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('subscription.school', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        return $query->latest();
    }

    private function autoActivateEligibleQuery(): Builder
    {
        return Subscription::where('status', SubscriptionStatus::PendingVerification)
            ->whereHas('school.subscriptions', fn ($q) => $q->where('status', SubscriptionStatus::Active));
    }

    /**
     * Tell the Super Admin exactly what happened to the emails.
     *
     * A failure is never folded into a green message. The approval itself has
     * been saved, and saying so is true, so the page says both: approved, and
     * which emails did not go and why, with a button to send them again.
     *
     * @param  Collection<int, EmailDelivery>  $deliveries
     */
    private function withEmailOutcome(RedirectResponse $response, string $done, Collection $deliveries, bool $resend = false): RedirectResponse
    {
        $sent = $deliveries->filter->wasSent();
        $failure = TransactionalMailer::failureSummary($deliveries);

        if ($deliveries->isEmpty() && $resend) {
            return $response->with('status', $done.' every email had already been delivered, so nothing was sent again.');
        }

        $status = $sent->isEmpty()
            ? $done
            : $done.' '.$sent->count().' email(s) sent to '.$sent->pluck('recipient')->unique()->implode(', ').'.';

        $response->with('status', $status);

        if ($failure !== null) {
            $response->with('email_error', $failure.' Fix the mail settings, then use "Resend emails".');
        }

        return $response;
    }

    /**
     * @return Collection<int, EmailDelivery>
     */
    private function activate(Subscription $subscription): Collection
    {
        DB::transaction(function () use ($subscription) {
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'starts_at' => $subscription->starts_at ?? now(),
                'ends_at' => $subscription->ends_at ?? $this->calculateEndDate($subscription->billing_cycle),
            ]);

            $subscription->latestPayment?->update([
                'status' => PaymentStatus::Verified,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);
        });

        AuditLog::record('subscription.approved', "Approved subscription for {$subscription->school->name}.", $subscription);

        // The welcome email and the paid invoice, sent now, after the approval
        // is saved, and the only place they are sent from. See
        // App\Services\Mail\SubscriptionEmails.
        return app(SubscriptionEmails::class)->approved($subscription);
    }

    private function rejectOne(Subscription $subscription, ?string $reason): void
    {
        DB::transaction(function () use ($subscription, $reason) {
            $subscription->update(['status' => SubscriptionStatus::Rejected]);

            $subscription->latestPayment?->update([
                'status' => PaymentStatus::Rejected,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'notes' => $reason,
            ]);
        });

        $subscription->school->users()->each(
            fn ($user) => $user->notify(new SubscriptionRejectedNotification($subscription, $reason))
        );

        AuditLog::record('subscription.rejected', "Rejected subscription for {$subscription->school->name}.", $subscription);
    }

    /**
     * Apply an approved top-up.
     *
     * $approvedStudentsCount is the Super Admin's figure and is what the
     * subscription grows by. The school's requested figure stays on the row as
     * `additional_students_count` so the two can be compared later.
     */
    /**
     * @return Collection<int, EmailDelivery>
     */
    private function activateTopUp(SubscriptionTopUp $topUp, int $approvedStudentsCount, ?string $notes = null): Collection
    {
        DB::transaction(function () use ($topUp, $approvedStudentsCount, $notes) {
            // Locked for the duration: two Super Admins approving different
            // top-ups for the same school at the same moment would otherwise
            // both read the same starting figure and one increase would vanish.
            $subscription = $topUp->subscription()->lockForUpdate()->first();

            $previous = (int) $subscription->students_count;
            $new = $previous + $approvedStudentsCount;

            $subscription->update([
                'students_count' => $new,
                'amount' => $subscription->amount + $topUp->additional_amount,
            ]);

            $topUp->update([
                'status' => SubscriptionTopUpStatus::Approved,
                'approved_students_count' => $approvedStudentsCount,
                'previous_students_count' => $previous,
                'new_students_count' => $new,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'notes' => $notes ?? $topUp->notes,
            ]);
        });

        $topUp->refresh();

        AuditLog::record(
            'subscription.topup.approved',
            sprintf(
                'Allocated %d student licence(s) to %s (requested %d). Allocation %d -> %d.',
                $topUp->approved_students_count,
                $topUp->subscription->school->name,
                $topUp->additional_students_count,
                $topUp->previous_students_count,
                $topUp->new_students_count,
            ),
            $topUp,
        );

        // The top-up confirmation and the paid invoice for it.
        return app(SubscriptionEmails::class)->topUpApproved($topUp);
    }

    private function rejectTopUpOne(SubscriptionTopUp $topUp, ?string $reason): void
    {
        $topUp->update([
            'status' => SubscriptionTopUpStatus::Rejected,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'notes' => $reason,
        ]);

        $topUp->subscription->school->users()->each(
            fn ($user) => $user->notify(new SubscriptionTopUpRejectedNotification($topUp, $reason))
        );

        AuditLog::record('subscription.topup.rejected', "Rejected a {$topUp->additional_students_count}-student top-up for {$topUp->subscription->school->name}.", $topUp);
    }

    private function calculateEndDate(BillingCycle $billingCycle): Carbon
    {
        return match ($billingCycle) {
            BillingCycle::Monthly => now()->addMonth(),
            BillingCycle::PerTerm, BillingCycle::PerStudentPerTerm => now()->addMonths(4),
        };
    }
}
