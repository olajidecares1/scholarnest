<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\BillingCycle;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTopUpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\BulkSubscriptionRequest;
use App\Http\Requests\SuperAdmin\RejectSubscriptionRequest;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionTopUp;
use App\Notifications\SubscriptionApprovedNotification;
use App\Notifications\SubscriptionRejectedNotification;
use App\Notifications\SubscriptionTopUpApprovedNotification;
use App\Notifications\SubscriptionTopUpRejectedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function approve(Subscription $subscription): RedirectResponse
    {
        $this->activate($subscription);

        return back()->with('status', "Subscription for {$subscription->school->name} has been approved and activated.");
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

        $subscriptions->each(fn (Subscription $subscription) => $this->activate($subscription));

        return back()->with('status', "{$subscriptions->count()} subscription(s) approved and activated.");
    }

    public function bulkReject(BulkSubscriptionRequest $request): RedirectResponse
    {
        $subscriptions = Subscription::whereIn('id', $request->validated('subscription_ids'))
            ->where('status', SubscriptionStatus::PendingVerification)
            ->get();

        $subscriptions->each(fn (Subscription $subscription) => $this->rejectOne($subscription, $request->validated('reason')));

        return back()->with('status', "{$subscriptions->count()} subscription(s) rejected.");
    }

    public function approveTopUp(SubscriptionTopUp $topUp): RedirectResponse
    {
        $this->activateTopUp($topUp);

        return back()->with('status', "Top-up for {$topUp->subscription->school->name} has been approved and applied.");
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
                    $subscription->latestPayment?->method?->label() ?? '—',
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

    private function activate(Subscription $subscription): void
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

        $subscription->school->users()->each(
            fn ($user) => $user->notify(new SubscriptionApprovedNotification($subscription))
        );

        AuditLog::record('subscription.approved', "Approved subscription for {$subscription->school->name}.", $subscription);
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

    private function activateTopUp(SubscriptionTopUp $topUp): void
    {
        DB::transaction(function () use ($topUp) {
            $subscription = $topUp->subscription;

            $subscription->update([
                'students_count' => $subscription->students_count + $topUp->additional_students_count,
                'amount' => $subscription->amount + $topUp->additional_amount,
            ]);

            $topUp->update([
                'status' => SubscriptionTopUpStatus::Approved,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);
        });

        $topUp->subscription->school->users()->each(
            fn ($user) => $user->notify(new SubscriptionTopUpApprovedNotification($topUp))
        );

        AuditLog::record('subscription.topup.approved', "Approved a {$topUp->additional_students_count}-student top-up for {$topUp->subscription->school->name}.", $topUp);
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
