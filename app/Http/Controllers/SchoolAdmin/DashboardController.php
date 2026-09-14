<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\ExaminationScore;
use App\Models\FeePayment;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Services\SchoolDashboardMetrics;
use App\Services\StudentLicenceAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StudentLicenceAllocation $licences,
        private readonly SchoolDashboardMetrics $metrics,
    ) {}

    public function index(Request $request): View
    {
        $school = $request->user()->school;
        [$accountState, $subscription] = $this->resolveAccountState($school);

        if ($accountState !== 'active') {
            return view('dashboard', [
                'school' => $school,
                'subscription' => $subscription,
                'accountState' => $accountState,
                'capacity' => null,
            ]);
        }

        return view('dashboard', [
            'school' => $school,
            'subscription' => $subscription,
            'accountState' => 'active',

            // Null for the flat-fee plans, which are uncapped, the card is
            // not drawn at all rather than drawn with a meaningless ceiling.
            // A Basic school only reaches a non-null figure once a Super Admin
            // has approved its payment, which is what makes this card the
            // school's confirmation that it is live.
            'capacity' => $this->licences->summary($school),

            // The dashboard is a dashboard now rather than a second menu.
            // Every figure below is read from the database, see
            // App\Services\SchoolDashboardMetrics.
            'headline' => $this->metrics->headline($school),
            'attendanceSummary' => $this->metrics->attendance($school),
            'resultsSummary' => $this->metrics->results($school),
            'pendingActions' => $this->metrics->pending($school),
            'recentActivity' => $this->metrics->recentActivity($school),

            'moduleCounts' => [
                'students' => $school->students()->count(),
                'staff' => $school->staff()->count(),
                'outstandingFees' => '₦'.number_format((float) $school->invoices()->with('payments')->get()->sum(fn ($invoice) => max($invoice->balance(), 0))),
                'attendanceToday' => (int) round($this->attendancePercent($school, CarbonImmutable::now()->startOfDay(), CarbonImmutable::now()->endOfDay())),
            ],
        ]);
    }

    public function overview(Request $request): View
    {
        $school = $request->user()->school;

        if (! $school->hasActiveSubscription()) {
            return view('school-admin.overview.index', ['school' => $school, 'subscription' => null]);
        }

        $subscription = $school->activeSubscription;

        $now = CarbonImmutable::now();
        $weekStart = $now->startOfWeek();
        $weekEnd = $now->endOfWeek();

        return view('school-admin.overview.index', [
            'school' => $school,
            'subscription' => $subscription,
            'statCards' => [
                'students' => $this->countCard(
                    label: 'Total Students',
                    total: $school->students()->count(),
                    query: fn ($start, $end) => $school->students()->whereBetween('created_at', [$start, $end])->count(),
                    now: $now,
                ),
                'staff' => $this->countCard(
                    label: 'Teachers & Staff',
                    total: $school->staff()->count(),
                    query: fn ($start, $end) => $school->staff()->whereBetween('created_at', [$start, $end])->count(),
                    now: $now,
                ),
                'attendance' => $this->attendanceCard($school, $now),
                'termAverage' => $this->termAverageCard($school),
                'outstandingFees' => $this->outstandingFeesCard($school, $now),
            ],
            'attendanceOverview' => $this->attendanceOverview($school, $weekStart, $weekEnd),
            'performanceBreakdown' => $this->performanceBreakdown($school),
            'recentAnnouncements' => Announcement::latest()->take(4)->get(),
            'upcomingEvents' => $school->events()->where('starts_at', '>=', now())->orderBy('starts_at')->take(4)->get(),
            'recentActivities' => $this->recentActivities($school),
            'notifications' => $request->user()->notifications()->latest()->take(5)->get(),
            'totalClasses' => $school->schoolClasses()->count(),
            'totalAcademicLevels' => $school->academicLevels()->count(),
        ]);
    }

    /**
     * Resolves which of the dashboard's account-state banners to show.
     * activeSubscription() alone can't drive this: it's scoped to
     * Active/PendingVerification/PendingPayment, so a Rejected or Expired
     * subscription would look identical to "never subscribed" through it,
     * the school's actual latest subscription (any status) is needed to
     * tell those cases apart and explain what happened.
     *
     * @return array{0: 'suspended'|'no_subscription'|'pending'|'rejected'|'expired'|'active', 1: ?Subscription}
     */
    private function resolveAccountState(School $school): array
    {
        if (! $school->is_active) {
            return ['suspended', $school->subscriptions()->with(['plan', 'latestPayment'])->latest()->first()];
        }

        // The approved subscription decides, if there is one. Taking simply the
        // newest row meant a school that submitted a renewal was told it was
        // "awaiting activation" while the subscription it had already paid for
        // was still running, the dashboard disagreeing with the access gates
        // about the very same school.
        $approved = $school->activeSubscription()->with(['plan', 'latestPayment'])->first();

        if ($approved) {
            return ['active', $approved];
        }

        $subscription = $school->subscriptions()->with(['plan', 'latestPayment'])->latest()->first();

        if (! $subscription) {
            return ['no_subscription', null];
        }

        return match ($subscription->status) {
            // Only reachable if the school was suspended between the check
            // above and this one; kept so the match stays exhaustive.
            SubscriptionStatus::Active => ['active', $subscription],
            SubscriptionStatus::PendingVerification, SubscriptionStatus::PendingPayment => ['pending', $subscription],
            SubscriptionStatus::Rejected => ['rejected', $subscription],
            SubscriptionStatus::Expired => ['expired', $subscription],
        };
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
            'isPercent' => false,
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
     * @return array{label: string, total: float, isPercent: bool, deltaPercent: float, sparkline: list<float>}
     */
    private function attendanceCard(School $school, CarbonImmutable $now): array
    {
        $percentFor = fn ($start, $end) => $this->attendancePercent($school, $start, $end);

        $thisWeek = $percentFor($now->startOfWeek(), $now->endOfWeek());
        $lastWeek = $percentFor($now->subWeek()->startOfWeek(), $now->subWeek()->endOfWeek());

        $sparkline = collect(range(6, 0))
            ->map(fn (int $daysAgo) => $percentFor($now->subDays($daysAgo)->startOfDay(), $now->subDays($daysAgo)->endOfDay()))
            ->all();

        return [
            'label' => 'Attendance (This Week)',
            'total' => $thisWeek,
            'isPercent' => true,
            'deltaPercent' => round($thisWeek - $lastWeek, 1),
            'sparkline' => $sparkline,
        ];
    }

    private function attendancePercent(School $school, $start, $end): float
    {
        $records = $school->attendanceRecords()->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get();

        if ($records->isEmpty()) {
            return 0.0;
        }

        $present = $records->filter(fn (AttendanceRecord $record) => $record->status->isPresentForStats())->count();

        return round($present / $records->count() * 100, 1);
    }

    /**
     * @return array{label: string, total: float, isPercent: bool, deltaPercent: float, sparkline: list<float>}
     */
    private function termAverageCard(School $school): array
    {
        $scores = ExaminationScore::whereHas('subject.examination', fn ($query) => $query->where('school_id', $school->id))->with('subject')->get();

        $average = $scores->isNotEmpty() ? round($scores->avg(fn ($score) => $score->percentage()), 1) : 0.0;

        $sparkline = collect(range(6, 0))->map(function (int $daysAgo) use ($scores) {
            $day = today()->subDays($daysAgo);
            $dayScores = $scores->filter(fn ($score) => $score->created_at->isSameDay($day));

            return $dayScores->isNotEmpty() ? round($dayScores->avg(fn ($score) => $score->percentage()), 1) : 0.0;
        })->all();

        return [
            'label' => 'Term Average Score',
            'total' => $average,
            'isPercent' => true,
            'deltaPercent' => 0.0,
            'sparkline' => $sparkline,
        ];
    }

    /**
     * @return array{label: string, total: float, isPercent: bool, deltaPercent: float, sparkline: list<float>}
     */
    private function outstandingFeesCard(School $school, CarbonImmutable $now): array
    {
        $invoices = $school->invoices()->with('payments')->get();
        $outstanding = (float) $invoices->sum(fn (Invoice $invoice) => max($invoice->balance(), 0));

        $paidFor = fn ($start, $end) => (float) FeePayment::whereHas('invoice', fn ($query) => $query->where('school_id', $school->id))
            ->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        $thisMonth = $paidFor($now->startOfMonth(), $now);
        $lastMonth = $paidFor($now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth());

        $deltaPercent = match (true) {
            $lastMonth > 0 => round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1),
            $thisMonth > 0 => 100.0,
            default => 0.0,
        };

        $sparkline = collect(range(6, 0))->map(fn (int $daysAgo) => $paidFor($now->subDays($daysAgo)->startOfDay(), $now->subDays($daysAgo)->endOfDay()))->all();

        return [
            'label' => 'Outstanding Fees',
            'total' => $outstanding,
            'isPercent' => false,
            'isCurrency' => true,
            'deltaPercent' => $deltaPercent,
            'sparkline' => $sparkline,
        ];
    }

    /**
     * @return array{days: list<string>, percentages: list<float>}
     */
    private function attendanceOverview(School $school, CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        $days = collect();
        $cursor = $weekStart;

        while ($cursor->lessThanOrEqualTo($weekEnd) && $cursor->lessThanOrEqualTo(CarbonImmutable::now())) {
            $days->push($cursor);
            $cursor = $cursor->addDay();
        }

        return [
            'days' => $days->map(fn ($day) => $day->format('D'))->all(),
            'percentages' => $days->map(fn ($day) => $this->attendancePercent($school, $day->startOfDay(), $day->endOfDay()))->all(),
        ];
    }

    /**
     * @return array{excellent: int, veryGood: int, average: int, belowAverage: int, total: int}
     */
    private function performanceBreakdown(School $school): array
    {
        $scores = ExaminationScore::whereHas('subject.examination', fn ($query) => $query->where('school_id', $school->id))
            ->with('subject')
            ->get()
            ->groupBy('student_id');

        $buckets = ['excellent' => 0, 'veryGood' => 0, 'average' => 0, 'belowAverage' => 0];

        foreach ($scores as $studentScores) {
            $average = $studentScores->avg(fn ($score) => $score->percentage());

            match (true) {
                $average >= 80 => $buckets['excellent']++,
                $average >= 60 => $buckets['veryGood']++,
                $average >= 40 => $buckets['average']++,
                default => $buckets['belowAverage']++,
            };
        }

        return [...$buckets, 'total' => $scores->count()];
    }

    /**
     * @return list<array{icon: string, color: string, description: string, timestamp: Carbon}>
     */
    private function recentActivities(School $school): array
    {
        $activities = collect();

        Student::where('school_id', $school->id)->latest()->take(3)->get()->each(function (Student $student) use ($activities) {
            $activities->push([
                'icon' => 'student',
                'color' => 'text-blue-600 bg-blue-50',
                'description' => "New student {$student->fullName()} was admitted",
                'timestamp' => $student->created_at,
            ]);
        });

        FeePayment::whereHas('invoice', fn ($query) => $query->where('school_id', $school->id))
            ->with('invoice.student')
            ->latest()
            ->take(3)
            ->get()
            ->each(function (FeePayment $payment) use ($activities) {
                $studentName = $payment->invoice->student?->fullName() ?? 'a student';
                $activities->push([
                    'icon' => 'payment',
                    'color' => 'text-green-600 bg-green-50',
                    'description' => '₦'.number_format((float) $payment->amount).' payment received from '.$studentName,
                    'timestamp' => $payment->created_at,
                ]);
            });

        AttendanceRecord::where('school_id', $school->id)
            ->with('markedBy')
            ->latest()
            ->take(10)
            ->get()
            ->unique(fn (AttendanceRecord $record) => $record->marked_by.'|'.$record->date.'|'.$record->class_name)
            ->take(3)
            ->each(function (AttendanceRecord $record) use ($activities) {
                $activities->push([
                    'icon' => 'attendance',
                    'color' => 'text-purple-600 bg-purple-50',
                    'description' => ($record->markedBy?->name ?? 'Someone').' marked attendance for '.($record->class_name ?? 'a class'),
                    'timestamp' => $record->created_at,
                ]);
            });

        return $activities->sortByDesc('timestamp')->take(5)->values()->all();
    }
}
