<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\BillingCycle;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\BulkSubscriptionRequest;
use App\Http\Requests\SuperAdmin\RejectSubscriptionRequest;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SubscriptionApprovedNotification;
use App\Notifications\SubscriptionRejectedNotification;
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
        $subscriptions = $this->filteredQuery($request)->paginate(7)->withQueryString();

        $autoActivateEligibleIds = $this->autoActivateEligibleQuery()->pluck('id');

        return view('super-admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'tab' => $request->query('tab', 'pending'),
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

    private function calculateEndDate(BillingCycle $billingCycle): Carbon
    {
        return match ($billingCycle) {
            BillingCycle::Monthly => now()->addMonth(),
            BillingCycle::PerTerm, BillingCycle::PerStudentPerTerm => now()->addMonths(4),
        };
    }
}
