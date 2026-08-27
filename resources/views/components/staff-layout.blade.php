@props([
    'pageTitle' => 'Dashboard',
    'pageSubtitle' => null,
])

@php
    $platformSettings = \App\Models\Setting::current();
    $staff = auth('staff')->user();
    $school = $staff->school;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} - {{ $school->name }}</title>

        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>{!! \App\Support\ThemePreset::cssVariables($platformSettings->theme_preset) !!}</style>
        <style>
            .edn-sidebar-scroll { scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, 0.22) transparent; }
            .edn-sidebar-scroll::-webkit-scrollbar { width: 6px; }
            .edn-sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
            .edn-sidebar-scroll::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.18); border-radius: 9999px; }
            .edn-sidebar-scroll::-webkit-scrollbar-thumb:hover { background-color: rgba(255, 255, 255, 0.32); }
        </style>
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-[#111a35] text-white shadow-xl print:hidden lg:flex">
            <div class="edn-sidebar-scroll flex-1 overflow-y-auto">
                <div class="mx-3 mb-1 mt-4 rounded-[8px] bg-white/10 p-3">
                    <div class="flex items-center gap-2.5">
                        <span class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-500">
                            <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-white">{{ Str::of($school->name)->substr(0, 1)->upper() }}</span>
                            @if ($school->logoUrl())
                                <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="relative h-full w-full rounded-full bg-white object-cover" onerror="this.style.display='none'">
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold leading-tight">{{ $school->name }}</p>
                            <p class="mt-0.5 truncate text-xs font-medium text-slate-300">Staff Portal</p>
                        </div>
                    </div>
                </div>

                <nav class="space-y-1 px-3 pb-4 pt-3">
                    @php
                        $navLinkClasses = fn (bool $isActive) => 'group flex min-h-[42px] items-center gap-3 rounded-[8px] px-3 py-2.5 text-[13px] font-medium transition-all duration-300 ease-out active:scale-[0.97] '
                            .($isActive
                                ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30'
                                : 'text-slate-300 hover:translate-x-1 hover:bg-white/5 hover:text-white');
                        $navIconClasses = 'h-5 w-5 shrink-0 transition-transform duration-300 ease-out group-hover:scale-110';

                        $navItems = [
                            ['route' => 'staff.profile', 'label' => 'My Profile', 'icon' => 'M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7', 'extra' => '<circle cx="10.5" cy="9" r="3.25" stroke="currentColor" stroke-width="1.75" />'],
                            ['route' => 'staff.id-card.show', 'label' => 'ID Card', 'icon' => 'M4 6.5h16a1 1 0 011 1V17a1 1 0 01-1 1H4a1 1 0 01-1-1V7.5a1 1 0 011-1z', 'extra' => '<circle cx="8" cy="12" r="1.75" stroke="currentColor" stroke-width="1.5" /><path d="M12.5 10.5h5M12.5 13.5h5M5.5 16.2c.3-1.2 1.3-2 2.5-2s2.2.8 2.5 2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />'],
                            ['route' => 'staff.timetable', 'label' => 'My Timetable', 'icon' => '', 'extra' => '<path d="M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /><path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M8.5 12.5h7M8.5 15.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                            ...($staff->role === \App\Enums\StaffRole::Teacher ? [
                                ['route' => 'staff.attendance.index', 'label' => 'Attendance', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />'],
                                ['route' => 'staff.diary.index', 'label' => 'Diary', 'icon' => 'M4 6.5A1.5 1.5 0 015.5 5h3.6a1 1 0 01.8.4l1 1.35a1 1 0 00.8.4h6.8A1.5 1.5 0 0120 8.65V17.5a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 17.5v-11z', 'extra' => ''],
                                ['route' => 'staff.exams.index', 'label' => 'Test/Exam Score', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                                ['route' => 'staff.results.index', 'label' => 'Report Cards', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M9 12.5h6M9 15.5h6M9 9.5h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                                ['route' => 'staff.cbt.tests.index', 'label' => 'CBT Management', 'icon' => 'M4.5 5.5h15a1 1 0 011 1V16a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M9.5 19.5h5M12 17v2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M8 12.5l2.3 2.3L15.5 10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                            ] : []),
                            ['route' => 'staff.settings.index', 'label' => 'Settings', 'icon' => '', 'extra' => '<circle cx="12" cy="12" r="2.75" stroke="currentColor" stroke-width="1.6" /><path d="M10.3 3.3a2 2 0 013.4 0l.5.9a2 2 0 001.6 1l1-.1a2 2 0 012.1 2.1l-.1 1a2 2 0 001 1.6l.9.5a2 2 0 010 3.4l-.9.5a2 2 0 00-1 1.6l.1 1a2 2 0 01-2.1 2.1l-1-.1a2 2 0 00-1.6 1l-.5.9a2 2 0 01-3.4 0l-.5-.9a2 2 0 00-1.6-1l-1 .1a2 2 0 01-2.1-2.1l.1-1a2 2 0 00-1-1.6l-.9-.5a2 2 0 010-3.4l.9-.5a2 2 0 001-1.6l-.1-1a2 2 0 012.1-2.1l1 .1a2 2 0 001.6-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                            ['route' => 'staff.help.index', 'label' => 'Help & Support', 'icon' => '', 'extra' => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6" /><path d="M9.5 9.3a2.5 2.5 0 114 2c-.9.6-1.5 1.1-1.5 2.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><circle cx="12" cy="17" r=".9" fill="currentColor" />'],
                        ];

                        // CBT and ID cards stay on Standard and Exclusive, but the
                        // staff portal itself is open to every plan now - so these
                        // two entries need gating on their own. Their routes 403 on
                        // Basic, and a link that 403s is worse than no link at all.
                        $hasPremiumStaffModules = $school->hasPlanAccess(\App\Enums\PlanKey::Standard, \App\Enums\PlanKey::Exclusive);

                        if (! $hasPremiumStaffModules) {
                            $navItems = array_values(array_filter(
                                $navItems,
                                fn (array $item): bool => ! in_array($item['route'], ['staff.id-card.show', 'staff.cbt.tests.index', 'staff.diary.index', 'staff.timetable'], true),
                            ));
                        }
                    @endphp

                    @php $isDashboardActive = request()->routeIs('staff.dashboard'); @endphp
                    <a href="{{ route('staff.dashboard', $school) }}" class="{{ $navLinkClasses($isDashboardActive) }}">
                        <svg class="{{ $navIconClasses }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 11.5L12 4l8 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /><path d="M6 10v9a1 1 0 001 1h3v-6h4v6h3a1 1 0 001-1v-9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Dashboard
                    </a>

                    @foreach ($navItems as $item)
                        @continue(! \Illuminate\Support\Facades\Route::has($item['route']))
                        @php $isActive = request()->routeIs(str($item['route'])->beforeLast('.').'.*') || request()->routeIs($item['route']); @endphp
                        <a href="{{ route($item['route'], $school) }}" class="{{ $navLinkClasses($isActive) }}">
                            <svg class="{{ $navIconClasses }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                {!! $item['extra'] ?? '' !!}
                                @if ($item['icon'])
                                    <path d="{{ $item['icon'] }}" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                                @endif
                            </svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="m-3">
                    <div class="rounded-[8px] bg-white/10 p-4 text-center transition-colors duration-300 hover:bg-white/[0.15]">
                        <p class="text-sm font-semibold">Need Help?</p>
                        <p class="mt-1 text-xs text-slate-300">Our support team is here to help.</p>
                        @if (\Illuminate\Support\Facades\Route::has('staff.help.index'))
                            <a
                                href="{{ route('staff.help.index', $school) }}"
                                class="mt-3 inline-flex w-full items-center justify-center rounded-[8px] bg-white px-3 py-2 text-xs font-semibold text-[#111a35] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md active:scale-[0.97]"
                            >
                                Contact Support
                            </a>
                        @endif
                    </div>
                </div>

                <div class="relative border-t border-white/10 px-3 py-3" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="flex w-full items-center gap-2.5 rounded-[8px] px-2 py-2 text-left transition-all duration-200 hover:bg-white/5 active:scale-[0.98]">
                        <span class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-500 text-sm font-bold text-white">
                            {{ Str::of($staff->fullName())->substr(0, 1)->upper() }}
                            <span class="absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full border-2 border-[#111a35] bg-green-400"></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-white">{{ $staff->fullName() }}</span>
                            <span class="block truncate text-xs text-slate-400">{{ $staff->role->label() }}</span>
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-300 ease-out" :class="{ 'rotate-180': open }" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        x-transition
                        style="display: none;"
                        class="absolute bottom-full left-3 right-3 z-30 mb-2 rounded-[8px] border border-gray-200 bg-white py-1 shadow-lg"
                    >
                        @if (\Illuminate\Support\Facades\Route::has('staff.settings.index'))
                            <a href="{{ route('staff.settings.index', $school) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Edit Profile</a>
                        @endif
                        <form method="POST" action="{{ route('staff.logout', $school) }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Log Out</button>
                        </form>
                    </div>
                </div>

                <p class="px-5 pb-4 text-xs text-slate-400">&copy; {{ now()->year }} {{ $school->name }}. All rights reserved.</p>
            </div>
        </aside>

        <div class="print:pl-0 lg:pl-64">
            <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-gray-200 bg-white/90 px-4 py-3 backdrop-blur print:hidden dark:border-gray-800 dark:bg-gray-900/90 sm:px-6">
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-bold text-gray-900 dark:text-white">{{ $pageTitle }}</h1>
                    @if ($pageSubtitle)
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $pageSubtitle }}</p>
                    @endif
                </div>

                <button
                    type="button"
                    x-data="{ dark: document.documentElement.classList.contains('dark') }"
                    @click="
                        dark = !dark;
                        document.documentElement.classList.toggle('dark', dark);
                        localStorage.theme = dark ? 'dark' : 'light';
                    "
                    :class="dark ? 'bg-blue-600' : 'bg-gray-300'"
                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-300 ease-in-out active:scale-95 dark:bg-gray-600"
                    role="switch"
                    :aria-checked="dark.toString()"
                    title="Toggle dark mode"
                >
                    <span class="sr-only">Toggle dark mode</span>
                    <span :class="dark ? 'translate-x-5' : 'translate-x-0.5'" class="inline-flex h-5 w-5 transform items-center justify-center rounded-full bg-white shadow-md ring-0 transition-transform duration-300 ease-in-out"></span>
                </button>

                <span class="relative ml-2 hidden h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-blue-50 dark:border-gray-700 sm:flex">
                    @if ($staff->photoUrl())
                        <img src="{{ $staff->photoUrl() }}" class="h-full w-full object-cover">
                    @else
                        <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-blue-700">{{ Str::of($staff->fullName())->substr(0, 1)->upper() }}</span>
                    @endif
                </span>
            </header>

            @php
                $pageNavItems = collect([
                    ['route' => 'staff.dashboard', 'label' => 'Dashboard', 'url' => route('staff.dashboard', $school)],
                ])
                    ->concat(
                        collect($navItems)
                            ->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']))
                            ->map(fn ($item) => ['route' => $item['route'], 'label' => $item['label'], 'url' => route($item['route'], $school)])
                    )
                    ->values()
                    ->all();
                $pageNav = \App\Support\PageNavigator::resolve($pageNavItems, request()->route()?->getName());
            @endphp
            <x-mobile-page-nav :prev="$pageNav['prev']" :next="$pageNav['next']" :current-label="$pageNav['current']" />

            <main class="p-4 pb-24 sm:p-6 lg:p-8 lg:pb-8 dark:bg-gray-900">
                {{ $slot }}
            </main>
        </div>

        {{-- Mobile/tablet bottom navigation --}}
        <div x-data="{ moreOpen: false }">
            <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 backdrop-blur print:hidden lg:hidden dark:border-gray-800 dark:bg-gray-900/95">
                <div class="mx-auto grid max-w-2xl grid-cols-4">
                    @php
                        $bottomNavItemClasses = fn (bool $isActive) => 'group flex flex-col items-center gap-1 py-2.5 text-[11px] font-semibold transition-all duration-300 ease-out active:scale-90 '
                            .($isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400 hover:text-blue-500 dark:text-gray-500 dark:hover:text-blue-400');
                        $bottomNavIconClasses = fn (bool $isActive) => 'h-6 w-6 shrink-0 transition-all duration-300 ease-out group-active:scale-90 '.($isActive ? '-translate-y-0.5' : '');
                        $isDashboardActive = request()->routeIs('staff.dashboard');
                    @endphp

                    <a href="{{ route('staff.dashboard', $school) }}" class="{{ $bottomNavItemClasses($isDashboardActive) }}">
                        <svg class="{{ $bottomNavIconClasses($isDashboardActive) }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 11.5L12 4l8 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M6 10v9a1 1 0 001 1h3v-6h4v6h3a1 1 0 001-1v-9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Home
                    </a>

                    {{-- Gated as well as routed: on Basic the timetable route
                         403s, and a bottom-bar tab that fails is worse than one
                         that is not there. --}}
                    @if (\Illuminate\Support\Facades\Route::has('staff.timetable') && $hasPremiumStaffModules)
                        @php $isTimetableActive = request()->routeIs('staff.timetable'); @endphp
                        <a href="{{ route('staff.timetable', $school) }}" class="{{ $bottomNavItemClasses($isTimetableActive) }}">
                            <svg class="{{ $bottomNavIconClasses($isTimetableActive) }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M8.5 12.5h7M8.5 15.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                            Timetable
                        </a>
                    @endif

                    @if (\Illuminate\Support\Facades\Route::has('staff.profile'))
                        @php $isProfileActive = request()->routeIs('staff.profile'); @endphp
                        <a href="{{ route('staff.profile', $school) }}" class="{{ $bottomNavItemClasses($isProfileActive) }}">
                            <svg class="{{ $bottomNavIconClasses($isProfileActive) }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10.5" cy="9" r="3.25" stroke="currentColor" stroke-width="1.75" /><path d="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                            Profile
                        </a>
                    @endif

                    <button type="button" @click="moreOpen = true" class="{{ $bottomNavItemClasses(false) }}">
                        <svg class="{{ $bottomNavIconClasses(false) }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="5" cy="12" r="1.6" fill="currentColor" />
                            <circle cx="12" cy="12" r="1.6" fill="currentColor" />
                            <circle cx="19" cy="12" r="1.6" fill="currentColor" />
                        </svg>
                        More
                    </button>
                </div>
            </nav>

            {{-- "More" bottom sheet --}}
            <div
                x-show="moreOpen"
                x-transition.opacity
                @click="moreOpen = false"
                class="fixed inset-0 z-50 bg-gray-900/50 lg:hidden"
                style="display: none;"
            ></div>

            <div
                x-show="moreOpen"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="translate-y-full"
                x-transition:enter-end="translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="translate-y-0"
                x-transition:leave-end="translate-y-full"
                class="fixed inset-x-0 bottom-0 z-50 max-h-[75vh] overflow-y-auto rounded-t-[16px] bg-white pb-[calc(env(safe-area-inset-bottom)+0.5rem)] shadow-2xl print:hidden lg:hidden dark:bg-gray-800"
                style="display: none;"
                @click.outside="moreOpen = false"
            >
                <div class="mx-auto mt-2.5 h-1.5 w-10 rounded-full bg-gray-300 dark:bg-gray-600"></div>

                <div class="grid grid-cols-4 gap-1 px-3 pb-3 pt-4">
                    @if ($staff->role === \App\Enums\StaffRole::Teacher && \Illuminate\Support\Facades\Route::has('staff.attendance.index'))
                        <a href="{{ route('staff.attendance.index', $school) }}" @click="moreOpen = false" class="group flex flex-col items-center gap-1.5 rounded-[10px] px-2 py-3 text-center text-[11px] font-semibold text-gray-600 transition-all duration-300 ease-out hover:bg-gray-50 active:scale-95 dark:text-gray-300 dark:hover:bg-gray-700/50">
                            <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /><path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            <span>Attendance</span>
                        </a>
                    @endif
                    @if ($staff->role === \App\Enums\StaffRole::Teacher && \Illuminate\Support\Facades\Route::has('staff.exams.index'))
                        <a href="{{ route('staff.exams.index', $school) }}" @click="moreOpen = false" class="group flex flex-col items-center gap-1.5 rounded-[10px] px-2 py-3 text-center text-[11px] font-semibold text-gray-600 transition-all duration-300 ease-out hover:bg-gray-50 active:scale-95 dark:text-gray-300 dark:hover:bg-gray-700/50">
                            <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                            <span>Scores</span>
                        </a>
                    @endif
                    @if ($staff->role === \App\Enums\StaffRole::Teacher && \Illuminate\Support\Facades\Route::has('staff.results.index'))
                        <a href="{{ route('staff.results.index', $school) }}" @click="moreOpen = false" class="group flex flex-col items-center gap-1.5 rounded-[10px] px-2 py-3 text-center text-[11px] font-semibold text-gray-600 transition-all duration-300 ease-out hover:bg-gray-50 active:scale-95 dark:text-gray-300 dark:hover:bg-gray-700/50">
                            <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M15 3.5V7h3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M9 12.5h6M9 15.5h6M9 9.5h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                            <span>Report Cards</span>
                        </a>
                    @endif
                    @if ($hasPremiumStaffModules && $staff->role === \App\Enums\StaffRole::Teacher && \Illuminate\Support\Facades\Route::has('staff.cbt.tests.index'))
                        <a href="{{ route('staff.cbt.tests.index', $school) }}" @click="moreOpen = false" class="group flex flex-col items-center gap-1.5 rounded-[10px] px-2 py-3 text-center text-[11px] font-semibold text-gray-600 transition-all duration-300 ease-out hover:bg-gray-50 active:scale-95 dark:text-gray-300 dark:hover:bg-gray-700/50">
                            <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.5 5.5h15a1 1 0 011 1V16a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /><path d="M9.5 19.5h5M12 17v2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M8 12.5l2.3 2.3L15.5 10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            <span>CBT</span>
                        </a>
                    @endif
                    @if (\Illuminate\Support\Facades\Route::has('staff.settings.index'))
                        <a href="{{ route('staff.settings.index', $school) }}" @click="moreOpen = false" class="group flex flex-col items-center gap-1.5 rounded-[10px] px-2 py-3 text-center text-[11px] font-semibold text-gray-600 transition-all duration-300 ease-out hover:bg-gray-50 active:scale-95 dark:text-gray-300 dark:hover:bg-gray-700/50">
                            <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="2.75" stroke="currentColor" stroke-width="1.6" /><path d="M10.3 3.3a2 2 0 013.4 0l.5.9a2 2 0 001.6 1l1-.1a2 2 0 012.1 2.1l-.1 1a2 2 0 001 1.6l.9.5a2 2 0 010 3.4l-.9.5a2 2 0 00-1 1.6l.1 1a2 2 0 01-2.1 2.1l-1-.1a2 2 0 00-1.6 1l-.5.9a2 2 0 01-3.4 0l-.5-.9a2 2 0 00-1.6-1l-1 .1a2 2 0 01-2.1-2.1l.1-1a2 2 0 00-1-1.6l-.9-.5a2 2 0 010-3.4l.9-.5a2 2 0 001-1.6l-.1-1a2 2 0 012.1-2.1l1 .1a2 2 0 001.6-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            <span>Settings</span>
                        </a>
                    @endif
                    @if (\Illuminate\Support\Facades\Route::has('staff.help.index'))
                        <a href="{{ route('staff.help.index', $school) }}" @click="moreOpen = false" class="group flex flex-col items-center gap-1.5 rounded-[10px] px-2 py-3 text-center text-[11px] font-semibold text-gray-600 transition-all duration-300 ease-out hover:bg-gray-50 active:scale-95 dark:text-gray-300 dark:hover:bg-gray-700/50">
                            <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6" /><path d="M9.5 9.3a2.5 2.5 0 114 2c-.9.6-1.5 1.1-1.5 2.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><circle cx="12" cy="17" r=".9" fill="currentColor" /></svg>
                            <span>Help</span>
                        </a>
                    @endif
                </div>

                <form method="POST" action="{{ route('staff.logout', $school) }}" class="border-t border-gray-100 px-3 py-3 dark:border-gray-700">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] px-4 py-2.5 text-sm font-semibold text-red-600 transition-colors duration-200 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 21H6a2 2 0 01-2-2V5a2 2 0 012-2h3M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Log Out
                    </button>
                </form>
            </div>
        </div>

        <x-idle-session-guard :logout-url="route('staff.logout', $school)" />
    </body>
</html>
