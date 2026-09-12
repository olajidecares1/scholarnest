@props([
    'pageTitle' => 'Dashboard',
    'pageSubtitle' => null,
])

@php
    $platformSettings = \App\Models\Setting::current();
    $logoUrl = $platformSettings->logoUrl();
    $school = auth()->user()->school;
    // The approved subscription when there is one, otherwise the latest
    // application - so a school waiting on approval still sees the plan it
    // submitted and its pending badge, rather than "Choose a Plan".
    $subscription = $school->subscriptionForDisplay();
    $openTicketsCount = \App\Models\SupportTicket::where('school_id', $school->id)->whereIn('status', [\App\Enums\TicketStatus::Open, \App\Enums\TicketStatus::InProgress])->count();

    // What the public has sent this school and nobody has looked at yet.
    // Counted together because they share one icon: an administrator wants
    // to know something arrived, not which of two kinds it was.
    $inboxCount = \App\Models\ContactMessage::where('school_id', $school->id)->whereNull('read_at')->count()
        + \App\Models\MisconductReport::where('school_id', $school->id)->where('status', \App\Models\MisconductReport::STATUS_NEW)->count();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} - {{ config('app.name', 'AkademicNest') }}</title>

        <x-favicon />

        {{-- Installable, like the other three portals. The School Admin's
             pages are obfuscated root paths with no school in them, so this
             app opens at the school's own admin login - which goes straight
             through to the dashboard when a session already exists. --}}
        @if ($school)
            <x-pwa :school="$school" portal="admin" />
        @endif

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
            .edn-sidebar-scroll {
                scrollbar-width: thin;
                scrollbar-color: rgba(255, 255, 255, 0.22) transparent;
            }
            .edn-sidebar-scroll::-webkit-scrollbar {
                width: 6px;
            }
            .edn-sidebar-scroll::-webkit-scrollbar-track {
                background: transparent;
            }
            .edn-sidebar-scroll::-webkit-scrollbar-thumb {
                background-color: rgba(255, 255, 255, 0.18);
                border-radius: 9999px;
            }
            .edn-sidebar-scroll::-webkit-scrollbar-thumb:hover {
                background-color: rgba(255, 255, 255, 0.32);
            }
        </style>
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-gray-200 bg-white shadow-sm print:hidden lg:flex dark:border-gray-800 dark:bg-gray-900">
            <div class="edn-sidebar-scroll flex-1 overflow-y-auto">
            <div class="mx-3 mb-3 mt-4 rounded-[8px] bg-gray-100 dark:bg-gray-800 p-3">
                <div class="flex items-center gap-2.5">
                    <span class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900/40">
                        <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-gray-900 dark:text-gray-100">{{ Str::of($school->name)->substr(0, 1)->upper() }}</span>
                        @if ($school->logoUrl())
                            <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="relative h-full w-full rounded-full bg-white object-cover" onerror="this.style.display='none'">
                        @else
                            <img src="{{ $logoUrl }}" alt="{{ $school->name }}" class="relative h-full w-full rounded-full bg-white object-contain p-1.5" onerror="this.style.display='none'">
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold leading-tight">{{ $school->name }}</p>
                        <a href="{{ route('settings.index') }}" class="mt-0.5 flex items-center gap-1 text-xs font-medium text-gray-500 dark:text-gray-400 transition-colors duration-200 hover:text-gray-900 dark:text-gray-100">
                            <span class="truncate">Session: {{ $school->current_session ?? 'Not set' }}</span>
                            <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                    </div>
                </div>
            </div>

            <nav class="space-y-1 px-3 pb-4">
                @php
                    $navLinkClasses = fn (bool $isActive) => 'group relative flex items-start gap-3 rounded-[10px] px-3 py-2.5 text-left transition-all duration-200 ease-out '
                        .($isActive
                            ? 'bg-primary-50 text-primary-800 shadow-sm ring-1 ring-primary-200 dark:bg-primary-900/40 dark:text-primary-100 dark:ring-primary-700'
                            : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-gray-900 dark:text-gray-100');
                    $navIconClasses = 'h-5 w-5 shrink-0 transition-transform duration-300 ease-out group-hover:scale-110';
                @endphp

                @foreach ([
                    ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M4 11.5L12 4l8 7.5', 'extra' => '<path d="M6 10v9a1 1 0 001 1h3v-6h4v6h3a1 1 0 001-1v-9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'students.index', 'label' => 'Students', 'icon' => 'M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7', 'extra' => '<circle cx="10.5" cy="9" r="3.25" stroke="currentColor" stroke-width="1.75" />'],
                    ['route' => 'guardians.index', 'label' => 'Parents & Guardians', 'icon' => 'M4.5 19.5c.5-2.7 2.6-4.5 5.3-4.8M18.5 19.5c-.4-2-1.7-3.5-3.5-4.2', 'extra' => '<circle cx="9" cy="8.5" r="2.75" stroke="currentColor" stroke-width="1.6" /><circle cx="16" cy="9.5" r="2.15" stroke="currentColor" stroke-width="1.4" />'],
                    ['route' => 'staff.index', 'label' => 'Teachers & Staff', 'icon' => 'M5 6.5a1.5 1.5 0 011.5-1.5h11A1.5 1.5 0 0119 6.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 015 17.5v-11z', 'extra' => '<circle cx="12" cy="10.5" r="2.25" stroke="currentColor" stroke-width="1.6" /><path d="M8.5 16c.7-1.8 2-2.5 3.5-2.5s2.8.7 3.5 2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'teacher-assignments.index', 'label' => 'Teacher Assignments', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'class-subjects.index', 'label' => 'Class Subjects', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'academics.index', 'label' => 'Academics', 'icon' => 'M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z', 'extra' => '<path d="M6.5 11v4c0 1.4 2.5 2.75 5.5 2.75s5.5-1.35 5.5-2.75v-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M20.5 9v5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                ] as $item)
                    @continue(! $school->canAccessRoute($item['route']))
                    @php $isActive = request()->routeIs(str($item['route'])->beforeLast('.').'.*'); @endphp
                    <a href="{{ route($item['route']) }}" class="{{ $navLinkClasses($isActive) }}">
                        <i class="{{ \App\Support\SidebarMeta::icon($item['route']) }} fa-fw text-[18px] leading-none text-primary-600 transition-transform duration-200 group-hover:scale-110 dark:text-primary-300"></i>
                        <span class="min-w-0 flex-1">
                            <h4 class="truncate text-[13px] font-semibold leading-tight">{{ $item['label'] }}</h4>
                            <small class="mt-0.5 block truncate text-[11px] font-normal leading-tight text-gray-500 dark:text-gray-400">{{ \App\Support\SidebarMeta::description($item['route']) }}</small>
                        </span>
                    </a>
                @endforeach

                @foreach ([
                    ['route' => 'attendance.index', 'label' => 'Attendance', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'timetable.index', 'label' => 'Timetable', 'icon' => 'M12 21a9 9 0 100-18 9 9 0 000 18z', 'extra' => '<path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'examinations.index', 'label' => 'Examinations', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                    ['route' => 'examinations.score-entry', 'label' => 'Test/Exam Score', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                    ['route' => 'results.index', 'label' => 'Results', 'icon' => 'M9 3h6a1 1 0 011 1v1h1a1 1 0 011 1v13a1 1 0 01-1 1H7a1 1 0 01-1-1V6a1 1 0 011-1h1V4a1 1 0 011-1z', 'extra' => '<path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'result-repository.index', 'label' => 'Result Repository', 'icon' => 'M4 7.5c0-1.4 3.6-2.5 8-2.5s8 1.1 8 2.5-3.6 2.5-8 2.5-8-1.1-8-2.5z', 'extra' => '<path d="M4 7.5v9c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5v-9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M4 12c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'result-pins.index', 'label' => 'Generate Exam Token', 'icon' => 'M15.5 8.5a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0z', 'extra' => '<path d="M13 11l-6.5 6.5M9 17.5l-1.5-1.5M6.5 20L5 18.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'assignments.index', 'label' => 'Assignments', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M9 11.5h6M9 14.5h6M9 17.5h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'cbt-practice.index', 'label' => 'CBT Practice', 'icon' => 'M4.5 5.5h15a1 1 0 011 1V16a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M9.5 19.5h5M12 17v2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M8 12.5l2.3 2.3L15.5 10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'cbt-tests.index', 'label' => 'CBT Tests', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /><path d="M8.5 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'events.index', 'label' => 'Events', 'icon' => 'M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M3.5 9.5h17M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'communications.index', 'label' => 'Communication', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'diary.index', 'label' => 'Teacher Diary', 'icon' => 'M4 6.5A1.5 1.5 0 015.5 5h3.6a1 1 0 01.8.4l1 1.35a1 1 0 00.8.4h6.8A1.5 1.5 0 0120 8.65V17.5a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 17.5v-11z', 'extra' => ''],
                    ['route' => 'notices.index', 'label' => 'Memorandums', 'icon' => 'M12 3a5 5 0 00-5 5v3.2c0 .5-.2 1-.5 1.4L5 15h14l-1.5-2.4c-.3-.4-.5-.9-.5-1.4V8a5 5 0 00-5-5z', 'extra' => '<path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                    ['route' => 'co-curricular.index', 'label' => 'Co-curricular', 'icon' => 'M12 4.5l2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2-4.2 2.2.8-4.7-3.4-3.3 4.7-.7z', 'extra' => ''],
                    ['route' => 'library.index', 'label' => 'Library', 'icon' => 'M3.5 6.2S5.5 5 8.5 5s5 1.2 5 1.2v12S11.5 17 8.5 17s-5 1.2-5 1.2v-12z', 'extra' => '<path d="M13.5 6.2S15.5 5 18.5 5s2 1.2 2 1.2v12s0 1.2-2 1.2-5 1.2-5 1.2v-12z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />'],
                    ['route' => 'transport.index', 'label' => 'Transport', 'icon' => 'M4 16V8.5a1 1 0 011-1h1.5l1.5-3h8l1.5 3H19a1 1 0 011 1V16a1 1 0 01-1 1h-1', 'extra' => '<path d="M6 17a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM17 17a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM7.5 17h8M4 12.5h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                    ['route' => 'hostels.index', 'label' => 'Hostel', 'icon' => 'M4 20V10.5L12 4l8 6.5V20', 'extra' => '<path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'finance.index', 'label' => 'Finance', 'icon' => 'M4 7.5h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1v-9a1 1 0 011-1z', 'extra' => '<path d="M4 7.5l2.5-3h11l2.5 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /><circle cx="16.5" cy="13" r="1.5" stroke="currentColor" stroke-width="1.5" />'],
                    ['route' => 'website.index', 'label' => 'Website', 'icon' => 'M12 3a9 9 0 100 18 9 9 0 000-18z', 'extra' => '<path d="M3 12h18M12 3c2.2 2.4 2.2 15.6 0 18M12 3c-2.2 2.4-2.2 15.6 0 18" stroke="currentColor" stroke-width="1.5" />'],
                    ['route' => 'news.index', 'label' => 'News', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'careers.index', 'label' => 'Careers', 'icon' => 'M5 6.5a1.5 1.5 0 011.5-1.5h11A1.5 1.5 0 0119 6.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 015 17.5v-11z', 'extra' => '<path d="M9 6.5V5a1.5 1.5 0 011.5-1.5h3A1.5 1.5 0 0115 5v1.5M5 11h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'testimonials.index', 'label' => 'Testimonials', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => ''],
                    ['route' => 'facilities.index', 'label' => 'Facilities', 'icon' => 'M4 20V10.5L12 4l8 6.5V20', 'extra' => '<path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'id-cards.index', 'label' => 'ID Cards', 'icon' => 'M4 6.5a1.5 1.5 0 011.5-1.5h13A1.5 1.5 0 0120 6.5v11a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 17.5v-11z', 'extra' => '<circle cx="9" cy="11" r="1.75" stroke="currentColor" stroke-width="1.5" /><path d="M6.5 15c.4-1.3 1.3-2 2.5-2s2.1.7 2.5 2M14 10h4M14 13h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                    ['route' => 'profile-change-requests.index', 'label' => 'Change Requests', 'icon' => 'M9 4.5h6l3 3V19a.5.5 0 01-.5.5h-11A.5.5 0 016 19V5a.5.5 0 01.5-.5h2.5z', 'extra' => '<path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                ] as $item)
                    @continue(! $school->canAccessRoute($item['route']))
                    @php $isActive = request()->routeIs(str($item['route'])->beforeLast('.').'.*'); @endphp
                    <a href="{{ route($item['route']) }}" class="{{ $navLinkClasses($isActive) }}">
                        <i class="{{ \App\Support\SidebarMeta::icon($item['route']) }} fa-fw text-[18px] leading-none text-primary-600 transition-transform duration-200 group-hover:scale-110 dark:text-primary-300"></i>
                        <span class="min-w-0 flex-1">
                            <h4 class="truncate text-[13px] font-semibold leading-tight">{{ $item['label'] }}</h4>
                            <small class="mt-0.5 block truncate text-[11px] font-normal leading-tight text-gray-500 dark:text-gray-400">{{ \App\Support\SidebarMeta::description($item['route']) }}</small>
                        </span>
                    </a>
                @endforeach

                @php $isSettingsActive = request()->routeIs('settings.index'); @endphp
                <a href="{{ route('settings.index') }}" class="{{ $navLinkClasses($isSettingsActive) }}">
                    <i class="fa-solid fa-gear fa-fw fa-fw text-[18px] leading-none text-primary-600 transition-transform duration-200 group-hover:scale-110 dark:text-primary-300"></i>
                    <span class="min-w-0 flex-1">
                            <h4 class="truncate text-[13px] font-semibold leading-tight">Settings</h4>
                            <small class="mt-0.5 block truncate text-[11px] font-normal leading-tight text-gray-500 dark:text-gray-400">School details and preferences</small>
                        </span>
                </a>
            </nav>

            <div class="m-3 space-y-3">
                <div class="rounded-[8px] bg-gray-100 dark:bg-gray-800 p-4 transition-colors duration-300 hover:bg-white/[0.15]">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1.5 text-sm font-semibold">
                            <svg class="h-4 w-4 text-amber-400" viewBox="0 0 24 24" fill="currentColor"><path d="M3 8l3.5 2.5L12 5l5.5 5.5L21 8l-1.5 10h-15L3 8z" /></svg>
                            Current Plan
                        </p>
                    </div>
                    @if ($subscription)
                        <p class="mt-2 text-sm font-bold text-gray-900 dark:text-gray-100">{{ $subscription->plan->name }}</p>
                        <span @class([
                            'mt-1 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                            'bg-green-400/20 text-green-300' => $subscription->status === \App\Enums\SubscriptionStatus::Active,
                            'bg-amber-400/20 text-amber-300' => $subscription->status === \App\Enums\SubscriptionStatus::PendingVerification,
                            'bg-gray-400/20 text-gray-300' => $subscription->status === \App\Enums\SubscriptionStatus::PendingPayment,
                        ])>
                            {{ $subscription->status->label() }}
                        </span>
                        @if ($subscription->ends_at)
                            <p class="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Valid Until</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $subscription->ends_at->format('M j, Y') }}</span>
                            </p>
                        @endif
                    @else
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">No active subscription yet.</p>
                    @endif
                    <a
                        href="{{ route('subscriptions.choose-plan') }}"
                        class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-[8px] bg-blue-600 px-3 py-2 text-xs font-semibold text-gray-900 dark:text-gray-100 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md active:scale-[0.97]"
                    >
                        {{ $subscription ? 'Upgrade Plan' : 'Choose a Plan' }}
                    </a>
                </div>

                <div class="rounded-[8px] bg-gray-100 dark:bg-gray-800 p-4 text-center transition-colors duration-300 hover:bg-white/[0.15]">
                    <p class="text-sm font-semibold">Need Help?</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Contact our support team.</p>
                    <a
                        href="{{ route('support-tickets.create') }}"
                        class="mt-3 inline-flex w-full items-center justify-center rounded-[8px] bg-white px-3 py-2 text-xs font-semibold text-[#111a35] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md active:scale-[0.97]"
                    >
                        Contact Support
                    </a>
                </div>
            </div>

            <div class="relative border-t border-gray-200 dark:border-gray-800 px-3 py-3" x-data="{ open: false }">
                <button type="button" @click="open = !open" @click.outside="open = false" class="flex w-full items-center gap-2.5 rounded-[8px] px-2 py-2 text-left transition-all duration-200 hover:bg-gray-100 dark:bg-gray-800 active:scale-[0.98]">
                    <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/40 text-sm font-bold text-gray-900 dark:text-gray-100">
                        {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                        <span class="absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full border-2 border-[#111a35] bg-green-400"></span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->role->label() }}</span>
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
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Edit Profile</a>
                    <form method="POST" action="{{ route('portal.admin.logout', $school) }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Log Out</button>
                    </form>
                </div>
            </div>

            <p class="px-5 pb-4 text-xs text-gray-500 dark:text-gray-400">&copy; {{ now()->year }} {{ config('app.name', 'AkademicNest') }}. All rights reserved.</p>
            </div>
        </aside>

        <div class="print:pl-0 lg:pl-64">
            @php
                $notifications = auth()->user()->notifications()->latest()->take(8)->get();
                $unreadCount = auth()->user()->unreadNotifications()->count();
            @endphp

            <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-gray-200 bg-white/90 px-4 py-3 backdrop-blur print:hidden dark:border-gray-800 dark:bg-gray-900/90 sm:px-6">
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-bold text-gray-900 dark:text-white">{{ $pageTitle }}</h1>
                    @if ($pageSubtitle)
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $pageSubtitle }}</p>
                    @endif
                </div>

                <div class="relative hidden md:block" x-data="{
                    open: false,
                    query: '',
                    loading: false,
                    results: { students: [], staff: [], classes: [] },
                    async search() {
                        if (this.query.length < 2) { this.results = { students: [], staff: [], classes: [] }; this.open = false; return; }
                        this.loading = true;
                        const res = await fetch('{{ route('search.query') }}?q=' + encodeURIComponent(this.query));
                        this.results = await res.json();
                        this.loading = false;
                        this.open = true;
                    },
                }">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.75" />
                            <path d="M20 20l-3-3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        x-model="query"
                        @input.debounce.300ms="search()"
                        @focus="if (query.length >= 2) open = true"
                        @click.outside="open = false"
                        placeholder="Search students, staff, classes..."
                        autocomplete="off"
                        class="w-64 pl-9 pr-3 transition-colors duration-200"
                    >

                    <div
                        x-show="open"
                        x-transition
                        style="display: none;"
                        class="absolute right-0 z-30 mt-2 w-80 rounded-[8px] border border-gray-200 bg-white py-2 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                    >
                        <template x-if="!loading && results.students.length === 0 && results.staff.length === 0 && results.classes.length === 0">
                            <p class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No results found.</p>
                        </template>

                        <template x-if="results.students.length > 0">
                            <div class="px-2 pb-2">
                                <p class="px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Students</p>
                                <template x-for="item in results.students" :key="item.url">
                                    <a :href="item.url" class="flex items-center justify-between gap-2 rounded-[6px] px-2 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                        <span class="truncate font-medium" x-text="item.title"></span>
                                        <span class="shrink-0 text-xs text-gray-400" x-text="item.subtitle"></span>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="results.staff.length > 0">
                            <div class="px-2 pb-2">
                                <p class="px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Staff</p>
                                <template x-for="item in results.staff" :key="item.url">
                                    <a :href="item.url" class="flex items-center justify-between gap-2 rounded-[6px] px-2 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                        <span class="truncate font-medium" x-text="item.title"></span>
                                        <span class="shrink-0 text-xs text-gray-400" x-text="item.subtitle"></span>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="results.classes.length > 0">
                            <div class="px-2 pb-2">
                                <p class="px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Classes</p>
                                <template x-for="item in results.classes" :key="item.url">
                                    <a :href="item.url" class="flex items-center justify-between gap-2 rounded-[6px] px-2 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                        <span class="truncate font-medium" x-text="item.title"></span>
                                        <span class="shrink-0 text-xs text-gray-400" x-text="item.subtitle"></span>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                @if ($school->canUseFeature(\App\Enums\PlanFeature::Website))
                {{-- websiteUrl() is null until there is a published site on a
                     plan that includes one, so this cannot open a tab at an
                     address the public site would refuse to serve. --}}
                @php $publishedWebsiteUrl = $school->websiteUrl(); @endphp
                <a
                    href="{{ $publishedWebsiteUrl ?? route('website.index') }}"
                    target="{{ $publishedWebsiteUrl ? '_blank' : '_self' }}"
                    class="hidden items-center gap-1.5 rounded-[8px] border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 transition-all duration-200 hover:border-blue-300 hover:text-blue-600 dark:border-gray-700 dark:text-gray-300 dark:hover:border-blue-700 sm:flex"
                >
                    School Website
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 17L17 7M17 7H9M17 7v8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
                @endif

                <div class="flex items-center gap-2.5 rounded-[8px] border border-gray-200 bg-gray-50 px-2 py-1.5 dark:border-gray-700 dark:bg-gray-900">
                    {{-- Messages and conduct reports from the school's own
                         website. Its own icon rather than a badge on the
                         support one: a ticket is the school asking AkademicNest
                         for help, this is the public writing to the school. --}}
                    <a
                        href="{{ route('inbox.index') }}"
                        class="relative flex h-9 w-9 items-center justify-center rounded-[6px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-white hover:text-blue-600 hover:shadow-sm active:scale-95 dark:text-gray-400 dark:hover:bg-gray-800"
                        title="Inbox"
                    >
                        <i class="fa-solid fa-envelope text-[17px]" aria-hidden="true"></i>
                        @if ($inboxCount > 0)
                            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">{{ min($inboxCount, 99) }}</span>
                        @endif
                    </a>

                    <a
                        href="{{ route('support-tickets.index') }}"
                        class="relative flex h-9 w-9 items-center justify-center rounded-[6px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-white hover:text-blue-600 hover:shadow-sm active:scale-95 dark:text-gray-400 dark:hover:bg-gray-800"
                        title="Support Tickets"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        @if ($openTicketsCount > 0)
                            <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-blue-500 text-[10px] font-bold text-white">{{ min($openTicketsCount, 99) }}</span>
                        @endif
                    </a>

                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open" @click.outside="open = false" class="relative flex h-9 w-9 items-center justify-center rounded-[6px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-white hover:text-blue-600 hover:shadow-sm active:scale-95 dark:text-gray-400 dark:hover:bg-gray-800">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 3a5 5 0 00-5 5v3.2c0 .5-.2 1-.5 1.4L5 15h14l-1.5-2.4c-.3-.4-.5-.9-.5-1.4V8a5 5 0 00-5-5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                                <path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
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
                                @if ($unreadCount > 0)
                                    <form method="POST" action="{{ route('notifications.read-all') }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Mark all read</button>
                                    </form>
                                @endif
                            </div>

                            <div class="max-h-80 overflow-y-auto">
                                @forelse ($notifications as $notification)
                                    <a
                                        href="{{ route('notifications.read', $notification) }}"
                                        class="flex items-start gap-2 border-b border-gray-50 px-4 py-3 text-left transition-colors duration-200 hover:bg-gray-50 dark:border-gray-700/50 dark:hover:bg-gray-700"
                                    >
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
                                    </a>
                                @empty
                                    <p class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No notifications yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        x-data="{ dark: document.documentElement.classList.contains('dark') }"
                        @click="
                            dark = !dark;
                            document.documentElement.classList.toggle('dark', dark);
                            localStorage.theme = dark ? 'dark' : 'light';
                            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark } }));
                        "
                        :class="dark ? 'bg-blue-600' : 'bg-gray-300'"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-300 ease-in-out active:scale-95 dark:bg-gray-600"
                        role="switch"
                        :aria-checked="dark.toString()"
                        title="Toggle dark mode"
                    >
                        <span class="sr-only">Toggle dark mode</span>
                        <span
                            :class="dark ? 'translate-x-5' : 'translate-x-0.5'"
                            class="inline-flex h-5 w-5 transform items-center justify-center rounded-full bg-white shadow-md ring-0 transition-transform duration-300 ease-in-out"
                        >
                            <svg x-show="!dark" class="h-3 w-3 text-amber-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 3v1M12 20v1M4.2 4.2l.7.7M19.1 19.1l.7.7M3 12h1M20 12h1M4.2 19.8l.7-.7M19.1 4.9l.7-.7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                                <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.75" />
                            </svg>
                            <svg x-show="dark" style="display: none;" class="h-3 w-3 text-blue-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </button>
                </div>

                <span class="relative ml-2 hidden h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-blue-50 dark:border-gray-700 sm:flex">
                    <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-blue-700">{{ Str::of($school->name)->substr(0, 1)->upper() }}</span>
                    @if ($school->logoUrl())
                        <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="relative h-full w-full rounded-full object-cover" onerror="this.style.display='none'">
                    @else
                        <img src="{{ $logoUrl }}" alt="{{ $school->name }}" class="relative h-full w-full bg-white object-contain p-1" onerror="this.style.display='none'">
                    @endif
                </span>
            </header>

            @php
                $pageNavItems = collect([
                    ['route' => 'dashboard', 'label' => 'Dashboard'],
                    ['route' => 'students.index', 'label' => 'Students'],
                    ['route' => 'guardians.index', 'label' => 'Parents & Guardians'],
                    ['route' => 'staff.index', 'label' => 'Teachers & Staff'],
                    ['route' => 'teacher-assignments.index', 'label' => 'Teacher Assignments'],
                    ['route' => 'class-subjects.index', 'label' => 'Class Subjects'],
                    ['route' => 'academics.index', 'label' => 'Academics'],
                    ['route' => 'attendance.index', 'label' => 'Attendance'],
                    ['route' => 'timetable.index', 'label' => 'Timetable'],
                    ['route' => 'examinations.index', 'label' => 'Examinations'],
                    ['route' => 'examinations.score-entry', 'label' => 'Test/Exam Score'],
                    ['route' => 'results.index', 'label' => 'Results'],
                    ['route' => 'result-repository.index', 'label' => 'Result Repository'],
                    ['route' => 'result-pins.index', 'label' => 'Generate Exam Token'],
                    ['route' => 'assignments.index', 'label' => 'Assignments'],
                    ['route' => 'cbt-practice.index', 'label' => 'CBT Practice'],
                    ['route' => 'cbt-tests.index', 'label' => 'CBT Tests'],
                    ['route' => 'events.index', 'label' => 'Events'],
                    ['route' => 'communications.index', 'label' => 'Communication'],
                    ['route' => 'diary.index', 'label' => 'Teacher Diary', 'icon' => 'M4 6.5A1.5 1.5 0 015.5 5h3.6a1 1 0 01.8.4l1 1.35a1 1 0 00.8.4h6.8A1.5 1.5 0 0120 8.65V17.5a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 17.5v-11z', 'extra' => ''],
                    ['route' => 'notices.index', 'label' => 'Memorandums'],
                    ['route' => 'co-curricular.index', 'label' => 'Co-curricular'],
                    ['route' => 'library.index', 'label' => 'Library'],
                    ['route' => 'transport.index', 'label' => 'Transport'],
                    ['route' => 'hostels.index', 'label' => 'Hostel'],
                    ['route' => 'finance.index', 'label' => 'Finance'],
                    ['route' => 'website.index', 'label' => 'Website'],
                    ['route' => 'news.index', 'label' => 'News'],
                    ['route' => 'careers.index', 'label' => 'Careers'],
                    ['route' => 'testimonials.index', 'label' => 'Testimonials'],
                    ['route' => 'facilities.index', 'label' => 'Facilities'],
                    ['route' => 'id-cards.index', 'label' => 'ID Cards'],
                    ['route' => 'profile-change-requests.index', 'label' => 'Change Requests'],
                    ['route' => 'invoices.index', 'label' => 'Invoices'],
                    ['route' => 'settings.index', 'label' => 'Settings'],
                ])
                    ->filter(fn ($item) => $school->canAccessRoute($item['route']))
                    ->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']))
                    ->map(fn ($item) => [...$item, 'url' => route($item['route'])])
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
                        $isDashboardActive = request()->routeIs('dashboard');
                        $isStudentsActive = request()->routeIs('students.*');
                        $isAttendanceActive = request()->routeIs('attendance.*');
                    @endphp

                    <a href="{{ route('dashboard') }}" class="{{ $bottomNavItemClasses($isDashboardActive) }}">
                        <svg class="{{ $bottomNavIconClasses($isDashboardActive) }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 11.5L12 4l8 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M6 10v9a1 1 0 001 1h3v-6h4v6h3a1 1 0 001-1v-9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Home
                    </a>

                    <a href="{{ route('students.index') }}" class="{{ $bottomNavItemClasses($isStudentsActive) }}">
                        <svg class="{{ $bottomNavIconClasses($isStudentsActive) }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                            <circle cx="10.5" cy="9" r="3.25" stroke="currentColor" stroke-width="1.75" />
                        </svg>
                        Students
                    </a>

                    <a href="{{ route('attendance.index') }}" class="{{ $bottomNavItemClasses($isAttendanceActive) }}">
                        <svg class="{{ $bottomNavIconClasses($isAttendanceActive) }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Attendance
                    </a>

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
                    @foreach ([
                        ['route' => 'academics.index', 'label' => 'Academics', 'icon' => 'M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z', 'extra' => '<path d="M6.5 11v4c0 1.4 2.5 2.75 5.5 2.75s5.5-1.35 5.5-2.75v-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M20.5 9v5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                        ['route' => 'timetable.index', 'label' => 'Timetable', 'icon' => 'M12 21a9 9 0 100-18 9 9 0 000 18z', 'extra' => '<path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                        ['route' => 'examinations.index', 'label' => 'Examinations', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                    ['route' => 'examinations.score-entry', 'label' => 'Test/Exam Score', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                        ['route' => 'results.index', 'label' => 'Results', 'icon' => 'M9 3h6a1 1 0 011 1v1h1a1 1 0 011 1v13a1 1 0 01-1 1H7a1 1 0 01-1-1V6a1 1 0 011-1h1V4a1 1 0 011-1z', 'extra' => '<path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                        ['route' => 'result-repository.index', 'label' => 'Result Repository', 'icon' => 'M4 7.5c0-1.4 3.6-2.5 8-2.5s8 1.1 8 2.5-3.6 2.5-8 2.5-8-1.1-8-2.5z', 'extra' => '<path d="M4 7.5v9c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5v-9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M4 12c0 1.4 3.6 2.5 8 2.5s8-1.1 8-2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'result-pins.index', 'label' => 'Generate Exam Token', 'icon' => 'M15.5 8.5a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0z', 'extra' => '<path d="M13 11l-6.5 6.5M9 17.5l-1.5-1.5M6.5 20L5 18.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                        ['route' => 'assignments.index', 'label' => 'Assignments', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 11.5h6M9 14.5h6M9 17.5h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                        ['route' => 'cbt-practice.index', 'label' => 'CBT Practice', 'icon' => 'M4.5 5.5h15a1 1 0 011 1V16a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 12.5l2.3 2.3L15.5 10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                        ['route' => 'cbt-tests.index', 'label' => 'CBT Tests', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M8.5 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                        ['route' => 'events.index', 'label' => 'Events', 'icon' => 'M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M3.5 9.5h17M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                        ['route' => 'communications.index', 'label' => 'Communication', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                        ['route' => 'diary.index', 'label' => 'Teacher Diary', 'icon' => 'M4 6.5A1.5 1.5 0 015.5 5h3.6a1 1 0 01.8.4l1 1.35a1 1 0 00.8.4h6.8A1.5 1.5 0 0120 8.65V17.5a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 17.5v-11z', 'extra' => ''],
                    ['route' => 'notices.index', 'label' => 'Memorandums', 'icon' => 'M12 3a5 5 0 00-5 5v3.2c0 .5-.2 1-.5 1.4L5 15h14l-1.5-2.4c-.3-.4-.5-.9-.5-1.4V8a5 5 0 00-5-5z', 'extra' => '<path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                        ['route' => 'co-curricular.index', 'label' => 'Co-curricular', 'icon' => 'M12 4.5l2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2-4.2 2.2.8-4.7-3.4-3.3 4.7-.7z', 'extra' => ''],
                        ['route' => 'library.index', 'label' => 'Library', 'icon' => 'M3.5 6.2S5.5 5 8.5 5s5 1.2 5 1.2v12S11.5 17 8.5 17s-5 1.2-5 1.2v-12z', 'extra' => '<path d="M13.5 6.2S15.5 5 18.5 5s2 1.2 2 1.2v12s0 1.2-2 1.2-5 1.2-5 1.2v-12z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />'],
                        ['route' => 'transport.index', 'label' => 'Transport', 'icon' => 'M4 16V8.5a1 1 0 011-1h1.5l1.5-3h8l1.5 3H19a1 1 0 011 1V16a1 1 0 01-1 1h-1', 'extra' => '<circle cx="6" cy="17" r="1.5" stroke="currentColor" stroke-width="1.5" /><circle cx="17" cy="17" r="1.5" stroke="currentColor" stroke-width="1.5" />'],
                        ['route' => 'hostels.index', 'label' => 'Hostel', 'icon' => 'M4 20V10.5L12 4l8 6.5V20', 'extra' => '<path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                        ['route' => 'finance.index', 'label' => 'Finance', 'icon' => 'M4 7.5h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1v-9a1 1 0 011-1z', 'extra' => '<circle cx="16.5" cy="13" r="1.5" stroke="currentColor" stroke-width="1.5" />'],
                        ['route' => 'website.index', 'label' => 'Website', 'icon' => 'M12 3a9 9 0 100 18 9 9 0 000-18z', 'extra' => '<path d="M3 12h18M12 3c2.2 2.4 2.2 15.6 0 18M12 3c-2.2 2.4-2.2 15.6 0 18" stroke="currentColor" stroke-width="1.5" />'],
                        ['route' => 'news.index', 'label' => 'News', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                        ['route' => 'careers.index', 'label' => 'Careers', 'icon' => 'M5 6.5a1.5 1.5 0 011.5-1.5h11A1.5 1.5 0 0119 6.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 015 17.5v-11z', 'extra' => '<path d="M9 6.5V5a1.5 1.5 0 011.5-1.5h3A1.5 1.5 0 0115 5v1.5M5 11h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                        ['route' => 'testimonials.index', 'label' => 'Testimonials', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => ''],
                        ['route' => 'facilities.index', 'label' => 'Facilities', 'icon' => 'M4 20V10.5L12 4l8 6.5V20', 'extra' => '<path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                        ['route' => 'id-cards.index', 'label' => 'ID Cards', 'icon' => 'M4 6.5a1.5 1.5 0 011.5-1.5h13A1.5 1.5 0 0120 6.5v11a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 17.5v-11z', 'extra' => '<circle cx="9" cy="11" r="1.75" stroke="currentColor" stroke-width="1.5" /><path d="M6.5 15c.4-1.3 1.3-2 2.5-2s2.1.7 2.5 2M14 10h4M14 13h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                        ['route' => 'profile-change-requests.index', 'label' => 'Change Requests', 'icon' => 'M9 4.5h6l3 3V19a.5.5 0 01-.5.5h-11A.5.5 0 016 19V5a.5.5 0 01.5-.5h2.5z', 'extra' => '<path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                        ['route' => 'guardians.index', 'label' => 'Parents & Guardians', 'icon' => 'M4.5 19.5c.5-2.7 2.6-4.5 5.3-4.8M18.5 19.5c-.4-2-1.7-3.5-3.5-4.2', 'extra' => '<circle cx="9" cy="8.5" r="2.75" stroke="currentColor" stroke-width="1.6" /><circle cx="16" cy="9.5" r="2.15" stroke="currentColor" stroke-width="1.4" />'],
                        ['route' => 'staff.index', 'label' => 'Teachers & Staff', 'icon' => 'M5 6.5a1.5 1.5 0 011.5-1.5h11A1.5 1.5 0 0119 6.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 015 17.5v-11z', 'extra' => '<circle cx="12" cy="10.5" r="2.25" stroke="currentColor" stroke-width="1.6" />'],
                        ['route' => 'teacher-assignments.index', 'label' => 'Assignments', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />'],
                        ['route' => 'class-subjects.index', 'label' => 'Class Subjects', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                        ['route' => 'settings.index', 'label' => 'Settings', 'icon' => '', 'extra' => '<circle cx="12" cy="12" r="2.75" stroke="currentColor" stroke-width="1.6" /><path d="M10.3 3.3a2 2 0 013.4 0l.5.9a2 2 0 001.6 1l1-.1a2 2 0 012.1 2.1l-.1 1a2 2 0 001 1.6l.9.5a2 2 0 010 3.4l-.9.5a2 2 0 00-1 1.6l.1 1a2 2 0 01-2.1 2.1l-1-.1a2 2 0 00-1.6 1l-.5.9a2 2 0 01-3.4 0l-.5-.9a2 2 0 00-1.6-1l-1 .1a2 2 0 01-2.1-2.1l.1-1a2 2 0 00-1-1.6l-.9-.5a2 2 0 010-3.4l.9-.5a2 2 0 001-1.6l-.1-1a2 2 0 012.1-2.1l1 .1a2 2 0 001.6-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                    ] as $item)
                        @continue(! \Illuminate\Support\Facades\Route::has($item['route']))
                        @continue(! $school->canAccessRoute($item['route']))
                            @php $isActive = request()->routeIs(str($item['route'])->beforeLast('.').'.*'); @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            @click="moreOpen = false"
                            class="group flex flex-col items-center gap-1.5 rounded-[10px] px-2 py-3 text-center text-[11px] font-semibold transition-all duration-300 ease-out active:scale-95 {{ $isActive ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50' }}"
                        >
                            <svg class="h-6 w-6 shrink-0 transition-transform duration-300 ease-out group-active:scale-90" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                {!! $item['extra'] ?? '' !!}
                                @if ($item['icon'])
                                    <path d="{{ $item['icon'] }}" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                                @endif
                            </svg>
                            <span class="leading-tight">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('portal.admin.logout', $school) }}" class="border-t border-gray-100 px-3 py-3 dark:border-gray-700">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] px-4 py-2.5 text-sm font-semibold text-red-600 transition-colors duration-200 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 21H6a2 2 0 01-2-2V5a2 2 0 012-2h3M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Log Out
                    </button>
                </form>
            </div>
        </div>

        <x-idle-session-guard :logout-url="route('portal.admin.logout', $school)" />
    </body>
</html>
