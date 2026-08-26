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

        <div class="grid grid-cols-4 gap-2.5 sm:grid-cols-5 sm:gap-3 md:grid-cols-6 lg:grid-cols-4 lg:gap-4 xl:grid-cols-5">
            <x-module-card :href="route('overview.index')" label="Overview" icon="fa-chart-line" color="text-primary-600 bg-primary-50 dark:bg-primary-900/20" />
            <x-module-card :href="route('reports.summary')" label="Reports" icon="fa-chart-pie" color="text-slate-600 bg-slate-100 dark:bg-slate-700" />

            <x-module-card :href="route('students.index')" label="Students" icon="fa-user-graduate" color="text-purple-600 bg-purple-50 dark:bg-purple-900/20" :badge="number_format($moduleCounts['students'])" />
            <x-module-card :href="route('staff.index')" label="Teachers & Staff" icon="fa-chalkboard-user" color="text-blue-600 bg-blue-50 dark:bg-blue-900/20" :badge="number_format($moduleCounts['staff'])" />
            @if (\Illuminate\Support\Facades\Route::has('academics.index'))
                <x-module-card :href="route('academics.index')" label="Academics" icon="fa-book-open-reader" color="text-teal-600 bg-teal-50 dark:bg-teal-900/20" />
            @endif
            <x-module-card :href="route('attendance.index')" label="Attendance" icon="fa-clipboard-check" color="text-green-600 bg-green-50 dark:bg-green-900/20" :badge="$moduleCounts['attendanceToday'] > 0 ? $moduleCounts['attendanceToday'].'%' : null" />
            @if ($school->canAccessRoute('timetable.index'))
                <x-module-card :href="route('timetable.index')" label="Timetable" icon="fa-calendar-days" color="text-cyan-600 bg-cyan-50 dark:bg-cyan-900/20" />
            @endif
            <x-module-card :href="route('examinations.index')" label="Examinations" icon="fa-file-pen" color="text-amber-600 bg-amber-50 dark:bg-amber-900/20" />
            {{-- Every plan. A Standard school still has students whose results
                 are withheld over fees, and still needs a way to hand one of
                 them a link. --}}
            <x-module-card :href="route('result-pins.index')" label="Exam Tokens" icon="fa-key" color="text-teal-600 bg-teal-50 dark:bg-teal-900/20" />
            @if ($school->canAccessRoute('assignments.index'))
                <x-module-card :href="route('assignments.index')" label="Assignments" icon="fa-list-check" color="text-rose-600 bg-rose-50 dark:bg-rose-900/20" />
            @endif
            @if ($school->canAccessRoute('cbt-practice.index'))
                <x-module-card :href="route('cbt-practice.index')" label="CBT Practice" icon="fa-laptop-code" color="text-indigo-600 bg-indigo-50 dark:bg-indigo-900/20" />
            @endif
            @if (\Illuminate\Support\Facades\Route::has('cbt-tests.index'))
                @if ($school->canAccessRoute('cbt-tests.index'))
                    <x-module-card :href="route('cbt-tests.index')" label="CBT Tests" icon="fa-file-circle-check" color="text-violet-600 bg-violet-50 dark:bg-violet-900/20" />
                @endif
            @endif
            @if ($school->canAccessRoute('events.index'))
                <x-module-card :href="route('events.index')" label="Events" icon="fa-calendar-days" color="text-orange-600 bg-orange-50 dark:bg-orange-900/20" />
            @endif
            @if ($school->canAccessRoute('diary.index'))
                <x-module-card :href="route('diary.index')" label="Teacher Diary" icon="fa-folder" color="text-amber-600 bg-amber-50 dark:bg-amber-900/20" />
            @endif
            <x-module-card :href="route('communications.index')" label="Communication" icon="fa-bullhorn" color="text-sky-600 bg-sky-50 dark:bg-sky-900/20" />
            @if (\Illuminate\Support\Facades\Route::has('notices.index'))
                <x-module-card :href="route('notices.index')" label="Memorandums" icon="fa-bell" color="text-yellow-600 bg-yellow-50 dark:bg-yellow-900/20" />
            @endif
            @if (\Illuminate\Support\Facades\Route::has('co-curricular.index'))
                @if ($school->canAccessRoute('co-curricular.index'))
                    <x-module-card :href="route('co-curricular.index')" label="Co-curricular" icon="fa-medal" color="text-fuchsia-600 bg-fuchsia-50 dark:bg-fuchsia-900/20" />
                @endif
            @endif
            @if ($school->canAccessRoute('library.index'))
                <x-module-card :href="route('library.index')" label="Library" icon="fa-book" color="text-lime-600 bg-lime-50 dark:bg-lime-900/20" />
            @endif
            @if ($school->canAccessRoute('transport.index'))
                <x-module-card :href="route('transport.index')" label="Transport" icon="fa-bus" color="text-violet-600 bg-violet-50 dark:bg-violet-900/20" />
            @endif
            @if ($school->canAccessRoute('hostels.index'))
                <x-module-card :href="route('hostels.index')" label="Hostel" icon="fa-building" color="text-orange-700 bg-orange-50 dark:bg-orange-900/20" />
            @endif
            @if ($school->canAccessRoute('finance.index'))
                <x-module-card :href="route('finance.index')" label="Finance" icon="fa-sack-dollar" color="text-red-600 bg-red-50 dark:bg-red-900/20" :badge="$moduleCounts['outstandingFees'] !== '₦0' ? $moduleCounts['outstandingFees'] : null" />
            @endif
            @if ($school->canAccessRoute('website.index'))
                <x-module-card :href="route('website.index')" label="Website" icon="fa-globe" color="text-blue-600 bg-blue-50 dark:bg-blue-900/20" />
            @endif
            @if ($school->canAccessRoute('news.index'))
                <x-module-card :href="route('news.index')" label="News" icon="fa-newspaper" color="text-pink-600 bg-pink-50 dark:bg-pink-900/20" />
            @endif
            @if ($school->canAccessRoute('careers.index'))
                <x-module-card :href="route('careers.index')" label="Careers" icon="fa-briefcase" color="text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20" />
            @endif
            @if ($school->canAccessRoute('testimonials.index'))
                <x-module-card :href="route('testimonials.index')" label="Testimonials" icon="fa-quote-left" color="text-teal-600 bg-teal-50 dark:bg-teal-900/20" />
            @endif
            @if ($school->canAccessRoute('facilities.index'))
                <x-module-card :href="route('facilities.index')" label="Facilities" icon="fa-building-columns" color="text-cyan-600 bg-cyan-50 dark:bg-cyan-900/20" />
            @endif
            @if (\Illuminate\Support\Facades\Route::has('id-cards.index'))
                @if ($school->canAccessRoute('id-cards.index'))
                    <x-module-card :href="route('id-cards.index')" label="ID Cards" icon="fa-id-card" color="text-indigo-600 bg-indigo-50 dark:bg-indigo-900/20" />
                @endif
            @endif
            <x-module-card :href="route('settings.index')" label="Settings" icon="fa-gear" color="text-gray-600 bg-gray-100 dark:bg-gray-700" />
        </div>
    @endif
</x-dashboard-layout>
