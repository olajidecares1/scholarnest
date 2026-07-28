@php
    $school = auth()->user()->school;
    $subscription = $school->activeSubscription;
    $recentAnnouncements = \App\Models\Announcement::latest()->take(3)->get();
    $totalStudents = $school->students()->count();
    $activeStudents = $school->students()->where('is_active', true)->count();
    $totalStaff = $school->staff()->count();
    $activeStaff = $school->staff()->where('is_active', true)->count();

    $monthRecords = $school->attendanceRecords()->whereDate('date', '>=', now()->startOfMonth())->get();
    $attendanceTotal = $monthRecords->count();
    $attendancePresent = $monthRecords->where('status', \App\Enums\AttendanceStatus::Present)->count();
    $attendanceAbsent = $monthRecords->where('status', \App\Enums\AttendanceStatus::Absent)->count();
    $attendanceLate = $monthRecords->where('status', \App\Enums\AttendanceStatus::Late)->count();
    $attendanceAverage = $attendanceTotal > 0 ? (int) round($monthRecords->filter(fn ($record) => $record->status->isPresentForStats())->count() / $attendanceTotal * 100) : null;

    $last7Records = $school->attendanceRecords()->whereDate('date', '>=', today()->subDays(6))->get()->groupBy(fn ($record) => $record->date->toDateString());
    $attendanceTrend = collect(range(6, 0))->map(function ($daysAgo) use ($last7Records) {
        $day = today()->subDays($daysAgo);
        $dayRecords = $last7Records->get($day->toDateString(), collect());
        $total = $dayRecords->count();

        return [
            'label' => $day->format('D'),
            'percent' => $total > 0 ? (int) round($dayRecords->filter(fn ($record) => $record->status->isPresentForStats())->count() / $total * 100) : 0,
        ];
    });
@endphp

