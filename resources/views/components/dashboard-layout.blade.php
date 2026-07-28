@props([
    'pageTitle' => 'Dashboard',
    'pageSubtitle' => null,
])

@php
    $platformSettings = \App\Models\Setting::current();
    $logoUrl = $platformSettings->logo_path ? \Illuminate\Support\Facades\Storage::url($platformSettings->logo_path) : asset('images/logo-icon-dark.png');
    $school = auth()->user()->school;
    $subscription = $school->activeSubscription;
    $openTicketsCount = \App\Models\SupportTicket::where('school_id', $school->id)->whereIn('status', [\App\Enums\TicketStatus::Open, \App\Enums\TicketStatus::InProgress])->count();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} - {{ config('app.name', 'EduNest') }}</title>

        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
        @if ($platformSettings->favicon_path)
            <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::url($platformSettings->favicon_path) }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>{!! \App\Support\ThemePreset::cssVariables($platformSettings->theme_preset) !!}</style>
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased" x-data="{ sidebarOpen: false }">
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
            style="display: none;"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full transform flex-col overflow-y-auto bg-[#111a35] text-white shadow-xl transition-transform duration-200 lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center gap-2.5 px-5 py-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[5px] bg-white p-1.5 shadow-sm transition-transform duration-300 ease-out hover:scale-105 hover:rotate-3 lg:rounded-[10px]">
                    <img src="{{ $logoUrl }}" alt="{{ $school->name }}" class="h-full w-full object-contain">
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold leading-tight">{{ $school->name }}</p>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-300">School Admin Portal</p>
                </div>
            </div>

            <nav class="mt-2 flex-1 space-y-1 overflow-y-auto px-3 pb-4">
                @php
                    $navLinkClasses = fn (bool $isActive) => 'group flex min-h-[44px] items-center gap-3 rounded-[5px] px-3 py-2.5 text-sm font-medium transition-all duration-300 ease-out lg:rounded-[10px] '
                        .($isActive
                            ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30'
                            : 'text-slate-300 hover:translate-x-1 hover:bg-white/5 hover:text-white');
                    $navIconClasses = 'h-5 w-5 shrink-0 transition-transform duration-300 ease-out group-hover:scale-110';
                @endphp

                @foreach ([
                    ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M4 11.5L12 4l8 7.5', 'extra' => '<path d="M6 10v9a1 1 0 001 1h3v-6h4v6h3a1 1 0 001-1v-9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'students.index', 'label' => 'Students', 'icon' => 'M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7', 'extra' => '<circle cx="10.5" cy="9" r="3.25" stroke="currentColor" stroke-width="1.75" />'],
                    ['route' => 'staff.index', 'label' => 'Staff', 'icon' => 'M5 6.5a1.5 1.5 0 011.5-1.5h11A1.5 1.5 0 0119 6.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 015 17.5v-11z', 'extra' => '<circle cx="12" cy="10.5" r="2.25" stroke="currentColor" stroke-width="1.6" /><path d="M8.5 16c.7-1.8 2-2.5 3.5-2.5s2.8.7 3.5 2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'academics.index', 'label' => 'Academics', 'icon' => 'M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z', 'extra' => '<path d="M6.5 11v4c0 1.4 2.5 2.75 5.5 2.75s5.5-1.35 5.5-2.75v-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M20.5 9v5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'attendance.index', 'label' => 'Attendance', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M9 12.5l2 2 4-4.2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'examinations.index', 'label' => 'Examinations', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                    ['route' => 'assignments.index', 'label' => 'Assignments', 'icon' => 'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z', 'extra' => '<path d="M9 4V3.3a1 1 0 011-1h4a1 1 0 011 1V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M9 11.5h6M9 14.5h6M9 17.5h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'events.index', 'label' => 'Events', 'icon' => 'M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M3.5 9.5h17M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'communications.index', 'label' => 'Communications', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'library.index', 'label' => 'Library', 'icon' => 'M3.5 6.2S5.5 5 8.5 5s5 1.2 5 1.2v12S11.5 17 8.5 17s-5 1.2-5 1.2v-12z', 'extra' => '<path d="M13.5 6.2S15.5 5 18.5 5s2 1.2 2 1.2v12s0 1.2-2 1.2-5 1.2-5 1.2v-12z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />'],
                    ['route' => 'finance.index', 'label' => 'Finance', 'icon' => 'M4 7.5h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1v-9a1 1 0 011-1z', 'extra' => '<path d="M4 7.5l2.5-3h11l2.5 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /><circle cx="16.5" cy="13" r="1.5" stroke="currentColor" stroke-width="1.5" />'],
                    ['route' => 'website.index', 'label' => 'Website', 'icon' => 'M12 3a9 9 0 100 18 9 9 0 000-18z', 'extra' => '<path d="M3 12h18M12 3c2.2 2.4 2.2 15.6 0 18M12 3c-2.2 2.4-2.2 15.6 0 18" stroke="currentColor" stroke-width="1.5" />'],
                ] as $item)
                    @php $isActive = request()->routeIs(str($item['route'])->beforeLast('.').'.*'); @endphp
                    <a href="{{ route($item['route']) }}" class="{{ $navLinkClasses($isActive) }}">
                        <svg class="{{ $navIconClasses }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            {!! $item['extra'] ?? '' !!}
                            <path d="{{ $item['icon'] }}" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach

                @php $isSettingsActive = request()->routeIs('settings.index'); @endphp
                <a href="{{ route('settings.index') }}" class="{{ $navLinkClasses($isSettingsActive) }}">
                    <svg class="{{ $navIconClasses }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="2.75" stroke="currentColor" stroke-width="1.6" />
                        <path d="M10.3 3.3a2 2 0 013.4 0l.5.9a2 2 0 001.6 1l1-.1a2 2 0 012.1 2.1l-.1 1a2 2 0 001 1.6l.9.5a2 2 0 010 3.4l-.9.5a2 2 0 00-1 1.6l.1 1a2 2 0 01-2.1 2.1l-1-.1a2 2 0 00-1.6 1l-.5.9a2 2 0 01-3.4 0l-.5-.9a2 2 0 00-1.6-1l-1 .1a2 2 0 01-2.1-2.1l.1-1a2 2 0 00-1-1.6l-.9-.5a2 2 0 010-3.4l.9-.5a2 2 0 001-1.6l-.1-1a2 2 0 012.1-2.1l1 .1a2 2 0 001.6-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Settings
                </a>
            </nav>

            <div class="m-3 space-y-3">
                <div class="rounded-[5px] bg-white/10 p-4 transition-colors duration-300 hover:bg-white/[0.15] lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1.5 text-sm font-semibold">
                            <svg class="h-4 w-4 text-amber-400" viewBox="0 0 24 24" fill="currentColor"><path d="M3 8l3.5 2.5L12 5l5.5 5.5L21 8l-1.5 10h-15L3 8z" /></svg>
                            Current Plan
                        </p>
                    </div>
                    @if ($subscription)
                        <p class="mt-2 text-sm font-bold text-white">{{ $subscription->plan->name }}</p>
                        <span @class([
                            'mt-1 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                            'bg-green-400/20 text-green-300' => $subscription->status === \App\Enums\SubscriptionStatus::Active,
                            'bg-amber-400/20 text-amber-300' => $subscription->status === \App\Enums\SubscriptionStatus::PendingVerification,
                            'bg-gray-400/20 text-gray-300' => $subscription->status === \App\Enums\SubscriptionStatus::PendingPayment,
                        ])>
                            {{ $subscription->status->label() }}
                        </span>
                        @if ($subscription->ends_at)
                            <p class="mt-2 flex items-center justify-between text-xs text-slate-300">
                                <span>Valid Until</span>
                                <span class="font-semibold text-white">{{ $subscription->ends_at->format('M j, Y') }}</span>
                            </p>
                        @endif
                    @else
                        <p class="mt-2 text-xs text-slate-300">No active subscription yet.</p>
                    @endif
                    <a
                        href="{{ route('subscriptions.choose-plan') }}"
                        class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-[5px] bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md lg:rounded-[10px]"
                    >
                        {{ $subscription ? 'Upgrade Plan' : 'Choose a Plan' }}
                    </a>
                </div>

                <div class="rounded-[5px] bg-white/10 p-4 text-center transition-colors duration-300 hover:bg-white/[0.15] lg:rounded-[10px]">
                    <p class="text-sm font-semibold">Need Help?</p>
                    <p class="mt-1 text-xs text-slate-300">Contact our support team.</p>
                    <a
                        href="{{ route('support-tickets.create') }}"
                        class="mt-3 inline-flex w-full items-center justify-center rounded-[5px] bg-white px-3 py-2 text-xs font-semibold text-[#111a35] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md lg:rounded-[10px]"
                    >
                        Contact Support
                    </a>
                </div>
            </div>

            <p class="px-5 pb-5 text-xs text-slate-400">&copy; {{ now()->year }} {{ config('app.name', 'EduNest') }}. All rights reserved.</p>
        </aside>

        <div class="lg:pl-64">
            @php
                $notifications = auth()->user()->notifications()->latest()->take(8)->get();
                $unreadCount = auth()->user()->unreadNotifications()->count();
            @endphp

            <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-gray-200 bg-white/90 px-4 py-3 backdrop-blur sm:px-6">
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-gray-100 hover:text-blue-600 lg:hidden"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-bold text-gray-900">{{ $pageTitle }}</h1>
                    @if ($pageSubtitle)
                        <p class="truncate text-sm text-gray-500">{{ $pageSubtitle }}</p>
                    @endif
                </div>

                <div class="relative hidden md:block">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.75" />
                            <path d="M20 20l-3-3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        disabled
                        placeholder="Search students, staff, payments, events..."
                        class="w-64 rounded-[5px] border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm text-gray-500 placeholder:text-gray-400 lg:rounded-[10px]"
                    >
                </div>

                <a
                    href="{{ route('support-tickets.index') }}"
                    class="relative flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-gray-100 hover:text-blue-600 lg:rounded-[10px]"
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
                    <button type="button" @click="open = !open" @click.outside="open = false" class="relative flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-gray-100 hover:text-blue-600 lg:rounded-[10px]">
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
                        class="absolute right-0 z-30 mt-2 w-80 rounded-[5px] border border-gray-200 bg-white shadow-lg lg:rounded-[10px]"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                            <p class="text-sm font-bold text-gray-900">Notifications</p>
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
                                    class="flex items-start gap-2 border-b border-gray-50 px-4 py-3 text-left transition-colors duration-200 hover:bg-gray-50"
                                >
                                    @if (is_null($notification->read_at))
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500"></span>
                                    @else
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-transparent"></span>
                                    @endif
                                    <span>
                                        <span class="block text-sm font-semibold text-gray-900">{{ $notification->data['title'] ?? 'Notification' }}</span>
                                        <span class="block text-xs text-gray-500">{{ $notification->data['body'] ?? '' }}</span>
                                        <span class="block text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                                    </span>
                                </a>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-gray-500">No notifications yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700">
                            {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-semibold text-gray-900">{{ auth()->user()->name }}</span>
                            <span class="block text-xs text-gray-500">{{ auth()->user()->role->label() }}</span>
                        </span>
                        <svg class="hidden h-4 w-4 text-gray-400 sm:block" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        x-transition
                        style="display: none;"
                        class="absolute right-0 mt-2 w-48 rounded-[5px] border border-gray-200 bg-white py-1 shadow-lg lg:rounded-[10px]"
                    >
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Edit Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Log Out</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
