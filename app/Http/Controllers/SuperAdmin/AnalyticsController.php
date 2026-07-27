<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PageView;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        $now = Carbon::now();

        return view('super-admin.analytics.index', [
            'totalViews30Days' => PageView::where('viewed_at', '>=', $now->copy()->subDays(30))->count(),
            'totalViewsPrevious30Days' => PageView::whereBetween('viewed_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count(),
            'timeline' => $this->timeline($now),
            'trafficSources' => $this->breakdown('traffic_source', ['direct', 'search', 'social', 'referral'], $now),
            'devices' => $this->breakdown('device_type', ['desktop', 'mobile', 'tablet'], $now),
            'topPages' => PageView::where('viewed_at', '>=', $now->copy()->subDays(30))
                ->selectRaw('path, count(*) as views')
                ->groupBy('path')
                ->orderByDesc('views')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    private function timeline(Carbon $now): array
    {
        $days = collect(range(13, 0))->map(fn (int $daysAgo) => $now->copy()->subDays($daysAgo));

        return [
            'labels' => $days->map(fn (Carbon $day) => $day->format('M j'))->all(),
            'counts' => $days->map(fn (Carbon $day) => PageView::whereBetween('viewed_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->count())->all(),
        ];
    }

    /**
     * @param  list<string>  $values
     * @return list<array{label: string, count: int}>
     */
    private function breakdown(string $column, array $values, Carbon $now): array
    {
        return collect($values)
            ->map(fn (string $value) => [
                'label' => ucfirst($value),
                'count' => PageView::where($column, $value)->where('viewed_at', '>=', $now->copy()->subDays(30))->count(),
            ])
            ->all();
    }
}
