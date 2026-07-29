<x-dashboard-layout page-title="Dashboard" :page-subtitle="'Welcome back, '.auth()->user()->name.'! Here\'s what\'s happening at '.$school->name.' today.'">
    @if (! $subscription)
        <div class="mx-auto max-w-4xl">
            <div class="rounded-[5px] border border-blue-200 bg-blue-50 p-6 text-center dark:border-blue-800 dark:bg-blue-900/20 lg:rounded-[10px]">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-[5px] bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 lg:rounded-[10px]">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5" />
                        <path d="M3 10h18" stroke="currentColor" stroke-width="1.5" />
                    </svg>
                </span>
                <h2 class="mt-3 text-lg font-bold text-gray-900 dark:text-white">You don&rsquo;t have an active subscription yet</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Choose a plan to unlock EduNest for {{ $school->name }}.</p>
                <a
                    href="{{ route('subscriptions.choose-plan') }}"
                    class="mt-4 inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-md shadow-blue-600/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-lg"
                >
                    Choose a Plan
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
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
                    ['key' => 'students', 'route' => 'students.index', 'badge' => 'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400', 'stroke' => '#8b5cf6', 'icon' => 'M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7', 'extra' => '<circle cx="10.5" cy="9" r="3.25" stroke="currentColor" stroke-width="1.75" />', 'tooltip' => 'Total number of students enrolled at your school, including inactive records.'],
                    ['key' => 'staff', 'route' => 'staff.index', 'badge' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400', 'stroke' => '#1877f2', 'icon' => 'M5 6.5a1.5 1.5 0 011.5-1.5h11A1.5 1.5 0 0119 6.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 015 17.5v-11z', 'extra' => '<circle cx="12" cy="10.5" r="2.25" stroke="currentColor" stroke-width="1.6" /><path d="M8.5 16c.7-1.8 2-2.5 3.5-2.5s2.8.7 3.5 2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />', 'tooltip' => 'Total number of teaching and non-teaching staff registered at your school.'],
                    ['key' => 'attendance', 'route' => 'attendance.index', 'badge' => 'bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400', 'stroke' => '#22c55e', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />', 'tooltip' => 'Share of attendance records marked Present or Late so far this week, across all classes.'],
                    ['key' => 'termAverage', 'route' => 'examinations.index', 'badge' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400', 'stroke' => '#f59e0b', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />', 'tooltip' => 'Average percentage score across every graded subject in this term\'s examinations.'],
                    ['key' => 'outstandingFees', 'route' => 'finance.invoices.index', 'badge' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400', 'stroke' => '#ef4444', 'icon' => 'M4 7.5h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1v-9a1 1 0 011-1z', 'extra' => '<circle cx="16.5" cy="13" r="1.5" stroke="currentColor" stroke-width="1.5" />', 'tooltip' => 'Total unpaid balance across every invoice — the amount still owed by students.'],
                ] as $card)
                    @php $data = $statCards[$card['key']]; @endphp
                    <a
                        href="{{ route($card['route']) }}"
                        class="group rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:border-blue-200 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:hover:border-blue-800 lg:rounded-[10px]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1">
                                    <p class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">{{ $data['label'] }}</p>
                                    <span class="group/tip relative hidden shrink-0 sm:inline-flex">
                                        <svg class="h-3.5 w-3.5 text-gray-300 transition-colors duration-150 hover:text-gray-400 dark:text-gray-600 dark:hover:text-gray-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                                            <path d="M12 11v5M12 8h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                                        </svg>
                                        <span class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 w-48 -translate-x-1/2 rounded-[8px] bg-gray-900 px-3 py-2 text-xs font-medium normal-case leading-snug text-white opacity-0 shadow-lg transition-opacity duration-200 group-hover/tip:opacity-100 dark:bg-gray-700">
                                            {{ $card['tooltip'] }}
                                            <span class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-gray-900 dark:border-t-gray-700"></span>
                                        </span>
                                    </span>
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
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    {!! $card['extra'] !!}
                                    <path d="{{ $card['icon'] }}" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
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
                                <p class="text-xs text-gray-500 dark:text-gray-400">Daily attendance so far this week</p>
                            </div>
                            <span class="group/tip relative inline-flex shrink-0">
                                <svg class="h-3.5 w-3.5 text-gray-300 transition-colors duration-150 hover:text-gray-400 dark:text-gray-600 dark:hover:text-gray-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                                    <path d="M12 11v5M12 8h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                                </svg>
                                <span class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 w-52 -translate-x-1/2 rounded-[8px] bg-gray-900 px-3 py-2 text-xs font-medium normal-case leading-snug text-white opacity-0 shadow-lg transition-opacity duration-200 group-hover/tip:opacity-100 dark:bg-gray-700">
                                    A student counts as present for a day if they're marked Present or Late on at least one attendance record.
                                    <span class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-gray-900 dark:border-t-gray-700"></span>
                                </span>
                            </span>
                        </div>
                        <span class="rounded-[8px] border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-500 dark:border-gray-700 dark:text-gray-400">This Week</span>
                    </div>

                    @if (array_sum($attendanceOverview['percentages']) > 0)
                        <div
                            class="mt-3"
                            x-data
                            x-init="new ApexCharts($el, {
                                chart: { type: 'line', height: 260, toolbar: { show: false } },
                                series: [{ name: 'Attendance', data: @js($attendanceOverview['percentages']) }],
                                xaxis: { categories: @js($attendanceOverview['days']) },
                                stroke: { curve: 'smooth', width: 3 },
                                colors: ['#1877f2'],
                                dataLabels: { enabled: false },
                                yaxis: { min: 0, max: 100, labels: { formatter: (val) => Math.round(val) + '%' } },
                                tooltip: { y: { formatter: (val) => Math.round(val) + '%' } },
                                grid: { borderColor: 'rgba(148, 163, 184, 0.2)' },
                                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
                            }).render()"
                        ></div>
                    @else
                        <div class="mt-4 flex h-56 items-center justify-center rounded-[5px] bg-gray-50 dark:bg-gray-900 lg:rounded-[10px]">
                            <p class="text-sm font-medium text-gray-400 dark:text-gray-500">No attendance has been recorded yet this week.</p>
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
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $notification->data['body'] ?? '' }}</span>
                                    <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $notification->created_at->diffForHumans() }}</span>
                                </span>
                            </a>
                        @empty
                            <p class="px-5 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No notifications yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Performance donut + Upcoming Events --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <div class="flex items-center gap-1.5">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Students Performance</h2>
                        <span class="group/tip relative inline-flex shrink-0">
                            <svg class="h-3.5 w-3.5 text-gray-300 transition-colors duration-150 hover:text-gray-400 dark:text-gray-600 dark:hover:text-gray-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                                <path d="M12 11v5M12 8h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                            </svg>
                            <span class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 w-52 -translate-x-1/2 rounded-[8px] bg-gray-900 px-3 py-2 text-xs font-medium normal-case leading-snug text-white opacity-0 shadow-lg transition-opacity duration-200 group-hover/tip:opacity-100 dark:bg-gray-700">
                                Each student's average score across all graded subjects this term, grouped into four performance bands.
                                <span class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-gray-900 dark:border-t-gray-700"></span>
                            </span>
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Average exam score per student, this term</p>

                    @if ($performanceBreakdown['total'] > 0)
                        <div
                            class="mt-3"
                            x-data
                            x-init="new ApexCharts($el, {
                                chart: { type: 'donut', height: 260 },
                                labels: ['Excellent (80-100%)', 'Very Good (60-79%)', 'Average (40-59%)', 'Below Average (<40%)'],
                                series: @js([
                                    $performanceBreakdown['excellent'],
                                    $performanceBreakdown['veryGood'],
                                    $performanceBreakdown['average'],
                                    $performanceBreakdown['belowAverage'],
                                ]),
                                colors: ['#22c55e', '#1877f2', '#f59e0b', '#ef4444'],
                                legend: { position: 'bottom', fontSize: '12px' },
                                plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Students', color: '#111827' } } } } },
                            }).render()"
                        ></div>
                    @else
                        <div class="mt-4 flex h-56 flex-col items-center justify-center rounded-[5px] bg-gray-50 text-center dark:bg-gray-900 lg:rounded-[10px]">
                            <p class="text-sm font-medium text-gray-400 dark:text-gray-500">No exam scores recorded yet this term.</p>
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
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $event->starts_at->format('M j, Y') }}@if (! $event->is_all_day) &middot; {{ $event->starts_at->format('g:ia') }} @endif</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $event->audience->label() }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-6 flex flex-col items-center justify-center py-6 text-center">
                            <svg class="h-8 w-8 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M3.5 9.5h17M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                            <p class="mt-2 text-sm font-medium text-gray-400 dark:text-gray-500">No upcoming events on the calendar.</p>
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
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $announcement->title }}</p>
                                    <p class="mt-0.5 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ $announcement->body }}</p>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $announcement->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No announcements yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Recent Activities</h2>
                    <div class="mt-4 space-y-4">
                        @forelse ($recentActivities as $activity)
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $activity['color'] }}">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        @if ($activity['icon'] === 'student')
                                            <circle cx="10.5" cy="9" r="3.25" stroke="currentColor" stroke-width="1.75" /><path d="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                        @elseif ($activity['icon'] === 'payment')
                                            <path d="M4 7.5h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1v-9a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /><circle cx="16.5" cy="13" r="1.5" stroke="currentColor" stroke-width="1.5" />
                                        @else
                                            <path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /><path d="M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                        @endif
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-800 dark:text-gray-100">{{ $activity['description'] }}</p>
                                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $activity['timestamp']->diffForHumans() }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No recent activity yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Quick Actions</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ([
                        ['route' => 'students.index', 'label' => 'Add Student', 'icon' => 'M12 4.5v15M4.5 12h15'],
                        ['route' => 'staff.index', 'label' => 'Add Teacher', 'icon' => 'M4.5 19.5c.6-2.6 2.7-4.5 5.5-4.5s4.9 1.9 5.5 4.5', 'extra' => '<circle cx="10" cy="8.5" r="3" stroke="currentColor" stroke-width="1.75" /><path d="M18 9v5M20.5 11.5h-5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />'],
                        ['route' => 'attendance.index', 'label' => 'Mark Attendance', 'icon' => 'M9 12.5l2 2 4-4.2', 'extra' => '<rect x="4.5" y="4.5" width="15" height="15" rx="2" stroke="currentColor" stroke-width="1.6" />'],
                        ['route' => 'examinations.index', 'label' => 'Create Exam', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                        ['route' => 'communications.index', 'label' => 'Send Notice', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                        ['route' => 'reports.summary', 'label' => 'Generate Report', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                    ] as $action)
                        <a
                            href="{{ route($action['route']) }}"
                            class="group flex flex-col items-center gap-2 rounded-[8px] border border-gray-200 p-4 text-center transition-all duration-300 ease-out hover:-translate-y-1 hover:border-blue-300 hover:bg-blue-50 hover:shadow-md dark:border-gray-700 dark:hover:bg-gray-700/50"
                        >
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-blue-600 transition-transform duration-300 ease-out group-hover:scale-110 group-hover:rotate-6 dark:bg-blue-900/30 dark:text-blue-400">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    {!! $action['extra'] ?? '' !!}
                                    <path d="{{ $action['icon'] }}" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $action['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-dashboard-layout>
