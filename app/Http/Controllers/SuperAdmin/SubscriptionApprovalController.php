<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\BillingCycle;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\RejectSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SubscriptionApprovedNotification;
use App\Notifications\SubscriptionRejectedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubscriptionApprovalController extends Controller
{
    public function index(Request $request): View
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

        $subscriptions = $query->latest()->paginate(7)->withQueryString();

        $autoActivateEligibleIds = Subscription::where('status', SubscriptionStatus::PendingVerification)
            ->whereHas('school.subscriptions', fn ($q) => $q->where('status', SubscriptionStatus::Active))
            ->pluck('id');

        return view('super-admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
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
            ],
        ]);
    }

    public function approve(Subscription $subscription): RedirectResponse
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

        return back()->with('status', "Subscription for {$subscription->school->name} has been approved and activated.");
    }

    public function reject(RejectSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $reason = $request->validated('reason');

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

        return back()->with('status', "Subscription for {$subscription->school->name} has been rejected.");
    }

    private function calculateEndDate(BillingCycle $billingCycle): Carbon
    {
        return match ($billingCycle) {
            BillingCycle::Monthly => now()->addMonth(),
            BillingCycle::PerTerm, BillingCycle::PerStudentPerTerm => now()->addMonths(4),
        };
    }
}
