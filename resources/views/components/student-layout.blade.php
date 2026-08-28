@props([
    'pageTitle' => 'Dashboard',
    'pageSubtitle' => null,
])

@php
    $platformSettings = \App\Models\Setting::current();
    $logoUrl = $platformSettings->logo_path ? \Illuminate\Support\Facades\Storage::url($platformSettings->logo_path) : asset('images/logo-icon-dark.png');
    $student = auth('student')->user();
    $school = $student->school;

    // One source for what this pupil may see - the bottom bar, the More sheet
    // and the home screen all read it, so they cannot disagree.
    $portalNav = \App\Support\PortalNavigation::forStudent($student);

    $unreadNotifications = \App\Support\PortalNavigation::unreadNotifications($student);
    $unreadMessages = \App\Support\PortalNavigation::unreadMessages($student);

    $portalBadges = array_filter([
        'student.notifications.index' => $unreadNotifications,
        'student.messages.index' => $unreadMessages,
    ]);
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

        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- The platform palette first, then this school's own brand colour
             over it. The sidebar icons read --color-primary, so School A
             gets School A's colour and a school that has set none falls
             back to the platform's rather than losing its icons. --}}
        <style>
            {!! \App\Support\ThemePreset::cssVariables($platformSettings->theme_preset) !!}
            {!! $school?->website?->brand_primary_color ? \App\Support\BrandColorScale::cssVariables('primary', $school->website->brand_primary_color) : '' !!}
        </style>
        <style>
            .edn-sidebar-scroll { scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, 0.22) transparent; }
            .edn-sidebar-scroll::-webkit-scrollbar { width: 6px; }
            .edn-sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
            .edn-sidebar-scroll::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.18); border-radius: 9999px; }
            .edn-sidebar-scroll::-webkit-scrollbar-thumb:hover { background-color: rgba(255, 255, 255, 0.32); }
        </style>
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-gray-200 bg-white shadow-sm print:hidden lg:flex dark:border-gray-800 dark:bg-gray-900">
            <div class="edn-sidebar-scroll flex-1 overflow-y-auto">
                <div class="mx-3 mb-1 mt-4 rounded-[8px] bg-gray-100 dark:bg-gray-800 p-3">
                    <div class="flex items-center gap-2.5">
                        <span class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900/40">
                            <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-gray-900 dark:text-gray-100">{{ Str::of($school->name)->substr(0, 1)->upper() }}</span>
                            @if ($school->logoUrl())
                                <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="relative h-full w-full rounded-full bg-white object-cover" onerror="this.style.display='none'">
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold leading-tight">{{ $school->name }}</p>
                            <p class="mt-0.5 truncate text-xs font-medium text-gray-500 dark:text-gray-400">Student Portal</p>
                        </div>
                    </div>
                </div>

                <nav class="space-y-1 px-3 pb-4 pt-3">
                    @php
                        $navLinkClasses = fn (bool $isActive) => 'group relative flex items-start gap-3 rounded-[10px] px-3 py-2.5 text-left transition-all duration-200 ease-out '
                        .($isActive
                            ? 'bg-primary-50 text-primary-800 shadow-sm ring-1 ring-primary-200 dark:bg-primary-900/40 dark:text-primary-100 dark:ring-primary-700'
                            : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-gray-900 dark:text-gray-100');
                        $navIconClasses = 'h-5 w-5 shrink-0 transition-transform duration-300 ease-out group-hover:scale-110';

                        $navItems = [
                            ['route' => 'student.dashboard', 'label' => 'Dashboard', 'icon' => 'M4 11.5L12 4l8 7.5', 'extra' => '<path d="M6 10v9a1 1 0 001 1h3v-6h4v6h3a1 1 0 001-1v-9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />'],
                            ['route' => 'student.profile', 'label' => 'My Profile', 'icon' => 'M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7', 'extra' => '<circle cx="10.5" cy="9" r="3.25" stroke="currentColor" stroke-width="1.75" />'],
                            ['route' => 'student.id-card.show', 'label' => 'ID Card', 'icon' => 'M4 6.5h16a1 1 0 011 1V17a1 1 0 01-1 1H4a1 1 0 01-1-1V7.5a1 1 0 011-1z', 'extra' => '<circle cx="8" cy="12" r="1.75" stroke="currentColor" stroke-width="1.5" /><path d="M12.5 10.5h5M12.5 13.5h5M5.5 16.2c.3-1.2 1.3-2 2.5-2s2.2.8 2.5 2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />'],
                            ['route' => 'student.timetable', 'label' => 'My Timetable', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M8.5 12.5h7M8.5 15.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                            ['route' => 'student.subjects', 'label' => 'My Subjects', 'icon' => 'M3.5 6.2S5.5 5 8.5 5s5 1.2 5 1.2v12S11.5 17 8.5 17s-5 1.2-5 1.2v-12z', 'extra' => '<path d="M13.5 6.2S15.5 5 18.5 5s2 1.2 2 1.2v12s0 1.2-2 1.2-5 1.2-5 1.2v-12z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />'],
                            ['route' => 'student.assignments.index', 'label' => 'Assignments', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M9 11.5h6M9 14.5h6M9 17.5h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                            ['route' => 'student.results.index', 'label' => 'Exams & Results', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                            ['route' => 'student.attendance.index', 'label' => 'Attendance', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />'],
                            ['route' => 'student.library.index', 'label' => 'Library', 'icon' => 'M3.5 6.2S5.5 5 8.5 5s5 1.2 5 1.2v12S11.5 17 8.5 17s-5 1.2-5 1.2v-12z', 'extra' => '<path d="M13.5 6.2S15.5 5 18.5 5s2 1.2 2 1.2v12s0 1.2-2 1.2-5 1.2-5 1.2v-12z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />'],
                            ['route' => 'student.cbt-practice.index', 'label' => 'CBT Practice', 'icon' => 'M4.5 5.5h15a1 1 0 011 1V16a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M9.5 19.5h5M12 17v2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M8 12.5l2.3 2.3L15.5 10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />', 'badge' => 'New'],
                            ['route' => 'student.tests.index', 'label' => 'My Tests', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /><path d="M8.5 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                            ['route' => 'student.messages.index', 'label' => 'Messages', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                            ['route' => 'student.notifications.index', 'label' => 'Notifications', 'icon' => 'M12 3a5 5 0 00-5 5v3.2c0 .5-.2 1-.5 1.4L5 15h14l-1.5-2.4c-.3-.4-.5-.9-.5-1.4V8a5 5 0 00-5-5z', 'extra' => '<path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                            ['route' => 'student.co-curricular.index', 'label' => 'Co-curricular', 'icon' => 'M12 4.5l2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2-4.2 2.2.8-4.7-3.4-3.3 4.7-.7z', 'extra' => ''],
                            ['route' => 'student.settings.index', 'label' => 'Settings', 'icon' => '', 'extra' => '<circle cx="12" cy="12" r="2.75" stroke="currentColor" stroke-width="1.6" /><path d="M10.3 3.3a2 2 0 013.4 0l.5.9a2 2 0 001.6 1l1-.1a2 2 0 012.1 2.1l-.1 1a2 2 0 001 1.6l.9.5a2 2 0 010 3.4l-.9.5a2 2 0 00-1 1.6l.1 1a2 2 0 01-2.1 2.1l-1-.1a2 2 0 00-1.6 1l-.5.9a2 2 0 01-3.4 0l-.5-.9a2 2 0 00-1.6-1l-1 .1a2 2 0 01-2.1-2.1l.1-1a2 2 0 00-1-1.6l-.9-.5a2 2 0 010-3.4l.9-.5a2 2 0 001-1.6l-.1-1a2 2 0 012.1-2.1l1 .1a2 2 0 001.6-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                            ['route' => 'student.help.index', 'label' => 'Help & Support', 'icon' => '', 'extra' => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6" /><path d="M9.5 9.3a2.5 2.5 0 114 2c-.9.6-1.5 1.1-1.5 2.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><circle cx="12" cy="17" r=".9" fill="currentColor" />'],
                        ];
                    @endphp

                    @foreach ($navItems as $item)
                        @continue(! \Illuminate\Support\Facades\Route::has($item['route']))
                        @php $isActive = request()->routeIs(str($item['route'])->beforeLast('.').'.*') || request()->routeIs($item['route']); @endphp
                        <a href="{{ route($item['route'], $school) }}" class="{{ $navLinkClasses($isActive) }}">
                            <i class="{{ \App\Support\SidebarMeta::icon($item['route']) }} fa-fw text-[18px] leading-none text-primary-600 transition-transform duration-200 group-hover:scale-110 dark:text-primary-300"></i>
                            <span class="min-w-0 flex-1">
                            <h4 class="truncate text-[13px] font-semibold leading-tight">{{ $item['label'] }}</h4>
                            <small class="mt-0.5 block truncate text-[11px] font-normal leading-tight text-gray-500 dark:text-gray-400">{{ \App\Support\SidebarMeta::description($item['route']) }}</small>
                        </span>
                            @if (! empty($item['badge']))
                                <span class="rounded-full bg-emerald-400/20 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-300">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="m-3">
                    <div class="rounded-[8px] bg-gray-100 dark:bg-gray-800 p-4 text-center transition-colors duration-300 hover:bg-white/[0.15]">
                        <p class="text-sm font-semibold">Need Help?</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Our support team is here to help.</p>
                        @if (\Illuminate\Support\Facades\Route::has('student.help.index'))
                            <a
                                href="{{ route('student.help.index', $school) }}"
                                class="mt-3 inline-flex w-full items-center justify-center rounded-[8px] bg-white px-3 py-2 text-xs font-semibold text-[#111a35] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md active:scale-[0.97]"
                            >
                                Contact Support
                            </a>
                        @endif
                    </div>
                </div>

                <div class="relative border-t border-gray-200 dark:border-gray-800 px-3 py-3" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="flex w-full items-center gap-2.5 rounded-[8px] px-2 py-2 text-left transition-all duration-200 hover:bg-gray-100 dark:bg-gray-800 active:scale-[0.98]">
                        <span class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900/40 text-sm font-bold text-gray-900 dark:text-gray-100">
                            {{ Str::of($student->first_name)->substr(0, 1)->upper() }}
                            @if ($student->photoUrl())
                                <img src="{{ $student->photoUrl() }}" alt="{{ $student->fullName() }}" class="absolute inset-0 h-full w-full object-cover">
                            @endif
                            <span class="absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full border-2 border-[#111a35] bg-green-400"></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $student->fullName() }}</span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $student->admission_number }}</span>
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400 transition-transform duration-300 ease-out" :class="{ 'rotate-180': open }" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        x-transition
                        style="display: none;"
                        class="absolute bottom-full left-3 right-3 z-30 mb-2 rounded-[8px] border border-gray-200 bg-white py-1 shadow-lg"
                    >
                        @if (\Illuminate\Support\Facades\Route::has('student.settings.index'))
                            <a href="{{ route('student.settings.index', $school) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Edit Profile</a>
                        @endif
                        <form method="POST" action="{{ route('student.logout', $school) }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Log Out</button>
                        </form>
                    </div>
                </div>

                <p class="px-5 pb-4 text-xs text-gray-500 dark:text-gray-400">&copy; {{ now()->year }} {{ $school->name }}. All rights reserved.</p>
            </div>
        </aside>

        <div class="print:pl-0 lg:pl-64">
            @php
                $notifications = $student->notifications()->latest()->take(8)->get();
                $unreadCount = $student->unreadNotifications()->count();
            @endphp

            <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-gray-200 bg-white/90 px-4 py-3 backdrop-blur print:hidden dark:border-gray-800 dark:bg-gray-900/90 sm:px-6">
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-bold text-gray-900 dark:text-white">{{ $pageTitle }}</h1>
                    @if ($pageSubtitle)
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $pageSubtitle }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-2.5 rounded-[8px] border border-gray-200 bg-gray-50 px-2 py-1.5 dark:border-gray-700 dark:bg-gray-900">
                    {{-- Messages sit beside the bell, as they would on a phone.
                         The bell keeps its dropdown; this is the envelope. --}}
                    <x-portal-top-actions
                        :messages-url="\Illuminate\Support\Facades\Route::has('student.messages.index') ? route('student.messages.index', $school) : null"
                        :unread-messages="$unreadMessages"
                    />

                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open" @click.outside="open = false" class="relative flex h-9 w-9 items-center justify-center rounded-[6px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-white hover:text-blue-600 hover:shadow-sm active:scale-95 dark:text-gray-400 dark:hover:bg-gray-800">
                            <i class="fa-solid fa-bell fa-fw text-[17px] leading-none"></i>
                            @if ($unreadCount > 0)
                                <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 animate-pulse items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">{{ min($unreadCount, 99) }}</span>
                            @endif
                        </button>

                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            style="display: none;"
                            class="absolute right-0 z-30 mt-2 w-80 rounded-[8px] border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">Notifications</p>
                            </div>

                            <div class="max-h-80 overflow-y-auto">
                                @forelse ($notifications as $notification)
                                    <div class="flex items-start gap-2 border-b border-gray-50 px-4 py-3 dark:border-gray-700/50">
                                        @if (is_null($notification->read_at))
                                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500"></span>
                                        @else
                                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-transparent"></span>
                                        @endif
                                        <span>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ $notification->data['title'] ?? 'Notification' }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $notification->data['body'] ?? '' }}</span>
                                            <span class="block text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                                        </span>
                                    </div>
                                @empty
                                    <p class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No notifications yet.</p>
                                @endforelse
                            </div>
                            @if (\Illuminate\Support\Facades\Route::has('student.notifications.index'))
                                <a href="{{ route('student.notifications.index', $school) }}" class="block border-t border-gray-100 px-4 py-2.5 text-center text-xs font-semibold text-blue-600 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">View All</a>
                            @endif
                        </div>
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
                </div>

                <span class="relative ml-2 hidden h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-blue-50 dark:border-gray-700 sm:flex">
                    <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-blue-700">{{ Str::of($student->first_name)->substr(0, 1)->upper() }}</span>
                    @if ($student->photoUrl())
                        <img src="{{ $student->photoUrl() }}" alt="{{ $student->fullName() }}" class="relative h-full w-full object-cover">
                    @endif
                </span>
            </header>

            @php
                $pageNavItems = collect($navItems)
                    ->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']))
                    ->map(fn ($item) => ['route' => $item['route'], 'label' => $item['label'], 'url' => route($item['route'], $school)])
                    ->values()
                    ->all();
                $pageNav = \App\Support\PageNavigator::resolve($pageNavItems, request()->route()?->getName());
            @endphp
            <x-mobile-page-nav :prev="$pageNav['prev']" :next="$pageNav['next']" :current-label="$pageNav['current']" />

            <main class="p-4 pb-24 sm:p-6 lg:p-8 lg:pb-8 dark:bg-gray-900">
                {{ $slot }}
            </main>
        </div>

        {{-- The app-style bottom bar. Every item, and every plan and role
             rule behind it, comes from App\Support\PortalNavigation. --}}
        <x-portal-bottom-nav
            :primary="$portalNav['primary']"
            :categories="$portalNav['categories']"
            :badges="$portalBadges"
            :logout-url="route('student.logout', $school)"
        />

        <x-idle-session-guard :logout-url="route('student.logout', $school)" />
    </body>
</html>