<x-dashboard-layout page-title="Dashboard" :page-subtitle="'Welcome back, '.auth()->user()->name.'! Here\'s what\'s happening.'">
    @if (! $subscription)
        <div class="mx-auto max-w-4xl">
            <div class="rounded-[5px] border border-blue-200 bg-blue-50 p-6 text-center lg:rounded-[10px]">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-[5px] bg-blue-100 text-blue-600 lg:rounded-[10px]">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5" />
                        <path d="M3 10h18" stroke="currentColor" stroke-width="1.5" />
                    </svg>
                </span>
                <h2 class="mt-3 text-lg font-bold text-gray-900">You don&rsquo;t have an active subscription yet</h2>
                <p class="mt-1 text-sm text-gray-600">Choose a plan to unlock EduNest for {{ $school->name }}.</p>
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
                <div class="rounded-[5px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 lg:rounded-[10px]">
                    Your payment for the <strong>{{ $subscription->plan->name }}</strong> is under review. We&rsquo;ll email you once it&rsquo;s confirmed.
                    <a href="{{ route('subscriptions.confirmation', $subscription) }}" class="ml-1 font-semibold underline">View details</a>
                </div>
            @endif

            {{-- Stat cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('students.index') }}" class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-500">Total Students</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 text-purple-600">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10.5" cy="9" r="3.25" stroke="currentColor" stroke-width="1.75" />
                                <path d="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-extrabold text-gray-900">{{ number_format($totalStudents) }}</p>
                    <p class="mt-1 text-xs font-medium text-gray-500">{{ number_format($activeStudents) }} active</p>
                </a>

                <a href="{{ route('staff.index') }}" class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-500">Total Staff</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="10.5" r="2.25" stroke="currentColor" stroke-width="1.6" />
                                <path d="M8.5 16c.7-1.8 2-2.5 3.5-2.5s2.8.7 3.5 2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                <path d="M5 6.5a1.5 1.5 0 011.5-1.5h11A1.5 1.5 0 0119 6.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 015 17.5v-11z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-extrabold text-gray-900">{{ number_format($totalStaff) }}</p>
                    <p class="mt-1 text-xs font-medium text-gray-500">{{ number_format($activeStaff) }} active</p>
                </a>

                @foreach ([
                    ['label' => 'Total Classes', 'iconBg' => 'bg-green-100 text-green-600', 'icon' => 'M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z', 'extra' => '<path d="M6.5 11v4c0 1.4 2.5 2.75 5.5 2.75s5.5-1.35 5.5-2.75v-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['label' => 'Outstanding Fees', 'iconBg' => 'bg-orange-100 text-orange-600', 'icon' => 'M4 7.5h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1v-9a1 1 0 011-1z', 'extra' => '<path d="M4 7.5l2.5-3h11l2.5 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /><circle cx="16.5" cy="13" r="1.5" stroke="currentColor" stroke-width="1.5" />'],
                ] as $stat)
                    <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md lg:rounded-[10px]">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                            <span class="flex h-10 w-10 items-center justify-center rounded-full {{ $stat['iconBg'] }}">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    {!! $stat['extra'] !!}
                                    <path d="{{ $stat['icon'] }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-3xl font-extrabold text-gray-300">&mdash;</p>
                        <p class="mt-1 text-xs font-medium text-gray-400">Launching soon</p>
                    </div>
                @endforeach
            </div>

            {{-- Attendance overview + Announcements --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <a href="{{ route('attendance.index') }}" class="block rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm transition-all duration-300 ease-out hover:shadow-md lg:col-span-2 lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Student Attendance Overview</h2>
                            <p class="mt-0.5 text-xs text-gray-500">Daily attendance across the last 7 days</p>
                        </div>
                        <span class="rounded-[8px] border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-500">This Month</span>
                    </div>

                    @if ($attendanceTotal > 0)
                        <div class="mt-4 flex h-48 items-end justify-between gap-2 rounded-[5px] bg-gray-50 p-4 lg:rounded-[10px]">
                            @foreach ($attendanceTrend as $day)
                                <div class="flex flex-1 flex-col items-center gap-1.5">
                                    <span class="text-[11px] font-semibold text-gray-500">{{ $day['percent'] }}%</span>
                                    <div class="flex h-28 w-full items-end rounded-[6px] bg-gray-200">
                                        <div class="w-full rounded-[6px] bg-blue-500" style="height: {{ max($day['percent'], 4) }}%"></div>
                                    </div>
                                    <span class="text-[11px] font-medium text-gray-500">{{ $day['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-4 flex h-48 items-center justify-center rounded-[5px] bg-gray-50 lg:rounded-[10px]">
                            <div class="text-center">
                                <svg class="mx-auto h-8 w-8 text-gray-300" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 19.5h16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                                    <path d="M6.5 19.5v-5.5M11 19.5V8M15.5 19.5v-8.7M20 19.5V5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                                </svg>
                                <p class="mt-2 text-sm font-medium text-gray-400">No attendance has been recorded yet this month.</p>
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ([
                            ['label' => 'Average Attendance', 'value' => $attendanceAverage !== null ? $attendanceAverage.'%' : '—'],
                            ['label' => 'Present', 'value' => number_format($attendancePresent)],
                            ['label' => 'Absent', 'value' => number_format($attendanceAbsent)],
                            ['label' => 'Late', 'value' => number_format($attendanceLate)],
                        ] as $stat)
                            <div class="rounded-[5px] border border-gray-100 p-3 text-center lg:rounded-[8px]">
                                <p class="text-xl font-extrabold text-gray-900">{{ $stat['value'] }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $stat['label'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </a>

                <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-900">Recent Announcements</h2>
                        <a href="{{ route('communications.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View All</a>
                    </div>

                    <div class="mt-4 space-y-4">
                        @forelse ($recentAnnouncements as $announcement)
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-[5px] bg-blue-50 text-blue-600 lg:rounded-[8px]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ $announcement->title }}</p>
                                    <p class="mt-0.5 line-clamp-2 text-xs text-gray-500">{{ $announcement->body }}</p>
                                    <p class="mt-1 text-xs text-gray-400">{{ $announcement->created_at->format('M j, Y') }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-6 text-center text-sm text-gray-500">No announcements yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Events + Fee collection --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-900">Upcoming Events</h2>
                        <a href="{{ route('events.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View All</a>
                    </div>
                    <div class="mt-6 flex flex-col items-center justify-center py-6 text-center">
                        <svg class="h-8 w-8 text-gray-300" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M3.5 9.5h17M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                        <p class="mt-2 text-sm font-medium text-gray-400">Your school calendar is coming soon</p>
                    </div>
                </div>

                <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-900">Fee Collection Overview</h2>
                        <span class="rounded-[8px] border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-400">This Term</span>
                    </div>
                    <div class="mt-6 flex flex-col items-center justify-center py-6 text-center">
                        <span class="flex h-24 w-24 items-center justify-center rounded-full border-8 border-gray-100">
                            <svg class="h-8 w-8 text-gray-300" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 7.5h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1v-9a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="16.5" cy="13" r="1.5" stroke="currentColor" stroke-width="1.5" />
                            </svg>
                        </span>
                        <p class="mt-3 text-sm font-medium text-gray-400">Fee tracking is coming soon</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-dashboard-layout>
