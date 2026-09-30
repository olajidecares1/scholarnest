<x-dashboard-layout page-title="Dashboard" :page-subtitle="'Welcome back, '.auth()->user()->name.'! Here\'s what\'s happening at '.$school->name.' today.'">
    @if (! $subscription)
        <div class="mx-auto max-w-4xl">
            <div class="rounded-[5px] border border-blue-200 bg-blue-50 p-6 text-center dark:border-blue-800 dark:bg-blue-900/20 lg:rounded-[10px]">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-[5px] bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 lg:rounded-[10px]">
                    <i class="fa-solid fa-credit-card text-[20px] leading-none" aria-hidden="true"></i>
                </span>
                <h2 class="mt-3 text-lg font-bold text-gray-900 dark:text-white">You don&rsquo;t have an active subscription yet</h2>
                <small class="block mt-1 text-sm text-gray-600 dark:text-gray-300">Choose a plan to unlock AkademicNest for {{ $school->name }}.</small>
                <a
                    href="{{ route('subscriptions.choose-plan') }}"
                    class="btn mt-4 inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-md shadow-blue-600/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-lg"
                >
                    Choose a Plan
                    <i class="fa-solid fa-arrow-right text-[14px] leading-none" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    @else
        <div class="space-y-6">
            @if ($subscription->status === \App\Enums\SubscriptionStatus::PendingVerification)
                <div class="rounded-[5px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300 lg:rounded-[10px]">
                    Your payment for the <strong>{{ $subscription->plan->name }}</strong> is under review. We&rsquo;ll email you once it&rsquo;s confirmed.
                    <a href="{{ route('subscriptions.confirmation', $subscription) }}" class="ml-1 font-semibold underline">View details</a>
                </div>
            @endif

            {{-- Stat cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    ['key' => 'students', 'route' => 'students.index', 'badge' => 'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400', 'stroke' => '#8b5cf6', 'icon' => 'fa-solid fa-user-graduate', 'tooltip' => 'Total number of students enrolled at your school, including inactive records.'],
                    ['key' => 'staff', 'route' => 'staff.index', 'badge' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400', 'stroke' => '#1877f2', 'icon' => 'fa-solid fa-chalkboard-user', 'tooltip' => 'Total number of teaching and non-teaching staff registered at your school.'],
                    ['key' => 'attendance', 'route' => 'attendance.index', 'badge' => 'bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400', 'stroke' => '#22c55e', 'icon' => 'fa-solid fa-clipboard-check', 'tooltip' => 'Share of attendance records marked Present or Late so far this week, across all classes.'],
                    ['key' => 'termAverage', 'route' => 'examinations.index', 'badge' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400', 'stroke' => '#f59e0b', 'icon' => 'fa-solid fa-chart-simple', 'tooltip' => 'Average percentage score across every graded subject in this term\'s examinations.'],
                    ['key' => 'outstandingFees', 'route' => 'finance.invoices.index', 'badge' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400', 'stroke' => '#ef4444', 'icon' => 'fa-solid fa-wallet', 'tooltip' => 'Total unpaid balance across every invoice, the amount still owed by students.'],
                ] as $card)
                    @php $data = $statCards[$card['key']]; @endphp
                    <a
                        href="{{ route($card['route']) }}"
                        class="group rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:border-blue-200 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:hover:border-blue-800 lg:rounded-[10px]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1">
                                    <small class="block truncate text-sm font-medium text-gray-500 dark:text-gray-400">{{ $data['label'] }}</small>
                                    <x-stat-tooltip :text="$card['tooltip']" />
                                </div>
                                <p class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white">
                                    @if (! empty($data['isCurrency']))
                                        &#8358;{{ number_format($data['total']) }}
                                    @elseif (! empty($data['isPercent']))
                                        {{ number_format($data['total'], 1) }}%
                                    @else
                                        {{ number_format($data['total']) }}
                                    @endif
                                </p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition-transform duration-300 ease-out group-hover:scale-110 group-hover:rotate-6 {{ $card['badge'] }}">
                                <i class="{{ $card['icon'] }} fa-fw text-[17px] leading-none" aria-hidden="true"></i>
                            </span>
                        </div>

                        <p class="mt-1 text-xs font-semibold {{ $data['deltaPercent'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $data['deltaPercent'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($data['deltaPercent']), 1) }}{{ ! empty($data['isPercent']) ? ' pts' : '%' }}
                            <span class="font-normal text-gray-400 dark:text-gray-500">vs {{ $card['key'] === 'attendance' ? 'last week' : 'prior period' }}</span>
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
                    </a>
                @endforeach
            </div>

            {{-- Attendance overview + Notifications --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-2 lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <div>
                                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Attendance Overview</h2>
                                <small class="field-hint">Daily attendance so far this week</small>
                            </div>
                            <x-stat-tooltip text="A student counts as present for a day if they're marked Present or Late on at least one attendance record." />
                        </div>
                        <span class="rounded-[8px] border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-500 dark:border-gray-700 dark:text-gray-400">This Week</span>
                    </div>

                    @if (array_sum($attendanceOverview['percentages']) > 0)
                        <div
                            class="mt-3"
                            x-data="{ chart: null }"
                            x-init="
                                const textColor = () => document.documentElement.classList.contains('dark') ? '#fff' : '#374151';
                                chart = new ApexCharts($el, {
                                    chart: { type: 'line', height: 260, toolbar: { show: false } },
                                    series: [{ name: 'Attendance', data: @js($attendanceOverview['percentages']) }],
                                    xaxis: { categories: @js($attendanceOverview['days']), labels: { style: { colors: textColor() } } },
                                    stroke: { curve: 'smooth', width: 3 },
                                    colors: ['#1877f2'],
                                    dataLabels: { enabled: false },
                                    yaxis: { min: 0, max: 100, labels: { formatter: (val) => Math.round(val) + '%', style: { colors: textColor() } } },
                                    tooltip: { y: { formatter: (val) => Math.round(val) + '%' } },
                                    grid: { borderColor: 'rgba(148, 163, 184, 0.2)' },
                                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
                                });
                                chart.render();
                                window.addEventListener('theme-changed', () => {
                                    chart.updateOptions({
                                        xaxis: { labels: { style: { colors: textColor() } } },
                                        yaxis: { labels: { style: { colors: textColor() } } },
                                    });
                                });
                            "
                        ></div>
                    @else
                        <div class="mt-4 flex h-56 items-center justify-center rounded-[5px] bg-gray-50 dark:bg-gray-900 lg:rounded-[10px]">
                            <small class="block text-sm font-medium text-gray-400 dark:text-gray-500">No attendance has been recorded yet this week.</small>
                        </div>
                    @endif
                </div>

                <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Recent Notifications</h2>
                    </div>
                    <div class="max-h-80 overflow-y-auto">
                        @forelse ($notifications as $notification)
                            <a
                                href="{{ route('notifications.read', $notification) }}"
                                class="flex items-start gap-2 border-b border-gray-50 px-5 py-3 text-left transition-colors duration-200 hover:bg-gray-50 dark:border-gray-700/50 dark:hover:bg-gray-700"
                            >
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ is_null($notification->read_at) ? 'bg-blue-500' : 'bg-transparent' }}"></span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $notification->data['title'] ?? 'Notification' }}</span>
                                    <small class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $notification->data['body'] ?? '' }}</small>
                                    <small class="block text-xs text-gray-400 dark:text-gray-500">{{ $notification->created_at->diffForHumans() }}</small>
                                </span>
                            </a>
                        @empty
                            <small class="block px-5 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No notifications yet.</small>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Performance donut + Upcoming Events --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <div class="flex items-center gap-1.5">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Students Performance</h2>
                        <x-stat-tooltip text="Each student's average score across all graded subjects this term, grouped into four performance bands." />
                    </div>
                    <small class="field-hint">Average exam score per student, this term</small>

                    @if ($performanceBreakdown['total'] > 0)
                        <div
                            class="mt-3"
                            x-data="{ chart: null }"
                            x-init="
                                const textColor = () => document.documentElement.classList.contains('dark') ? '#fff' : '#374151';
                                chart = new ApexCharts($el, {
                                    chart: { type: 'donut', height: 260 },
                                    labels: ['Excellent (80-100%)', 'Very Good (60-79%)', 'Average (40-59%)', 'Below Average (<40%)'],
                                    series: @js([
                                        $performanceBreakdown['excellent'],
                                        $performanceBreakdown['veryGood'],
                                        $performanceBreakdown['average'],
                                        $performanceBreakdown['belowAverage'],
                                    ]),
                                    colors: ['#22c55e', '#1877f2', '#f59e0b', '#ef4444'],
                                    legend: { position: 'bottom', fontSize: '12px', labels: { colors: textColor() } },
                                    plotOptions: { pie: { donut: { labels: { show: true, name: { color: textColor() }, value: { color: textColor() }, total: { show: true, label: 'Students', color: textColor() } } } } },
                                });
                                chart.render();
                                window.addEventListener('theme-changed', () => {
                                    chart.updateOptions({
                                        legend: { labels: { colors: textColor() } },
                                        plotOptions: { pie: { donut: { labels: { name: { color: textColor() }, value: { color: textColor() }, total: { color: textColor() } } } } },
                                    });
                                });
                            "
                        ></div>
                    @else
                        <div class="mt-4 flex h-56 flex-col items-center justify-center rounded-[5px] bg-gray-50 text-center dark:bg-gray-900 lg:rounded-[10px]">
                            <small class="block text-sm font-medium text-gray-400 dark:text-gray-500">No exam scores recorded yet this term.</small>
                        </div>
                    @endif
                </div>

                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Upcoming Events</h2>
                        <a href="{{ route('events.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View Calendar</a>
                    </div>
                    @if ($upcomingEvents->isNotEmpty())
                        <div class="mt-4 space-y-4">
                            @foreach ($upcomingEvents as $event)
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-[8px] bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400">
                                        <span class="text-[10px] font-bold leading-none">{{ $event->starts_at->format('M') }}</span>
                                        <span class="text-sm font-extrabold leading-none">{{ $event->starts_at->format('j') }}</span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $event->title }}</p>
                                        <small class="field-hint mt-0.5">{{ $event->starts_at->format('M j, Y') }}@if (! $event->is_all_day) &middot; {{ $event->starts_at->format('g:ia') }} @endif</small>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $event->audience->label() }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-6 flex flex-col items-center justify-center py-6 text-center">
                            <i class="fa-regular fa-calendar-days text-gray-300 dark:text-gray-600 text-[28px] leading-none" aria-hidden="true"></i>
                            <small class="block mt-2 text-sm font-medium text-gray-400 dark:text-gray-500">No upcoming events on the calendar.</small>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Recent Announcements + Recent Activities --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Recent Announcements</h2>
                        <a href="{{ route('communications.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View All</a>
                    </div>
                    <div class="mt-4 space-y-4">
                        @forelse ($recentAnnouncements as $announcement)
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                    <i class="fa-regular fa-comment text-[14px] leading-none" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $announcement->title }}</p>
                                    <small class="block mt-0.5 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ $announcement->body }}</small>
                                    <small class="block mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $announcement->created_at->diffForHumans() }}</small>
                                </div>
                            </div>
                        @empty
                            <small class="block py-6 text-center text-sm text-gray-500 dark:text-gray-400">No announcements yet.</small>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Recent Activities</h2>
                    <div class="mt-4 space-y-4">
                        @forelse ($recentActivities as $activity)
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $activity['color'] }}">
                                    <i class="fa-solid {{ ['student' => 'fa-user-plus', 'payment' => 'fa-money-bill-wave'][$activity['icon']] ?? 'fa-clipboard-check' }} text-[14px] leading-none" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-800 dark:text-gray-100">{{ $activity['description'] }}</p>
                                    <small class="block mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $activity['timestamp']->diffForHumans() }}</small>
                                </div>
                            </div>
                        @empty
                            <small class="block py-6 text-center text-sm text-gray-500 dark:text-gray-400">No recent activity yet.</small>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Quick Actions</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ([
                        ['route' => 'students.index', 'label' => 'Add Student', 'icon' => 'fa-solid fa-user-plus'],
                        ['route' => 'staff.index', 'label' => 'Add Teacher', 'icon' => 'fa-solid fa-chalkboard-user'],
                        ['route' => 'attendance.index', 'label' => 'Mark Attendance', 'icon' => 'fa-solid fa-clipboard-check'],
                        ['route' => 'examinations.index', 'label' => 'Create Exam', 'icon' => 'fa-solid fa-file-pen'],
                        ['route' => 'communications.index', 'label' => 'Send Notice', 'icon' => 'fa-solid fa-bullhorn'],
                        ['route' => 'reports.summary', 'label' => 'Generate Report', 'icon' => 'fa-solid fa-file-lines'],
                    ] as $action)
                        <a
                            href="{{ route($action['route']) }}"
                            class="group flex flex-col items-center gap-2 rounded-[8px] border border-gray-200 p-4 text-center transition-all duration-300 ease-out hover:-translate-y-1 hover:border-blue-300 hover:bg-blue-50 hover:shadow-md dark:border-gray-700 dark:hover:bg-gray-700/50"
                        >
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-blue-600 transition-transform duration-300 ease-out group-hover:scale-110 group-hover:rotate-6 dark:bg-blue-900/30 dark:text-blue-400">
                                <i class="{{ $action['icon'] }} fa-fw text-[17px] leading-none" aria-hidden="true"></i>
                            </span>
                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $action['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-dashboard-layout>
