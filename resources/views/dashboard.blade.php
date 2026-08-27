<x-dashboard-layout page-title="Dashboard" :page-subtitle="'Welcome back, '.auth()->user()->name.'! Here\'s '.$school->name.' at a glance.'">
    @if ($accountState !== 'active')
        <div class="mx-auto max-w-4xl">
            @switch($accountState)
                @case('suspended')
                    <div class="rounded-[5px] border border-red-200 bg-red-50 p-6 text-center dark:border-red-800 dark:bg-red-900/20 lg:rounded-[10px]">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-[5px] bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 9v4m0 4h.01M10.3 3.9L2.5 17a2 2 0 001.7 3h15.6a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <h2 class="mt-3 text-lg font-bold text-gray-900 dark:text-white">Your school&rsquo;s account has been suspended</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Please contact EduNest support to resolve this before you can continue using your account.</p>
                    </div>
                    @break

                @case('no_subscription')
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
                    @break

                @case('pending')
                    <div class="rounded-[5px] border border-blue-200 bg-blue-50 p-6 text-center dark:border-blue-800 dark:bg-blue-900/20 lg:rounded-[10px]">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-[5px] bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 lg:rounded-[10px]">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" /><path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <h2 class="mt-3 text-lg font-bold text-gray-900 dark:text-white">Awaiting activation</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Your account is awaiting payment confirmation and activation by the EduNest Team.</p>
                        @if ($subscription?->reference)
                            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">Reference {{ $subscription->reference }} &middot; submitted {{ $subscription->created_at->diffForHumans() }}</p>
                        @endif
                    </div>
                    @break

                @case('rejected')
                    <div class="rounded-[5px] border border-amber-200 bg-amber-50 p-6 text-center dark:border-amber-800 dark:bg-amber-900/20 lg:rounded-[10px]">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-[5px] bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400 lg:rounded-[10px]">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                        </span>
                        <h2 class="mt-3 text-lg font-bold text-gray-900 dark:text-white">Your subscription was not approved</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ $subscription?->latestPayment?->notes ?? 'Please review your payment details and try again.' }}
                        </p>
                        <a
                            href="{{ route('subscriptions.choose-plan') }}"
                            class="mt-4 inline-flex items-center gap-2 rounded-[8px] bg-amber-600 px-6 py-3 text-sm font-bold text-white shadow-md shadow-amber-600/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-amber-700 hover:shadow-lg"
                        >
                            Choose a Plan Again
                        </a>
                    </div>
                    @break

                @case('expired')
                    <div class="rounded-[5px] border border-gray-200 bg-gray-50 p-6 text-center dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-[5px] bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 lg:rounded-[10px]">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" /><path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <h2 class="mt-3 text-lg font-bold text-gray-900 dark:text-white">Your subscription has expired</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Renew your plan to regain access to EduNest for {{ $school->name }}.</p>
                        <a
                            href="{{ route('subscriptions.choose-plan') }}"
                            class="mt-4 inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-md shadow-blue-600/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-lg"
                        >
                            Renew Your Plan
                        </a>
                    </div>
                    @break
            @endswitch
        </div>
    @else
        <div class="mb-6">
            <x-welcome-banner
                :photo-url="auth()->user()->photoUrl()"
                :initials="Str::of(auth()->user()->name)->substr(0, 1)->upper()"
                :name="auth()->user()->name"
                role-label="School Administrator"
                :subtitle="'Here\'s '.$school->name.' at a glance.'"
            />
        </div>

        @if ($capacity)
            <x-student-capacity-card :capacity="$capacity" class="mb-6" />
        @endif

        {{-- A dashboard, not a second menu.

             This was twenty-five cards linking to the same places as the
             sidebar. It told a head teacher nothing they could act on. What
             follows is what the school actually looks like this morning: how
             many pupils and staff, whether the registers were taken, how far
             the term's marks have got, and what is waiting on them.

             Every figure is read from the database - see
             App\Services\SchoolDashboardMetrics. --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            @foreach ([
                'students' => ['Pupils', 'fa-user-graduate'],
                'staff' => ['Teachers & Staff', 'fa-chalkboard-user'],
                'guardians' => ['Parents', 'fa-users'],
                'classes' => ['Active Classes', 'fa-layer-group'],
            ] as $key => [$label, $icon])
                <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <small class="block truncate text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</small>
                            <p class="mt-1 text-2xl font-bold leading-none text-gray-900 dark:text-white">{{ number_format($headline[$key]['value']) }}</p>
                        </div>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-primary-50 dark:bg-primary-900/30">
                            <i class="fa-solid {{ $icon }} text-primary-600 dark:text-primary-300"></i>
                        </span>
                    </div>
                    <small class="mt-2 block text-[11px] leading-tight text-gray-400 dark:text-gray-500">{{ $headline[$key]['caption'] }}</small>
                </div>
            @endforeach
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Attendance today</h3>
                    <i class="fa-solid fa-calendar-check text-primary-600 dark:text-primary-300"></i>
                </div>

                @if ($attendanceSummary['percent'] === null)
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No register has been taken today.</p>
                @else
                    <p class="mt-3 text-3xl font-bold leading-none text-gray-900 dark:text-white">{{ $attendanceSummary['percent'] }}%</p>
                    <small class="mt-1 block text-[12px] text-gray-500 dark:text-gray-400">
                        {{ number_format($attendanceSummary['present']) }} present, {{ number_format($attendanceSummary['absent']) }} absent
                    </small>
                @endif

                <small class="mt-3 block text-[11.5px] text-gray-500 dark:text-gray-400">
                    {{ $attendanceSummary['classesMarked'] }} of {{ $attendanceSummary['classesTotal'] }} classes marked
                </small>

                {{-- The week behind today, so one bad morning reads as a bad
                     morning rather than as a trend. --}}
                <div class="mt-4 flex items-end gap-1.5">
                    @foreach ($attendanceSummary['week'] as $day)
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <div class="flex h-16 w-full items-end rounded-[4px] bg-gray-100 dark:bg-gray-700">
                                @if ($day['percent'] !== null)
                                    <div class="w-full rounded-[4px] bg-primary-500" style="height: {{ max($day['percent'], 4) }}%"></div>
                                @endif
                            </div>
                            <small class="text-[10px] text-gray-400 dark:text-gray-500">{{ $day['day'] }}</small>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Results this session</h3>
                    <i class="fa-solid fa-square-poll-vertical text-primary-600 dark:text-primary-300"></i>
                </div>

                @if ($resultsSummary['examinations'] === 0)
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No examinations recorded for {{ $school->currentSession() }} yet.</p>
                @else
                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Marks entered</dt>
                            <dd class="font-bold text-gray-900 dark:text-white">{{ number_format($resultsSummary['entered']) }} / {{ number_format($resultsSummary['expected']) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Still outstanding</dt>
                            <dd class="font-bold {{ $resultsSummary['outstanding'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ number_format($resultsSummary['outstanding']) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Published to parents</dt>
                            <dd class="font-bold text-gray-900 dark:text-white">{{ number_format($resultsSummary['published']) }}</dd>
                        </div>
                    </dl>

                    @if ($resultsSummary['expected'] > 0)
                        <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-primary-500" style="width: {{ min(100, (int) round($resultsSummary['entered'] / max($resultsSummary['expected'], 1) * 100)) }}%"></div>
                        </div>
                    @endif
                @endif
            </div>

            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Waiting on you</h3>
                    <i class="fa-solid fa-bell text-primary-600 dark:text-primary-300"></i>
                </div>

                @forelse ($pendingActions as $action)
                    <div class="mt-3 flex items-center justify-between gap-3 rounded-[8px] bg-gray-50 px-3 py-2.5 dark:bg-gray-900/40">
                        <small class="text-[12.5px] font-medium text-gray-700 dark:text-gray-200">
                            @if ($action['url'])
                                <a href="{{ $action['url'] }}" class="hover:text-primary-600 hover:underline dark:hover:text-primary-300">{{ $action['label'] }}</a>
                            @else
                                {{ $action['label'] }}
                            @endif
                        </small>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                            {{ number_format($action['count']) }}
                        </span>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Nothing needs your attention.</p>
                @endforelse

                <small class="mt-4 block border-t border-gray-100 pt-3 text-[11.5px] text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <i class="fa-solid fa-calendar-days mr-1 text-gray-400"></i>
                    Session {{ $school->current_session ?? 'not set' }}
                </small>
            </div>
        </div>

        <div class="mt-6 rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Recent activity</h3>
                <i class="fa-solid fa-clock-rotate-left text-primary-600 dark:text-primary-300"></i>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($recentActivity as $entry)
                    <div class="flex items-start justify-between gap-4 px-5 py-3">
                        <div class="min-w-0">
                            <small class="block truncate text-[12.5px] font-medium text-gray-800 dark:text-gray-200">{{ $entry->description }}</small>
                            <small class="mt-0.5 block text-[11px] text-gray-400 dark:text-gray-500">{{ $entry->user_name ?: 'System' }}</small>
                        </div>
                        <small class="shrink-0 text-[11px] text-gray-400 dark:text-gray-500">{{ $entry->created_at?->diffForHumans() }}</small>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Nothing has happened yet.</p>
                @endforelse
            </div>
        </div>
    @endif
</x-dashboard-layout>
