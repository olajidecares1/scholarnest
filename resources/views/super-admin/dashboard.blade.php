<x-super-admin-layout page-title="Dashboard" page-subtitle="Platform overview and recent activity.">
    <div class="space-y-6">
        <x-welcome-banner
            :photo-url="auth()->user()->photoUrl()"
            :initials="Str::of(auth()->user()->name)->substr(0, 1)->upper()"
            :name="auth()->user()->name"
            role-label="ScholarNest Team"
            subtitle="Here's the platform at a glance."
        />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                ['key' => 'schools', 'isCurrency' => false, 'badge' => 'bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400', 'stroke' => '#1877f2', 'icon' => 'M4 21h16M5 21V10M19 21V10M3 10l9-6 9 6M8 10v11M12 10v11M16 10v11'],
                ['key' => 'activeSubscriptions', 'isCurrency' => false, 'badge' => 'bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400', 'stroke' => '#22c55e', 'icon' => 'M3 5h18M3 5a2 2 0 00-2 2v6a2 2 0 002 2h18a2 2 0 002-2V7a2 2 0 00-2-2M8.5 12.5l2.5 2.5 5-5'],
                ['key' => 'users', 'isCurrency' => false, 'badge' => 'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400', 'stroke' => '#8b5cf6', 'icon' => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8zM22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75'],
                ['key' => 'monthlyRevenue', 'isCurrency' => true, 'badge' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400', 'stroke' => '#f59e0b', 'icon' => 'M12 8v8M9 10.5a2.5 2.5 0 012.5-1.5h1a2 2 0 010 4h-1a2 2 0 000 4h1a2.5 2.5 0 002.5-1.5M4 12a8 8 0 1116 0 8 8 0 01-16 0z'],
                ['key' => 'ytdRevenue', 'isCurrency' => true, 'badge' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400', 'stroke' => '#6366f1', 'icon' => 'M4 20V10M10 20V4M16 20v-7M20 20v-3'],
            ] as $card)
                @php $data = $statCards[$card['key']]; @endphp
                <div class="group rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:border-primary-200 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-800 lg:rounded-[10px]">
                    <div class="flex items-start justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">{{ $data['label'] }}</p>
                            <p class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white">
                                {{ $card['isCurrency'] ? '₦'.number_format($data['total']) : number_format($data['total']) }}
                            </p>
                        </div>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition-transform duration-300 ease-out group-hover:scale-110 group-hover:rotate-6 {{ $card['badge'] }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="{{ $card['icon'] }}" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>

                    <p class="mt-1 text-xs font-semibold {{ $data['deltaPercent'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $data['deltaPercent'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($data['deltaPercent']), 1) }}%
                        <span class="font-normal text-gray-400">vs prior period</span>
                    </p>

                    <div
                        class="mt-2 h-10"
                        x-data
                        x-init="new ApexCharts($el, {
                            chart: { type: 'line', height: 40, sparkline: { enabled: true } },
                            series: [{ data: @js($data['sparkline']) }],
                            stroke: { curve: 'smooth', width: 2 },
                            colors: ['{{ $card['stroke'] }}'],
                            tooltip: { enabled: false },
                        }).render()"
                    ></div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-shadow duration-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 lg:col-span-2 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Revenue Overview</h2>
                <p class="field-hint">Verified payments by month, {{ now()->year }}</p>
                <div
                    class="mt-3"
                    x-data
                    x-init="new ApexCharts($el, {
                        chart: { type: 'line', height: 280, toolbar: { show: false } },
                        series: [{ name: 'Revenue', data: @js($revenueOverview['amounts']) }],
                        xaxis: { categories: @js($revenueOverview['months']) },
                        stroke: { curve: 'smooth', width: 3 },
                        colors: ['#1877f2'],
                        dataLabels: { enabled: false },
                        yaxis: { labels: { formatter: (val) => '₦' + Math.round(val).toLocaleString() } },
                        tooltip: { y: { formatter: (val) => '₦' + Number(val).toLocaleString() } },
                        grid: { borderColor: 'rgba(148, 163, 184, 0.2)' },
                    }).render()"
                ></div>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm transition-shadow duration-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Recent Notifications</h2>
                </div>
                <div class="max-h-80 overflow-y-auto">
                    @forelse ($notifications as $notification)
                        <a
                            href="{{ route('notifications.read', $notification) }}"
                            class="flex items-start gap-2 border-b border-gray-50 px-5 py-3 text-left transition-colors duration-200 hover:bg-gray-50 dark:border-gray-700/50 dark:hover:bg-gray-700"
                        >
                            @if (is_null($notification->read_at))
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500"></span>
                            @else
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-transparent"></span>
                            @endif
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $notification->data['title'] ?? 'Notification' }}</span>
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $notification->data['body'] ?? '' }}</span>
                                <span class="block text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="px-5 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No notifications yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-shadow duration-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Subscription Overview</h2>
                <p class="field-hint">Active subscriptions by plan</p>
                @if (empty($subscriptionOverview))
                    <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active subscriptions yet.</p>
                @else
                    <div
                        class="mt-3"
                        x-data
                        x-init="new ApexCharts($el, {
                            chart: { type: 'donut', height: 260 },
                            labels: @js(array_column($subscriptionOverview, 'label')),
                            series: @js(array_column($subscriptionOverview, 'count')),
                            colors: ['#1877f2', '#22c55e', '#f59e0b', '#8b5cf6'],
                            legend: { position: 'bottom' },
                            dataLabels: { formatter: (val) => Math.round(val) + '%' },
                        }).render()"
                    ></div>
                @endif
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-shadow duration-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Schools by Status</h2>
                <p class="field-hint">Where every school currently stands</p>
                <div
                    class="mt-3"
                    x-data
                    x-init="new ApexCharts($el, {
                        chart: { type: 'donut', height: 260 },
                        labels: ['Active', 'Expiring Soon', 'Expired', 'Suspended'],
                        series: @js([
                            $schoolsByStatus['active'],
                            $schoolsByStatus['expiring_soon'],
                            $schoolsByStatus['expired'],
                            $schoolsByStatus['suspended'],
                        ]),
                        colors: ['#22c55e', '#f59e0b', '#ef4444', '#6b7280'],
                        legend: { position: 'bottom' },
                    }).render()"
                ></div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Expiring in 7 Days', 'value' => $expiryAlerts['sevenDays'], 'color' => 'text-red-600 dark:text-red-400'],
                ['label' => 'Expiring in 15 Days', 'value' => $expiryAlerts['fifteenDays'], 'color' => 'text-amber-600 dark:text-amber-400'],
                ['label' => 'Expiring in 30 Days', 'value' => $expiryAlerts['thirtyDays'], 'color' => 'text-purple-600 dark:text-purple-400'],
                ['label' => 'Already Expired', 'value' => $expiryAlerts['expired'], 'color' => 'text-gray-600 dark:text-gray-400'],
            ] as $alert)
                <a
                    href="{{ route('super-admin.subscriptions.index', ['tab' => $loop->last ? 'expired' : 'active']) }}"
                    class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:border-primary-300 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $alert['label'] }}</p>
                    <p class="mt-1 text-2xl font-extrabold {{ $alert['color'] }}">{{ number_format($alert['value']) }}</p>
                </a>
            @endforeach
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm transition-shadow duration-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Recent Schools</h2>
                <a href="{{ route('super-admin.schools.index') }}" class="group text-sm font-semibold text-primary-500 transition-colors duration-200 hover:text-primary-600">
                    View all <span class="inline-block transition-transform duration-300 ease-out group-hover:translate-x-1">&rarr;</span>
                </a>
            </div>

            @if (empty($recentSchools))
                <p class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">No schools yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">School</th>
                                <th class="px-5 py-3 font-semibold">Plan</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 font-semibold">Students</th>
                                <th class="px-5 py-3 font-semibold">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($recentSchools as $school)
                                <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $school['name'] }}</td>
                                    <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school['plan'] }}</td>
                                    <td class="px-5 py-3">
                                        @php
                                            $statusStyles = [
                                                'active' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                                'expiring_soon' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                                'expired' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                                'suspended' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                            ];
                                            $statusLabels = [
                                                'active' => 'Active',
                                                'expiring_soon' => 'Expiring Soon',
                                                'expired' => 'Expired',
                                                'suspended' => 'Suspended',
                                            ];
                                        @endphp
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusStyles[$school['status']] }}">
                                            {{ $statusLabels[$school['status']] }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school['studentsCount'] ?? '—' }}</td>
                                    <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school['joinedAt']->format('M j, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-shadow duration-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Quick Actions</h2>
            <div class="mt-3 grid grid-cols-3 gap-2.5 sm:grid-cols-4 sm:gap-3 md:grid-cols-5 lg:grid-cols-4">
                @foreach ([
                    ['route' => 'super-admin.schools.create', 'label' => 'Add School', 'icon' => 'M12 4.5v15M4.5 12h15', 'extra' => ''],
                    ['route' => 'super-admin.users.create', 'label' => 'Add Admin', 'icon' => 'M4.5 19.5c.6-2.6 2.7-4.5 5.5-4.5s4.9 1.9 5.5 4.5', 'extra' => '<circle cx="10" cy="8.5" r="3" stroke="currentColor" stroke-width="1.75" /><path d="M18 9v5M20.5 11.5h-5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />'],
                    ['route' => 'super-admin.subscriptions.index', 'label' => 'Subscriptions', 'icon' => 'M7 13.5l2.2 2.2L14 11', 'extra' => '<rect x="3.5" y="5.5" width="17" height="13" rx="2.5" stroke="currentColor" stroke-width="1.75" /><path d="M3.5 9.5h17" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />'],
                    ['route' => 'super-admin.payments.index', 'label' => 'Payments', 'icon' => 'M12 4v2.2M12 17.8V20M8.5 8.5c0-1.4 1.6-2.5 3.5-2.5s3.5 1.1 3.5 2.3c0 3.2-7 1.4-7 4.7 0 1.3 1.6 2.3 3.5 2.3s3.5-1.1 3.5-2.5', 'extra' => '<circle cx="12" cy="12" r="8.25" stroke="currentColor" stroke-width="1.6" />'],
                    ['route' => 'super-admin.communications.index', 'label' => 'Send Message', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'super-admin.reports.index', 'label' => 'Generate Report', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                    ['route' => 'super-admin.settings.index', 'label' => 'System Settings', 'icon' => 'M10.3 3.3a2 2 0 013.4 0l.5.9a2 2 0 001.6 1l1-.1a2 2 0 012.1 2.1l-.1 1a2 2 0 001 1.6l.9.5a2 2 0 010 3.4l-.9.5a2 2 0 00-1 1.6l.1 1a2 2 0 01-2.1 2.1l-1-.1a2 2 0 00-1.6 1l-.5.9a2 2 0 01-3.4 0l-.5-.9a2 2 0 00-1.6-1l-1 .1a2 2 0 01-2.1-2.1l.1-1a2 2 0 00-1-1.6l-.9-.5a2 2 0 010-3.4l.9-.5a2 2 0 001-1.6l-.1-1a2 2 0 012.1-2.1l1 .1a2 2 0 001.6-1z', 'extra' => '<circle cx="12" cy="12" r="2.75" stroke="currentColor" stroke-width="1.6" />'],
                ] as $action)
                    <a
                        href="{{ route($action['route']) }}"
                        class="group flex flex-col items-center gap-2 rounded-[5px] border border-gray-200 p-4 text-center transition-all duration-300 ease-out hover:-translate-y-1 hover:border-primary-300 hover:bg-primary-50 hover:shadow-md dark:border-gray-700 dark:hover:bg-gray-700/50 lg:rounded-[10px]"
                    >
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-50 text-primary-600 transition-transform duration-300 ease-out group-hover:scale-110 group-hover:rotate-6 dark:bg-primary-900/30 dark:text-primary-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                {!! $action['extra'] !!}
                                <path d="{{ $action['icon'] }}" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-super-admin-layout>
