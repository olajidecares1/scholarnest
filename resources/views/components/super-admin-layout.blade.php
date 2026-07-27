@props([
    'pageTitle' => 'Dashboard',
    'pageSubtitle' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} - {{ config('app.name', 'EduNest') }} Super Admin</title>

        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100" x-data="{ sidebarOpen: false }">
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
            style="display: none;"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full transform flex-col overflow-y-auto bg-primary-800 text-white shadow-xl transition-transform duration-200 lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center gap-2 px-5 py-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[5px] bg-white p-1.5 shadow-sm lg:rounded-[10px]">
                    <img src="{{ asset('images/logo-mark.png') }}" alt="EduNest" class="h-full w-full object-contain">
                </span>
                <div>
                    <p class="text-lg font-bold leading-tight">EduNest</p>
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-100">Super Admin</p>
                </div>
            </div>

            <nav class="mt-2 flex-1 space-y-1 px-3 pb-4">
                @php
                    $navLink = fn (string $route, string $label, string $icon, ?string $routePattern = null) => [
                        'route' => $route, 'label' => $label, 'icon' => $icon, 'pattern' => $routePattern ?? $route,
                    ];
                @endphp

                <a
                    href="{{ route('super-admin.dashboard') }}"
                    class="flex min-h-[44px] items-center gap-3 rounded-[5px] px-3 py-2.5 text-sm font-medium transition lg:rounded-[10px] {{ request()->routeIs('super-admin.dashboard') ? 'bg-white/20 text-white shadow-sm' : 'text-primary-50 hover:bg-white/10 hover:text-white' }}"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 11.5L12 4l8 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M6 10v9a1 1 0 001 1h3v-6h4v6h3a1 1 0 001-1v-9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Dashboard
                </a>

                <a
                    href="{{ route('super-admin.schools.index') }}"
                    class="flex min-h-[44px] items-center gap-3 rounded-[5px] px-3 py-2.5 text-sm font-medium transition lg:rounded-[10px] {{ request()->routeIs('super-admin.schools.*') ? 'bg-white/20 text-white shadow-sm' : 'text-primary-50 hover:bg-white/10 hover:text-white' }}"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 21h16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                        <path d="M5 21V10M19 21V10" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                        <path d="M3 10l9-6 9 6" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" />
                    </svg>
                    Schools
                </a>

                @foreach ([
                    ['route' => 'super-admin.subscriptions.index', 'label' => 'Subscriptions', 'icon' => 'M3 5h18M3 5a2 2 0 00-2 2v6a2 2 0 002 2h18a2 2 0 002-2V7a2 2 0 00-2-2M3 5h18'],
                    ['route' => 'super-admin.payments.index', 'label' => 'Payments', 'icon' => 'M4 7h16M4 7a2 2 0 00-2 2v6a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2M4 7l1.7-3.4A2 2 0 017.5 2.5h9a2 2 0 011.8 1.1L20 7M8 15h.01M12 15h4'],
                    ['route' => 'super-admin.users.index', 'label' => 'Users', 'icon' => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8zM22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75'],
                    ['route' => 'super-admin.roles.index', 'label' => 'Roles & Permissions', 'icon' => 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z'],
                    ['route' => 'super-admin.reports.index', 'label' => 'Reports', 'icon' => 'M9 17v-6M15 17v-2M12 17v-9M4 21h16a1 1 0 001-1V4a1 1 0 00-1-1H4a1 1 0 00-1 1v16a1 1 0 001 1z'],
                    ['route' => 'super-admin.analytics.index', 'label' => 'Analytics', 'icon' => 'M4 20V10M10 20V4M16 20v-7M20 20v-3'],
                    ['route' => 'super-admin.communications.index', 'label' => 'Communications', 'icon' => 'M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z'],
                    ['route' => 'super-admin.support-tickets.index', 'label' => 'Support Tickets', 'icon' => 'M9 12h6M9 16h6M17 21H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z'],
                    ['route' => 'super-admin.cms.index', 'label' => 'CMS', 'icon' => 'M4 6h16M4 12h16M4 18h7'],
                    ['route' => 'super-admin.themes.index', 'label' => 'Themes', 'icon' => 'M12 3a9 9 0 109 9c0-.5-.05-1-.14-1.45a3.5 3.5 0 01-4.85-4.4A9 9 0 0012 3zM7.5 10.5h.01M9.5 7.5h.01M14.5 7.5h.01'],
                    ['route' => 'super-admin.settings.index', 'label' => 'System Settings', 'icon' => 'M10.3 3.3a2 2 0 013.4 0l.5.9a2 2 0 001.6 1l1-.1a2 2 0 012.1 2.1l-.1 1a2 2 0 001 1.6l.9.5a2 2 0 010 3.4l-.9.5a2 2 0 00-1 1.6l.1 1a2 2 0 01-2.1 2.1l-1-.1a2 2 0 00-1.6 1l-.5.9a2 2 0 01-3.4 0l-.5-.9a2 2 0 00-1.6-1l-1 .1a2 2 0 01-2.1-2.1l.1-1a2 2 0 00-1-1.6l-.9-.5a2 2 0 010-3.4l.9-.5a2 2 0 001-1.6l-.1-1a2 2 0 012.1-2.1l1 .1a2 2 0 001.6-1z'],
                    ['route' => 'super-admin.audit-logs.index', 'label' => 'Audit Logs', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h6'],
                ] as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        class="flex min-h-[44px] items-center gap-3 rounded-[5px] px-3 py-2.5 text-sm font-medium transition lg:rounded-[10px] {{ request()->routeIs(str($item['route'])->beforeLast('.').'.*') ? 'bg-white/20 text-white shadow-sm' : 'text-primary-50 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="{{ $item['icon'] }}" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="m-3 rounded-[5px] bg-white/10 p-4 text-center lg:rounded-[10px]">
                <p class="text-sm font-semibold">Super Administrator</p>
                <p class="mt-1 text-xs text-primary-50">You have full access to all platform features.</p>
                <a
                    href="{{ route('super-admin.settings.index') }}"
                    class="mt-3 inline-flex w-full items-center justify-center rounded-[5px] bg-white px-3 py-2 text-xs font-semibold text-primary-700 lg:rounded-[10px]"
                >
                    System Settings
                </a>
            </div>

            <p class="px-5 pb-5 text-xs text-primary-100">&copy; {{ now()->year }} EduNest. All rights reserved.</p>
        </aside>

        <div class="lg:pl-64">
            @php
                $notifications = auth()->user()->notifications()->latest()->take(8)->get();
                $unreadCount = auth()->user()->unreadNotifications()->count();
            @endphp

            <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-gray-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90 sm:px-6">
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:hidden"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-bold text-gray-900 dark:text-white">{{ $pageTitle }}</h1>
                    @if ($pageSubtitle)
                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $pageSubtitle }}</p>
                    @endif
                </div>

                <p
                    x-data="{ time: '' }"
                    x-init="
                        const tick = () => time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                        tick();
                        setInterval(tick, 1000 * 30);
                    "
                    x-text="time"
                    class="hidden shrink-0 text-sm font-medium tabular-nums text-gray-500 dark:text-gray-400 lg:block"
                ></p>

                <div class="relative hidden md:block" x-data="{
                    open: false,
                    query: '',
                    loading: false,
                    results: { schools: [], users: [], subscriptions: [] },
                    async search() {
                        if (this.query.length < 2) { this.results = { schools: [], users: [], subscriptions: [] }; this.open = false; return; }
                        this.loading = true;
                        const res = await fetch('{{ route('super-admin.search') }}?q=' + encodeURIComponent(this.query));
                        this.results = await res.json();
                        this.loading = false;
                        this.open = true;
                    },
                }">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
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
                        placeholder="Search schools, users, payments..."
                        autocomplete="off"
                        class="w-64 rounded-[5px] border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary-500 focus:bg-white focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:placeholder:text-gray-500 lg:rounded-[10px]"
                    >

                    <div
                        x-show="open"
                        x-transition
                        style="display: none;"
                        class="absolute right-0 z-30 mt-2 w-80 rounded-[5px] border border-gray-200 bg-white py-2 shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
                    >
                        <template x-if="!loading && results.schools.length === 0 && results.users.length === 0 && results.subscriptions.length === 0">
                            <p class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No results found.</p>
                        </template>

                        <template x-if="results.schools.length > 0">
                            <div class="px-2 pb-2">
                                <p class="px-2 py-1 text-xs font-bold uppercase text-gray-400">Schools</p>
                                <template x-for="item in results.schools" :key="item.url">
                                    <a :href="item.url" class="block rounded-[5px] px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <span class="font-medium text-gray-900 dark:text-white" x-text="item.title"></span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="item.subtitle"></span>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="results.users.length > 0">
                            <div class="px-2 pb-2">
                                <p class="px-2 py-1 text-xs font-bold uppercase text-gray-400">Users</p>
                                <template x-for="item in results.users" :key="item.url">
                                    <a :href="item.url" class="block rounded-[5px] px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <span class="font-medium text-gray-900 dark:text-white" x-text="item.title"></span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="item.subtitle"></span>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="results.subscriptions.length > 0">
                            <div class="px-2">
                                <p class="px-2 py-1 text-xs font-bold uppercase text-gray-400">Subscriptions</p>
                                <template x-for="item in results.subscriptions" :key="item.url">
                                    <a :href="item.url" class="block rounded-[5px] px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <span class="font-medium text-gray-900 dark:text-white" x-text="item.title"></span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="item.subtitle"></span>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <button
                    type="button"
                    x-data
                    @click="
                        document.documentElement.classList.toggle('dark');
                        localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                    "
                    class="flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:rounded-[10px]"
                >
                    <svg class="h-5 w-5 dark:hidden" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3v1M12 20v1M4.2 4.2l.7.7M19.1 19.1l.7.7M3 12h1M20 12h1M4.2 19.8l.7-.7M19.1 4.9l.7-.7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                        <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.75" />
                    </svg>
                    <svg class="hidden h-5 w-5 dark:block" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" />
                    </svg>
                </button>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="relative flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:rounded-[10px]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 3a5 5 0 00-5 5v3.2c0 .5-.2 1-.5 1.4L5 15h14l-1.5-2.4c-.3-.4-.5-.9-.5-1.4V8a5 5 0 00-5-5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                            <path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                        @if ($unreadCount > 0)
                            <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">{{ min($unreadCount, 99) }}</span>
                        @endif
                    </button>

                    <div
                        x-show="open"
                        x-transition
                        style="display: none;"
                        class="absolute right-0 z-30 mt-2 w-80 rounded-[5px] border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">Notifications</p>
                            @if ($unreadCount > 0)
                                <form method="POST" action="{{ route('notifications.read-all') }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-primary-500 hover:text-primary-600">Mark all read</button>
                                </form>
                            @endif
                        </div>

                        <div class="max-h-80 overflow-y-auto">
                            @forelse ($notifications as $notification)
                                <a
                                    href="{{ route('notifications.read', $notification) }}"
                                    class="flex items-start gap-2 border-b border-gray-50 px-4 py-3 text-left hover:bg-gray-50 dark:border-gray-700/50 dark:hover:bg-gray-700"
                                >
                                    @if (is_null($notification->read_at))
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500"></span>
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

                <a
                    href="{{ route('super-admin.communications.index') }}"
                    class="flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:rounded-[10px]"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                    </svg>
                </a>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700">
                            {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ auth()->user()->name }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->role->label() }}</span>
                        </span>
                    </button>

                    <div
                        x-show="open"
                        x-transition
                        style="display: none;"
                        class="absolute right-0 mt-2 w-48 rounded-[5px] border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
                    >
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">Edit Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">Log Out</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8 dark:bg-gray-900">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
