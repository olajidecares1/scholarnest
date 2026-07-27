<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now = CarbonImmutable::now();

        return view('super-admin.dashboard', [
            'statCards' => [
                'schools' => $this->countCard(
                    label: 'Total Schools',
                    total: School::count(),
                    query: fn ($start, $end) => School::whereBetween('created_at', [$start, $end])->count(),
                    now: $now,
                ),
                'activeSubscriptions' => $this->countCard(
                    label: 'Active Subscriptions',
                    total: Subscription::where('status', SubscriptionStatus::Active)->count(),
                    query: fn ($start, $end) => Subscription::where('status', SubscriptionStatus::Active)
                        ->whereBetween('created_at', [$start, $end])->count(),
                    now: $now,
                ),
                'users' => $this->countCard(
                    label: 'School Admins',
                    total: User::where('role', UserRole::SchoolAdmin)->count(),
                    query: fn ($start, $end) => User::where('role', UserRole::SchoolAdmin)
                        ->whereBetween('created_at', [$start, $end])->count(),
                    now: $now,
                ),
                'monthlyRevenue' => $this->sumCard(
                    label: 'Revenue This Month',
                    total: (float) Payment::where('status', PaymentStatus::Verified)
                        ->whereBetween('verified_at', [$now->startOfMonth(), $now])->sum('amount'),
                    query: fn ($start, $end) => (float) Payment::where('status', PaymentStatus::Verified)
                        ->whereBetween('verified_at', [$start, $end])->sum('amount'),
                    now: $now,
                ),
                'ytdRevenue' => $this->ytdRevenueCard($now),
            ],
            'subscriptionOverview' => $this->subscriptionOverview(),
            'revenueOverview' => $this->revenueOverview($now),
            'schoolsByStatus' => $this->schoolsByStatus(),
            'recentSchools' => $this->recentSchools(),
            'expiryAlerts' => $this->expiryAlerts($now),
            'notifications' => auth()->user()->notifications()->latest()->take(6)->get(),
        ]);
    }

    /**
     * @param  \Closure(CarbonImmutable, CarbonImmutable): int  $query
     * @return array{label: string, total: int, deltaPercent: float, sparkline: list<int>}
     */
    private function countCard(string $label, int $total, \Closure $query, CarbonImmutable $now): array
    {
        return [
            'label' => $label,
            'total' => $total,
            ...$this->trend($query, $now),
        ];
    }

    /**
     * @param  \Closure(CarbonImmutable, CarbonImmutable): float  $query
     * @return array{label: string, total: float, deltaPercent: float, sparkline: list<float>}
     */
    private function sumCard(string $label, float $total, \Closure $query, CarbonImmutable $now): array
    {
        return [
            'label' => $label,
            'total' => $total,
            ...$this->trend($query, $now),
        ];
    }

    /**
     * @param  \Closure(CarbonImmutable, CarbonImmutable): (int|float)  $query
     * @return array{deltaPercent: float, sparkline: list<int|float>}
     */
    private function trend(\Closure $query, CarbonImmutable $now): array
    {
        $current = $query($now->subDays(30), $now);
        $previous = $query($now->subDays(60), $now->subDays(30));

        $deltaPercent = match (true) {
            $previous > 0 => round((($current - $previous) / $previous) * 100, 1),
            $current > 0 => 100.0,
            default => 0.0,
        };

        $sparkline = collect(range(6, 0))
            ->map(fn (int $daysAgo) => $query(
                $now->subDays($daysAgo)->startOfDay(),
                $now->subDays($daysAgo)->endOfDay(),
            ))
            ->all();

        return [
            'deltaPercent' => $deltaPercent,
            'sparkline' => $sparkline,
        ];
    }

    /**
     * @return array{label: string, total: float, deltaPercent: float, sparkline: list<float>}
     */
    private function ytdRevenueCard(CarbonImmutable $now): array
    {
        $ytd = (float) Payment::where('status', PaymentStatus::Verified)
            ->whereBetween('verified_at', [$now->startOfYear(), $now])->sum('amount');

        $lastYtd = (float) Payment::where('status', PaymentStatus::Verified)
            ->whereBetween('verified_at', [$now->subYear()->startOfYear(), $now->subYear()])->sum('amount');

        $deltaPercent = match (true) {
            $lastYtd > 0 => round((($ytd - $lastYtd) / $lastYtd) * 100, 1),
            $ytd > 0 => 100.0,
            default => 0.0,
        };

        $sparkline = collect(range(0, $now->month - 1))
            ->map(function (int $monthsAgo) use ($now) {
                $month = $now->subMonths($monthsAgo);

                return (float) Payment::where('status', PaymentStatus::Verified)
                    ->whereBetween('verified_at', [$month->startOfMonth(), $month->endOfMonth()])
                    ->sum('amount');
            })
            ->reverse()
            ->values()
            ->all();

        return [
            'label' => 'Revenue Year to Date',
            'total' => $ytd,
            'deltaPercent' => $deltaPercent,
            'sparkline' => $sparkline,
        ];
    }

    /**
     * @return list<array{label: string, count: int, percent: float}>
     */
    private function subscriptionOverview(): array
    {
        $subscriptions = Subscription::where('status', SubscriptionStatus::Active)->with('plan')->get();
        $total = $subscriptions->count();

        return $subscriptions
            ->groupBy(fn (Subscription $subscription) => $subscription->plan->name)
            ->map(fn (Collection $group, string $planName) => [
                'label' => $planName,
                'count' => $group->count(),
                'percent' => $total > 0 ? round(($group->count() / $total) * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{months: list<string>, amounts: list<float>}
     */
    private function revenueOverview(CarbonImmutable $now): array
    {
        $months = collect(range(1, 12))->map(fn (int $month) => $now->startOfYear()->addMonths($month - 1));

        return [
            'months' => $months->map(fn (CarbonImmutable $month) => $month->format('M'))->all(),
            'amounts' => $months->map(fn (CarbonImmutable $month) => (float) Payment::where('status', PaymentStatus::Verified)
                ->whereBetween('verified_at', [$month->startOfMonth(), $month->endOfMonth()])
                ->sum('amount'))->all(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function schoolsByStatus(): array
    {
        $counts = ['active' => 0, 'expiring_soon' => 0, 'expired' => 0, 'suspended' => 0];

        School::with('activeSubscription')->get()->each(function (School $school) use (&$counts) {
            $counts[$this->classifySchoolStatus($school)]++;
        });

        return $counts;
    }

    private function classifySchoolStatus(School $school): string
    {
        if (! $school->is_active) {
            return 'suspended';
        }

        $subscription = $school->activeSubscription;

        if ($subscription === null) {
            return 'expired';
        }

        if ($subscription->status !== SubscriptionStatus::Active) {
            return 'active';
        }

        if ($subscription->ends_at !== null && $subscription->ends_at->lessThanOrEqualTo(now()->addDays(30))) {
            return 'expiring_soon';
        }

        return 'active';
    }

    /**
     * @return list<array{name: string, plan: string, status: string, studentsCount: int|null, joinedAt: Carbon}>
     */
    private function recentSchools(): array
    {
        return School::with(['activeSubscription.plan'])
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (School $school) => [
                'name' => $school->name,
                'plan' => $school->activeSubscription?->plan?->name ?? '—',
                'status' => $this->classifySchoolStatus($school),
                'studentsCount' => $school->activeSubscription?->students_count,
                'joinedAt' => $school->created_at,
            ])
            ->all();
    }

    /**
     * @return array{sevenDays: int, fifteenDays: int, thirtyDays: int, expired: int}
     */
    private function expiryAlerts(CarbonImmutable $now): array
    {
        $activeExpiring = fn (int $days) => Subscription::where('status', SubscriptionStatus::Active)
            ->whereBetween('ends_at', [$now, $now->addDays($days)])
            ->count();

        return [
            'sevenDays' => $activeExpiring(7),
            'fifteenDays' => $activeExpiring(15),
            'thirtyDays' => $activeExpiring(30),
            'expired' => Subscription::where('status', SubscriptionStatus::Expired)->count(),
        ];
    }
}
