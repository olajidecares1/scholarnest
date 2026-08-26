@php
    $deltaPercent = $totalViewsPrevious30Days > 0
        ? round((($totalViews30Days - $totalViewsPrevious30Days) / $totalViewsPrevious30Days) * 100, 1)
        : ($totalViews30Days > 0 ? 100.0 : 0.0);
@endphp

<x-super-admin-layout page-title="Analytics" page-subtitle="Monitor visitor traffic across the platform.">
    <div class="space-y-6">
        <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Page Views (Last 30 Days)</p>
            <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($totalViews30Days) }}</p>
            <p class="mt-1 text-xs font-semibold {{ $deltaPercent >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ $deltaPercent >= 0 ? '▲' : '▼' }} {{ number_format(abs($deltaPercent), 1) }}%
                <span class="font-normal text-gray-400">vs prior 30 days</span>
            </p>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Visitor Timeline</h2>
            <p class="field-hint">Page views over the last 14 days</p>
            <div
                class="mt-3"
                x-data
                x-init="new ApexCharts($el, {
                    chart: { type: 'area', height: 260, toolbar: { show: false } },
                    series: [{ name: 'Views', data: @js($timeline['counts']) }],
                    xaxis: { categories: @js($timeline['labels']) },
                    stroke: { curve: 'smooth', width: 2 },
                    colors: ['#1877f2'],
                    dataLabels: { enabled: false },
                    fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
                    grid: { borderColor: 'rgba(148, 163, 184, 0.2)' },
                }).render()"
            ></div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Traffic Sources</h2>
                <p class="field-hint">Where visitors came from, last 30 days</p>
                <div
                    class="mt-3"
                    x-data
                    x-init="new ApexCharts($el, {
                        chart: { type: 'donut', height: 260 },
                        labels: @js(array_column($trafficSources, 'label')),
                        series: @js(array_column($trafficSources, 'count')),
                        colors: ['#1877f2', '#22c55e', '#f59e0b', '#8b5cf6'],
                        legend: { position: 'bottom' },
                    }).render()"
                ></div>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Devices</h2>
                <p class="field-hint">Device breakdown, last 30 days</p>
                <div
                    class="mt-3"
                    x-data
                    x-init="new ApexCharts($el, {
                        chart: { type: 'donut', height: 260 },
                        labels: @js(array_column($devices, 'label')),
                        series: @js(array_column($devices, 'count')),
                        colors: ['#1877f2', '#f59e0b', '#22c55e'],
                        legend: { position: 'bottom' },
                    }).render()"
                ></div>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Top Pages</h2>
                <p class="field-hint">Most visited pages, last 30 days</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Page</th>
                            <th class="px-5 py-3 font-semibold">Views</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($topPages as $page)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 font-semibold text-gray-700 dark:text-gray-300">{{ $page->label() }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ number_format($page->views) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No traffic recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-super-admin-layout>
